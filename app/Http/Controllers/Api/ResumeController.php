<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResumeUploadRequest;
use App\Models\Resume;
use App\Services\Resume\ResumeAnalysisService;
use App\Services\Resume\ResumeParserService;
use App\Services\Resume\ResumeStructureService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ResumeController extends Controller
{
    use ApiResponse;

    protected ResumeParserService $parserService;
    protected ResumeAnalysisService $analysisService;
    protected ResumeStructureService $structureService;

    public function __construct(
        ResumeParserService $parserService,
        ResumeAnalysisService $analysisService,
        ResumeStructureService $structureService
    ) {
        $this->parserService = $parserService;
        $this->analysisService = $analysisService;
        $this->structureService = $structureService;
    }

    public function upload(ResumeUploadRequest $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Ambil file resume
        |--------------------------------------------------------------------------
        */

        $file = $request->file('resume');

        /*
        |--------------------------------------------------------------------------
        | 2. Simpan file resume
        |--------------------------------------------------------------------------
        */

        $path = $file->store('resumes', 'public');

        /*
        |--------------------------------------------------------------------------
        | 3. Simpan data awal resume
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
        | 4. Lokasi file PDF
        |--------------------------------------------------------------------------
        */

        $fullPath = storage_path(
            'app/public/' . $path
        );

        /*
        |--------------------------------------------------------------------------
        | 5. Parse PDF menjadi text
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
        | 6. Pastikan hasil parsing tidak kosong
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
        | 7. Analisis ATS
        |--------------------------------------------------------------------------
        */

        try {

            $analysis = $this->analysisService->analyze($text);

            Log::info(
                'CONTROLLER ANALYSIS',
                $analysis
            );

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
        | 8. Cek jika AI ATS mengembalikan error
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
        | 9. Struktur resume menggunakan AI
        |--------------------------------------------------------------------------
        */

        $structuredResume = null;

        try {

            Log::info(
                'START RESUME STRUCTURE',
                [
                    'resume_id' => $resume->id,
                    'text_length' => mb_strlen($text),
                ]
            );

            $structuredResume = $this->structureService->structure(
                $text
            );

            Log::info(
                'STRUCTURED RESUME RESULT',
                [
                    'resume_id' => $resume->id,
                    'has_data' => ! empty($structuredResume),
                    'projects_count' => count(
                        $structuredResume['projects'] ?? []
                    ),
                    'skills_count' => count(
                        $structuredResume['skills'] ?? []
                    ),
                ]
            );

        } catch (\Throwable $e) {

            Log::error(
                'RESUME STRUCTURE ERROR',
                [
                    'resume_id' => $resume->id,
                    'message' => $e->getMessage(),
                ]
            );

            return $this->error(
                'Resume berhasil dianalisis ATS tetapi gagal dibuat menjadi struktur data untuk AI Cover Letter.',
                500
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 10. Simpan semua hasil analisis
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | 11. Refresh data dari database
        |--------------------------------------------------------------------------
        */

        $resume->refresh();

        /*
        |--------------------------------------------------------------------------
        | 12. Log hasil akhir
        |--------------------------------------------------------------------------
        */

        Log::info(
            'AFTER UPDATE',
            $resume->toArray()
        );

        /*
        |--------------------------------------------------------------------------
        | 13. Return response
        |--------------------------------------------------------------------------
        */

        return $this->success(
            [
                'resume' => $resume,

                'analysis' => $analysis,

                'structured_resume' => $structuredResume,
            ],
            'Resume berhasil dianalisis dan distrukturkan',
            201
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GET ALL RESUMES
    |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | GET DETAIL RESUME
    |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | DELETE RESUME
    |--------------------------------------------------------------------------
    */

    public function destroy(Resume $resume)
    {
        if ($resume->user_id !== auth()->id()) {

            return $this->error(
                'Anda tidak memiliki akses',
                403
            );
        }

        Storage::disk('public')
            ->delete($resume->file_path);

        $resume->delete();

        return $this->success(
            null,
            'Resume berhasil dihapus'
        );
    }
}