<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResumeUploadRequest;
use App\Models\Resume;
use App\Services\Resume\ResumeAnalysisService;
use App\Services\Resume\ResumeParserService;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

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

    public function upload(ResumeUploadRequest $request)
    {
        try {
            $file = $request->file('resume');

            // Simpan file
            $path = $file->store('resumes', 'public');

            // Simpan data awal resume
            $resume = Resume::create([
                'user_id'       => $request->user()->id,
                'title'         => pathinfo(
                    $file->getClientOriginalName(),
                    PATHINFO_FILENAME
                ),
                'original_name' => $file->getClientOriginalName(),
                'file_path'     => $path,
                'file_size'     => $file->getSize(),
                'ats_score'     => null,
                'career_level'  => null,
                'skills'        => [],
                'suggestions'   => [],
            ]);

            // Path file lengkap
            $fullPath = storage_path('app/public/' . $path);

            // Parse PDF menjadi text
            $text = $this->parserService->parse($fullPath);

            Log::info('RESUME PARSED', [
                'resume_id' => $resume->id,
                'text_length' => strlen($text),
            ]);

            // Analisis ATS menggunakan AI
            $analysis = $this->analysisService->analyze($text);

            Log::info('CONTROLLER ANALYSIS', $analysis);

            // Jika AI Analysis error
            if (isset($analysis['status'])) {
                return response()->json($analysis);
            }

            // Struktur resume sementara dimatikan
            // Karena proses AI kedua menyebabkan timeout 30 detik
            $structuredResume = null;

            // Simpan hasil analisis AI
            $resume->update([
                'ats_score' => $analysis['ats_score'] ?? 0,
                'career_level' => $analysis['career_level'] ?? 'Unknown',
                'skills' => $analysis['skills'] ?? [],
                'suggestions' => $analysis['suggestions'] ?? [],
                'structured_resume' => $structuredResume,
            ]);

            // Refresh data resume
            $resume->refresh();

            Log::info('AFTER UPDATE', $resume->toArray());

            // Response berhasil
            return $this->success([
                'resume' => $resume,
                'analysis' => $analysis,
                'structured_resume' => $structuredResume,
            ], 'Resume berhasil dianalisis', 201);

        } catch (\Throwable $e) {

            Log::error('RESUME UPLOAD ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->error(
                'Gagal memproses resume: ' . $e->getMessage(),
                500
            );
        }
    }

    public function index()
    {
        $resumes = auth()->user()
            ->resumes()
            ->latest()
            ->get();

        return $this->success(
            $resumes,
            'Daftar resume berhasil diambil'
        );
    }

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

    public function destroy(Resume $resume)
    {
        if ($resume->user_id !== auth()->id()) {
            return $this->error(
                'Anda tidak memiliki akses',
                403
            );
        }

        Storage::disk('public')->delete($resume->file_path);

        $resume->delete();

        return $this->success(
            null,
            'Resume berhasil dihapus'
        );
    }
}