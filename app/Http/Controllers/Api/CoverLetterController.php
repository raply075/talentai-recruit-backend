<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CoverLetterRequest;
use App\Models\CoverLetter;
use App\Models\Resume;
use App\Services\CoverLetter\CoverLetterService;
use App\Services\Resume\ResumeParserService;
use App\Services\Resume\ResumeStructureService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CoverLetterController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResumeParserService $parser,
        protected ResumeStructureService $structureService,
        protected CoverLetterService $generator
    ) {}

    public function generate(CoverLetterRequest $request)
    {
        // 1. Cari resume milik user yang sedang login
        $resume = Resume::where('user_id', auth()->id())
            ->findOrFail($request->resume_id);

        // 2. Lokasi file PDF resume
        $fullPath = storage_path(
            'app/public/' . $resume->file_path
        );

        // 3. Ambil teks dari PDF
        $resumeText = $this->parser->parse($fullPath);

        // 4. Pastikan teks resume tidak kosong
        if (trim($resumeText) === '') {
            return $this->error(
                'Teks resume tidak dapat dibaca atau kosong.',
                422
            );
        }

        // 5. Ubah teks resume menjadi JSON terstruktur
        $structuredResume = $this->structureService->structure(
            $resumeText
        );

        // 6. Ubah JSON terstruktur menjadi string
        $structuredResumeJson = json_encode(
            $structuredResume,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        // 7. Kirim JSON terstruktur ke AI
        \Log::info('Structured Resume JSON', [
    'json' => $structuredResumeJson
]);
        $coverLetter = $this->generator->generate(
            $structuredResumeJson,
            $request->company,
            $request->position,
            $request->tone ?? 'professional'
        );

        // 8. Simpan Cover Letter ke database
        $letter = CoverLetter::create([
            'user_id' => auth()->id(),
            'resume_id' => $resume->id,
            'company' => $request->company,
            'position' => $request->position,
            'tone' => $request->tone ?? 'professional',
            'content' => $coverLetter,
        ]);

        // 9. Kembalikan hasil
        return $this->success(
            $letter,
            'Cover letter berhasil dibuat'
        );
    }
    public function structureResume(Request $request)
{
    // Cari resume milik user yang sedang login
    $resume = Resume::where('user_id', auth()->id())
        ->findOrFail($request->resume_id);

    // Lokasi file PDF resume
    $fullPath = storage_path(
        'app/public/' . $resume->file_path
    );

    // Ambil teks dari PDF
    $resumeText = $this->parser->parse($fullPath);

    // Pastikan teks resume tidak kosong
    if (trim($resumeText) === '') {
        return $this->error(
            'Teks resume tidak dapat dibaca atau kosong.',
            422
        );
    }

    // Ubah teks resume menjadi JSON terstruktur
    $structuredResume = $this->structureService->structure(
        $resumeText
    );

    // Tampilkan hasil JSON terstruktur
    return $this->success(
        $structuredResume,
        'Resume berhasil diubah menjadi struktur JSON'
    );
}
}