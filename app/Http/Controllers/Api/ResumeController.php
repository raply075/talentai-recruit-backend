<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResumeUploadRequest;
use App\Models\Resume;
use App\Services\Resume\ResumeAnalysisService;
use App\Services\Resume\ResumeParserService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ResumeController extends Controller
{
    use ApiResponse;

    protected ResumeParserService $parserService;
    protected ResumeAnalysisService $analysisService;

    public function __construct(
        ResumeParserService $parserService,
        ResumeAnalysisService $analysisService
    ) {
        $this->parserService = $parserService;
        $this->analysisService = $analysisService;
    }

    /**
     * Upload dan analisis resume
     */
    public function upload(ResumeUploadRequest $request)
    {
        $file = $request->file('resume');

        /*
        |--------------------------------------------------------------------------
        | 1. Simpan file
        |--------------------------------------------------------------------------
        */

        $path = $file->store('resumes', 'public');

        /*
        |--------------------------------------------------------------------------
        | 2. Buat data resume awal
        |--------------------------------------------------------------------------
        */

        $resume = Resume::create([
            'user_id' => $request->user()->id,
            'title' => pathinfo(
                $file->getClientOriginalName(),
                PATHINFO_FILENAME
            ),
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'ats_score' => null,
            'career_level' => null,
            'skills' => [],
            'suggestions' => [],
            'structured_resume' => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 3. Lokasi file
        |--------------------------------------------------------------------------
        */

        $fullPath = storage_path(
            'app/public/' . $path
        );

        /*
        |--------------------------------------------------------------------------
        | 4. Parse resume
        |--------------------------------------------------------------------------
        */

        try {

            $text = $this->parserService->parse($fullPath);

            Log::info('RESUME PARSED', [
                'resume_id' => $resume->id,
                'text_length' => mb_strlen($text),
            ]);

        } catch (\Throwable $e) {

            Log::error('RESUME PARSER ERROR', [
                'resume_id' => $resume->id,
                'message' => $e->getMessage(),
            ]);

            return $this->error(
                'Resume gagal dibaca.',
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Validasi hasil parsing
        |--------------------------------------------------------------------------
        */

        if (trim($text) === '') {

            return $this->error(
                'Teks resume tidak dapat dibaca atau kosong.',
                422
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Analisis ATS
        |--------------------------------------------------------------------------
        */

        try {

            $analysis = $this->analysisService->analyze($text);

            Log::info('CONTROLLER ANALYSIS', [
                'resume_id' => $resume->id,
                'ats_score' => $analysis['ats_score'] ?? null,
                'career_level' => $analysis['career_level'] ?? null,
                'skills' => $analysis['skills'] ?? [],
            ]);

        } catch (\Throwable $e) {

            Log::error('ATS ANALYSIS ERROR', [
                'resume_id' => $resume->id,
                'message' => $e->getMessage(),
            ]);

            return $this->error(
                'Resume berhasil dibaca tetapi analisis ATS gagal.',
                500
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 7. Cek hasil ATS
        |--------------------------------------------------------------------------
        */

        if (
            isset($analysis['status'])
            && $analysis['status'] !== 200
        ) {

            return response()->json(
                $analysis,
                $analysis['status'] ?? 500
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 8. Buat structured_resume TANPA AI kedua
        |
        | Ini sengaja dibuat dari data yang sudah ada.
        | Tujuannya mencegah timeout PHP 30 detik.
        |--------------------------------------------------------------------------
        */

        $structuredResume = [

            'personal' => [
                'name' => null,
                'email' => null,
                'phone' => null,
                'location' => null,
                'linkedin' => null,
                'github' => null,
                'portfolio' => null,
            ],

            'profile' => null,

            'education' => [],

            'experience' => [],

            'projects' => [],

            'skills' => $analysis['skills'] ?? [],

            'organizations' => [],

            'certifications' => [],

            'achievements' => [],

            /*
            |--------------------------------------------------------------------------
            | Data mentah CV
            |--------------------------------------------------------------------------
            |
            | Cover Letter Controller dapat menggunakan raw_text ini
            | sebagai sumber utama informasi CV.
            |--------------------------------------------------------------------------
            */

            'raw_text' => $text,

            /*
            |--------------------------------------------------------------------------
            | Data ATS
            |--------------------------------------------------------------------------
            */

            'ats_score' => $analysis['ats_score'] ?? null,

            'career_level' => $analysis['career_level'] ?? null,

            'suggestions' => $analysis['suggestions'] ?? [],
        ];

        Log::info('STRUCTURED RESUME CREATED WITHOUT SECOND AI CALL', [
            'resume_id' => $resume->id,
            'text_length' => mb_strlen($text),
            'skills_count' => count(
                $structuredResume['skills'] ?? []
            ),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 9. Update database
        |--------------------------------------------------------------------------
        */

        try {

            $resume->update([

                'ats_score' => $analysis['ats_score'] ?? 0,

                'career_level' => $analysis['career_level']
                    ?? 'Unknown',

                'skills' => $analysis['skills']
                    ?? [],

                'suggestions' => $analysis['suggestions']
                    ?? [],

                'structured_resume' => $structuredResume,

            ]);

        } catch (\Throwable $e) {

            Log::error('RESUME DATABASE UPDATE ERROR', [
                'resume_id' => $resume->id,
                'message' => $e->getMessage(),
            ]);

            return $this->error(
                'Resume berhasil dianalisis tetapi gagal disimpan.',
                500
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Refresh
        |--------------------------------------------------------------------------
        */

        $resume->refresh();

        /*
        |--------------------------------------------------------------------------
        | 11. Log hasil akhir
        |--------------------------------------------------------------------------
        */

        Log::info('AFTER UPDATE', [
            'id' => $resume->id,
            'structured_resume_exists' =>
                ! empty($resume->structured_resume),
            'ats_score' => $resume->ats_score,
            'career_level' => $resume->career_level,
            'skills_count' => count($resume->skills ?? []),
        ]);

        /*
        |--------------------------------------------------------------------------
        | 12. Response
        |--------------------------------------------------------------------------
        */

        return $this->success(
            [
                'resume' => $resume,

                'analysis' => $analysis,

                'structured_resume' =>
                    $structuredResume,
            ],
            'Resume berhasil dianalisis dan disimpan.',
            201
        );
    }

    /**
     * GET ALL RESUMES
     */
    public function index()
    {
        $resumes = auth()
            ->user()
            ->resumes()
            ->latest()
            ->get();

        return $this->success(
            $resumes,
            'Daftar resume berhasil diambil'
        );
    }

    /**
     * GET DETAIL RESUME
     */
    public function show(Resume $resume)
    {
        if ($resume->user_id !== auth()->id()) {

            return $this->error(
                'Anda tidak memiliki akses ke resume ini',
                403
            );
        }

        return $this->success(
            $resume,
            'Detail resume berhasil diambil'
        );
    }

    /**
     * DELETE RESUME
     */
    public function destroy(Resume $resume)
    {
        if ($resume->user_id !== auth()->id()) {

            return $this->error(
                'Anda tidak memiliki akses',
                403
            );
        }

        if ($resume->file_path) {

            Storage::disk('public')
                ->delete($resume->file_path);
        }

        $resume->delete();

        return $this->success(
            null,
            'Resume berhasil dihapus'
        );
    }
}