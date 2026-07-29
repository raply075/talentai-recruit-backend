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

        // Batasi panjang teks resume
        $resumeText = mb_substr($resumeText, 0, 6000);

        $systemPrompt = <<<SYSTEM
Anda adalah Applicant Tracking System (ATS) profesional.

Tugas Anda adalah mengevaluasi resume berdasarkan isi resume yang diberikan.

ATURAN WAJIB:

- Balas HANYA JSON valid.
- Jangan menggunakan Markdown.
- Jangan menggunakan ```json.
- Jangan memberikan penjelasan di luar JSON.
- ats_score harus berupa angka 0 sampai 100.
- career_level hanya boleh:
  Intern
  Junior
  Mid
  Senior
  Lead
- skills harus berupa array string.
- suggestions harus berupa array string.
- Gunakan hanya informasi yang terdapat dalam resume.
- Jangan mengarang pengalaman, skill, pendidikan, atau pencapaian.
SYSTEM;

        $userPrompt = <<<PROMPT
Analisis resume berikut.

RESUME:

{$resumeText}

Kembalikan JSON dengan struktur berikut:

{
    "ats_score": 0,
    "career_level": "Junior",
    "skills": [],
    "suggestions": []
}

PENTING:
- ats_score harus angka 0-100.
- career_level harus salah satu dari: Intern, Junior, Mid, Senior, Lead.
- skills harus berisi skill yang ditemukan dalam resume.
- suggestions berisi saran perbaikan resume.
- Hanya tampilkan satu objek JSON valid.
PROMPT;

        /*
        |--------------------------------------------------------------------------
        | MODEL OPENROUTER
        |--------------------------------------------------------------------------
        |
        | Model dicoba satu per satu.
        | Jika model terkena rate limit 429, lanjut ke model berikutnya.
        |
        */

        $models = [
            'google/gemma-4-26b-a4b-it:free',
            'openai/gpt-oss-20b:free',
            'inclusionai/ling-3.0-flash:free',
        ];

        $lastError = null;

        foreach ($models as $model) {

            try {

                Log::info('ATS AI START', [
                    'model' => $model,
                    'text_length' => mb_strlen($resumeText),
                ]);

                $response = Http::connectTimeout(10)
                    ->timeout(60)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'HTTP-Referer' => config('app.url'),
                        'X-Title' => config('app.name'),
                        'Content-Type' => 'application/json',
                    ])
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

                /*
                |--------------------------------------------------------------------------
                | RATE LIMIT
                |--------------------------------------------------------------------------
                */

                if ($response->status() === 429) {

                    $lastError = 'OpenRouter rate limit exceeded.';

                    Log::warning('ATS RATE LIMIT', [
                        'model' => $model,
                        'status' => 429,
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | SERVER ERROR
                |--------------------------------------------------------------------------
                */

                if (in_array($response->status(), [
                    500,
                    502,
                    503,
                    504,
                ])) {

                    $lastError = 'Provider AI sedang mengalami gangguan.';

                    Log::warning('ATS PROVIDER ERROR', [
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | ERROR LAIN
                |--------------------------------------------------------------------------
                */

                if (! $response->successful()) {

                    $message = data_get(
                        $response->json(),
                        'error.message',
                        'Provider AI mengembalikan error.'
                    );

                    $lastError = $message;

                    Log::error('ATS MODEL ERROR', [
                        'model' => $model,
                        'status' => $response->status(),
                        'message' => $message,
                    ]);

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | AMBIL CONTENT AI
                |--------------------------------------------------------------------------
                */

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

                    $lastError = 'AI mengembalikan response kosong.';

                    Log::warning('ATS EMPTY RESPONSE', [
                        'model' => $model,
                    ]);

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | BERSIHKAN MARKDOWN JSON
                |--------------------------------------------------------------------------
                */

                $content = preg_replace(
                    '/^```json\s*/i',
                    '',
                    $content
                );

                $content = preg_replace(
                    '/^```\s*/',
                    '',
                    $content
                );

                $content = preg_replace(
                    '/\s*```$/',
                    '',
                    $content
                );

                $content = trim($content);

                /*
                |--------------------------------------------------------------------------
                | AMBIL BAGIAN JSON SAJA
                |--------------------------------------------------------------------------
                */

                $start = strpos($content, '{');
                $end = strrpos($content, '}');

                if (
                    $start !== false &&
                    $end !== false &&
                    $end > $start
                ) {

                    $content = substr(
                        $content,
                        $start,
                        $end - $start + 1
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | DECODE JSON
                |--------------------------------------------------------------------------
                */

                $result = json_decode(
                    $content,
                    true
                );

                if (
                    json_last_error() !== JSON_ERROR_NONE
                    || ! is_array($result)
                ) {

                    $lastError = 'AI mengembalikan JSON tidak valid.';

                    Log::warning('ATS JSON INVALID', [
                        'model' => $model,
                        'json_error' => json_last_error_msg(),
                        'content' => $content,
                    ]);

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | VALIDASI CAREER LEVEL
                |--------------------------------------------------------------------------
                */

                $allowedCareerLevels = [
                    'Intern',
                    'Junior',
                    'Mid',
                    'Senior',
                    'Lead',
                ];

                $careerLevel = $result['career_level']
                    ?? 'Junior';

                if (! in_array(
                    $careerLevel,
                    $allowedCareerLevels,
                    true
                )) {
                    $careerLevel = 'Junior';
                }

                /*
                |--------------------------------------------------------------------------
                | NORMALISASI HASIL
                |--------------------------------------------------------------------------
                */

                $atsScore = (int) (
                    $result['ats_score']
                    ?? 0
                );

                $atsScore = max(
                    0,
                    min(
                        100,
                        $atsScore
                    )
                );

                $skills = is_array(
                    $result['skills'] ?? null
                )
                    ? array_values(
                        array_filter(
                            $result['skills']
                        )
                    )
                    : [];

                $suggestions = is_array(
                    $result['suggestions'] ?? null
                )
                    ? array_values(
                        array_filter(
                            $result['suggestions']
                        )
                    )
                    : [];

                $returnData = [
                    'ats_score' => $atsScore,
                    'career_level' => $careerLevel,
                    'skills' => $skills,
                    'suggestions' => $suggestions,
                ];

                Log::info('ATS SUCCESS', [
                    'model' => $model,
                    'ats_score' => $atsScore,
                    'career_level' => $careerLevel,
                    'skills_count' => count($skills),
                ]);

                return $returnData;

            } catch (\Throwable $e) {

                $lastError = $e->getMessage();

                Log::error('ATS EXCEPTION', [
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SEMUA MODEL GAGAL
        |--------------------------------------------------------------------------
        |
        | Jangan mengembalikan hasil ATS palsu.
        | Lempar exception agar Controller tahu bahwa AI gagal.
        |
        */

        Log::error('ALL ATS MODELS FAILED', [
            'last_error' => $lastError,
        ]);

        throw new Exception(
            $lastError
                ?? 'Semua model AI gagal menganalisis resume.'
        );
    }
}