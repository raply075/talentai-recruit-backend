<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CoverLetterRequest;
use App\Models\CoverLetter;
use App\Models\Resume;
use App\Services\CoverLetter\CoverLetterService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CoverLetterController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CoverLetterService $generator
    ) {}

    /**
     * Generate Cover Letter berdasarkan resume yang sudah dianalisis.
     */
    public function generate(CoverLetterRequest $request)
    {
        // 1. Cari resume milik user yang sedang login
        $resume = Resume::where('user_id', auth()->id())
            ->findOrFail($request->resume_id);

        // 2. Pastikan resume sudah memiliki structured_resume
        if (empty($resume->structured_resume)) {
            return $this->error(
                'Data resume terstruktur belum tersedia. Silakan upload dan analisis resume terlebih dahulu.',
                422
            );
        }

        // 3. Ambil structured_resume dari database
        $structuredResume = $resume->structured_resume;

        // 4. Jika structured_resume masih berupa string JSON,
        //    decode terlebih dahulu
        if (is_string($structuredResume)) {
            $decoded = json_decode($structuredResume, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Structured Resume JSON tidak valid.', [
                    'resume_id' => $resume->id,
                    'json_error' => json_last_error_msg(),
                ]);

                return $this->error(
                    'Data resume terstruktur tidak valid.',
                    422
                );
            }

            $structuredResume = $decoded;
        }

        // 5. Ubah structured resume menjadi JSON string
        $structuredResumeJson = json_encode(
            $structuredResume,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        // 6. Log untuk debugging
        Log::info('Using Stored Structured Resume', [
            'resume_id' => $resume->id,
            'json_length' => strlen($structuredResumeJson),
        ]);

        // 7. Kirim langsung ke CoverLetterService
        //    Tidak perlu parse PDF lagi
        //    Tidak perlu structure AI lagi
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

        // 9. Kembalikan response
        return $this->success(
            $letter,
            'Cover letter berhasil dibuat'
        );
    }

    /**
     * Mengambil structured resume dari database.
     * Bisa digunakan untuk testing/debugging.
     */
    public function structureResume(Request $request)
    {
        // 1. Cari resume milik user yang sedang login
        $resume = Resume::where('user_id', auth()->id())
            ->findOrFail($request->resume_id);

        // 2. Pastikan structured_resume tersedia
        if (empty($resume->structured_resume)) {
            return $this->error(
                'Data resume terstruktur belum tersedia. Silakan upload dan analisis resume terlebih dahulu.',
                422
            );
        }

        // 3. Ambil data structured resume
        $structuredResume = $resume->structured_resume;

        // 4. Jika masih berupa JSON string, decode
        if (is_string($structuredResume)) {
            $decoded = json_decode($structuredResume, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->error(
                    'Data resume terstruktur tidak valid.',
                    422
                );
            }

            $structuredResume = $decoded;
        }

        // 5. Kembalikan data structured resume
        return $this->success(
            $structuredResume,
            'Data resume terstruktur berhasil diambil'
        );
    }
}