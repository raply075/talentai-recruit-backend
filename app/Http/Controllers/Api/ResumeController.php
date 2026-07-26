<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResumeUploadRequest;
use App\Models\Resume;
use App\Services\Resume\ResumeAnalysisService;
use App\Services\Resume\ResumeParserService;
use App\Services\Resume\ResumeStructureService;
use App\Traits\ApiResponse;
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
        $file = $request->file('resume');

        // Simpan file
        $path = $file->store('resumes', 'public');

        // Simpan data awal
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

        // Parse PDF menjadi text
        $fullPath = storage_path('app/public/' . $path);

        $text = $this->parserService->parse($fullPath);

        // Analisis AI yang sudah ada
        $analysis = $this->analysisService->analyze($text);

        // Jika AI Analysis error
        if (isset($analysis['status'])) {
            return response()->json($analysis);
        }

        // Struktur resume menggunakan AI
        $structuredResume = $this->structureService->structure($text);

        // Simpan hasil analisis AI
        $resume->update([
            'ats_score'    => $analysis['ats_score'] ?? 0,
            'career_level' => $analysis['career_level'] ?? 'Unknown',
            'skills'       => $analysis['skills'] ?? [],
            'suggestions'  => $analysis['suggestions'] ?? [],
        ]);

        $resume->refresh();

        return $this->success([
            'resume' => $resume,

            'analysis' => $analysis,

            'structured_resume' => $structuredResume,

        ], 'Resume berhasil dianalisis dan distrukturkan', 201);
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