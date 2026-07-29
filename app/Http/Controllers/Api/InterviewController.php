<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InterviewRequest;
use App\Models\Resume;
use App\Services\Interview\InterviewService;
use App\Services\Resume\ResumeParserService;
use App\Services\Resume\ResumeStructureService;
use App\Traits\ApiResponse;

class InterviewController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResumeParserService $parser,
        protected ResumeStructureService $structureService,
        protected InterviewService $interviewService
    ) {}

    public function generate(InterviewRequest $request)
    {
        // 1. Cari resume milik user yang sedang login
        $resume = Resume::where('user_id', auth()->id())
            ->findOrFail($request->resume_id);

        // 2. Lokasi file PDF
        $fullPath = storage_path(
            'app/public/' . $resume->file_path
        );

        // 3. Parse PDF menjadi text
        $resumeText = $this->parser->parse($fullPath);

        // 4. Pastikan resume tidak kosong
        if (trim($resumeText) === '') {
            return $this->error(
                'Teks resume tidak dapat dibaca atau kosong.',
                422
            );
        }

        // 5. Ubah menjadi JSON terstruktur
        $structuredResume = $this->structureService->structure(
            $resumeText
        );

        // 6. Encode JSON untuk AI
        $structuredResumeJson = json_encode(
            $structuredResume,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        // 7. Generate Interview
        $questions = $this->interviewService->generate(
            $structuredResumeJson,
            $request->position,
            $request->difficulty,
            $request->question_count
        );

        // 8. Return response
        return $this->success(
            $questions,
            'Interview berhasil dibuat'
        );
    }
}