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

    /*
    |--------------------------------------------------------------------------
    | UPLOAD RESUME
    |--------------------------------------------------------------------------
    */

    public function upload(ResumeUploadRequest $request)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. AMBIL FILE
        |--------------------------------------------------------------------------
        */

        $file = $request->file('resume');

        /*
        |--------------------------------------------------------------------------
        | 2. SIMPAN FILE
        |--------------------------------------------------------------------------
        */

        $path = $file->store(
            'resumes',
            'public'
        );

        /*
        |--------------------------------------------------------------------------
        | 3. BUAT DATA RESUME AWAL
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
        | 4. FULL PATH FILE
        |--------------------------------------------------------------------------
        */

        $fullPath = storage_path(
            'app/public/' . $path
        );

        /*
        |--------------------------------------------------------------------------
        | 5. PARSE RESUME
        |--------------------------------------------------------------------------
        */

        try {

            $text = $this->parserService->parse(
                $fullPath
            );

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
        | 6. CEK HASIL PARSING
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
        | 7. ANALISIS ATS
        |--------------------------------------------------------------------------
        */

        $analysis = null;
        $analysisError = null;

        try {

            $analysis = $this->analysisService->analyze(
                $text
            );

            Log::info('CONTROLLER ANALYSIS', [
                'resume_id' => $resume->id,
                'ats_score' => $analysis['ats_score'] ?? null,
                'career_level' => $analysis['career_level'] ?? null,
                'skills_count' => count(
                    $analysis['skills'] ?? []
                ),
            ]);

        } catch (\Throwable $e) {

            $analysisError = $e->getMessage();

            Log::error('ATS ANALYSIS ERROR', [
                'resume_id' => $resume->id,
                'message' => $analysisError,
            ]);

            /*
            |--------------------------------------------------------------------------
            | JANGAN HENTIKAN PROSES
            |--------------------------------------------------------------------------
            |
            | Resume tetap disimpan.
            | Kita tetap mencoba membuat struktur resume.
            |
            */

            $analysis = [
                'ats_score' => null,
                'career_level' => null,
                'skills' => [],
                'suggestions' => [
                    'Analisis ATS belum tersedia: '
                    . $analysisError,
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 8. STRUKTUR RESUME
        |--------------------------------------------------------------------------
        */

        $structuredResume = null;
        $structureError = null;

        try {

            Log::info(
                'START RESUME STRUCTURE',
                [
                    'resume_id' => $resume->id,
                    'text_length' => mb_strlen($text),
                ]
            );

            $structuredResume =
                $this->structureService->structure(
                    $text
                );

            Log::info(
                'STRUCTURED RESUME RESULT',
                [
                    'resume_id' => $resume->id,
                    'has_data' => ! empty(
                        $structuredResume
                    ),
                    'projects_count' => count(
                        $structuredResume['projects'] ?? []
                    ),
                    'skills_count' => count(
                        $structuredResume['skills'] ?? []
                    ),
                ]
            );

        } catch (\Throwable $e) {

            $structureError = $e->getMessage();

            Log::error(
                'RESUME STRUCTURE ERROR',
                [
                    'resume_id' => $resume->id,
                    'message' => $structureError,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | STRUKTUR GAGAL
            |--------------------------------------------------------------------------
            */

            $structuredResume = null;
        }

        /*
        |--------------------------------------------------------------------------
        | 9. SIMPAN HASIL KE DATABASE
        |--------------------------------------------------------------------------
        */

        $resume->update([

            'ats_score' =>
                $analysis['ats_score'] ?? null,

            'career_level' =>
                $analysis['career_level'] ?? null,

            'skills' =>
                $analysis['skills'] ?? [],

            'suggestions' =>
                $analysis['suggestions'] ?? [],

            'structured_resume' =>
                $structuredResume,

        ]);

        /*
        |--------------------------------------------------------------------------
        | 10. REFRESH DATA
        |--------------------------------------------------------------------------
        */

        $resume->refresh();

        /*
        |--------------------------------------------------------------------------
        | 11. LOG HASIL AKHIR
        |--------------------------------------------------------------------------
        */

        Log::info(
            'AFTER UPDATE',
            [
                'resume_id' => $resume->id,
                'ats_score' => $resume->ats_score,
                'career_level' => $resume->career_level,
                'skills_count' => count(
                    $resume->skills ?? []
                ),
                'structured_resume_exists' =>
                    ! empty(
                        $resume->structured_resume
                    ),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 12. RESPONSE
        |--------------------------------------------------------------------------
        */

        return $this->success(
            [
                'resume' => $resume,

                'analysis' => $analysis,

                'structured_resume' =>
                    $structuredResume,

                'analysis_error' =>
                    $analysisError,

                'structure_error' =>
                    $structureError,
            ],
            'Resume berhasil diproses.',
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
        if (
            $resume->user_id !== auth()->id()
        ) {

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
        if (
            $resume->user_id !== auth()->id()
        ) {

            return $this->error(
                'Anda tidak memiliki akses',
                403
            );
        }

        Storage::disk('public')
            ->delete(
                $resume->file_path
            );

        $resume->delete();

        return $this->success(
            null,
            'Resume berhasil dihapus'
        );
    }
}