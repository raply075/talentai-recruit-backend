<?php

namespace App\Services\Resume;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResumeAnalysisService
{
    public function analyze(string $resumeText): array
    {
        $apiKey = config('services.openrouter.api_key');

        if (empty($apiKey)) {
            throw new Exception('API Key OpenRouter tidak ditemukan.');
        }

        if (trim($resumeText) === '') {
            throw new Exception('Resume kosong.');
        }

        // Batasi panjang resume
        $resumeText = mb_substr($resumeText, 0, 6000);

        $systemPrompt = <<<SYSTEM
Anda adalah Applicant Tracking System (ATS) profesional.

Tugas Anda adalah mengevaluasi resume.

ATURAN WAJIB:

- Balas HANYA JSON valid.
- Jangan menggunakan markdown.
- Jangan menggunakan ```json.
- Jangan memberikan penjelasan.
- ats_score berupa angka 0-100.
- career_level hanya salah satu:
  Intern
  Junior
  Mid
  Senior
  Lead
- skills berupa array string.
- suggestions berupa array string.
SYSTEM;

        $userPrompt = <<<PROMPT
Analisis resume berikut.

RESUME:

{$resumeText}

Kembalikan JSON berikut:

{
    "ats_score": 0,
    "career_level": "Junior",
    "skills": [],
    "suggestions": []
}
PROMPT;

        $models = [
            'google/gemma-4-26b-a4b-it:free',
            'openai/gpt-oss-20b:free',
            'inclusionai/ling-3.0-flash:free',
            'poolside/laguna-m.1:free',
        ];

        foreach ($models as $model) {

            try {

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'HTTP-Referer' => config('app.url'),
                    'X-Title' => config('app.name'),
                ])
                ->timeout(120)
                ->post(
                    'https://openrouter.ai/api/v1/chat/completions',
                    [
                        'model' => $model,

                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt,
                            ],
                            [
                                'role' => 'user',
                                'content' => $userPrompt,
                            ],
                        ],

                        'temperature' => 0.1,
                        'top_p' => 0.8,
                        'max_tokens' => 800,
                    ]
                );

                Log::info('ATS RESPONSE', [
                    'model' => $model,
                    'status' => $response->status(),
                ]);

                if (! $response->successful()) {

                    Log::warning('ATS model gagal.', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    if (! in_array($response->status(), [
                        429,
                        500,
                        502,
                        503,
                        504,
                    ])) {

                        throw new Exception(
                            $response->json()['error']['message']
                                ?? 'Provider Error'
                        );
                    }

                    continue;
                }

                $content = trim(
                    data_get(
                        $response->json(),
                        'choices.0.message.content',
                        ''
                    )
                );

                Log::info('ATS RAW', [
                    'model' => $model,
                    'content' => $content,
                ]);

                if ($content === '') {
                    continue;
                }

                // Bersihkan markdown jika ada
                $content = preg_replace('/^```json\s*/i', '', $content);
                $content = preg_replace('/^```\s*/', '', $content);
                $content = preg_replace('/\s*```$/', '', $content);

                $content = trim($content);

                // Ambil JSON jika model menambahkan teks
                $start = strpos($content, '{');
                $end = strrpos($content, '}');

                if ($start !== false && $end !== false) {
                    $content = substr(
                        $content,
                        $start,
                        $end - $start + 1
                    );
                }

                $result = json_decode($content, true);

                if (
                    json_last_error() !== JSON_ERROR_NONE ||
                    ! is_array($result)
                ) {

                    Log::warning('ATS JSON tidak valid.', [
                        'model' => $model,
                        'json_error' => json_last_error_msg(),
                        'content' => $content,
                    ]);

                    continue;
                }

                Log::info('ATS berhasil.', [
                    'model' => $model,
                ]);

                $returnData = [
    'ats_score' => (int)($result['ats_score'] ?? 0),
    'career_level' => $result['career_level'] ?? 'Unknown',
    'skills' => is_array($result['skills'] ?? null)
        ? $result['skills']
        : [],
    'suggestions' => is_array($result['suggestions'] ?? null)
        ? $result['suggestions']
        : [],
];

Log::info('ATS RETURN', $returnData);

return $returnData;

            } catch (\Throwable $e) {

                Log::error('ATS Error', [
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return [
            'ats_score' => 0,
            'career_level' => 'Unknown',
            'skills' => [],
            'suggestions' => [
                'Semua model AI gagal menganalisis resume.',
            ],
        ];
    }
}