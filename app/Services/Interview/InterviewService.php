<?php

namespace App\Services\Interview;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InterviewService
{
    public function generate(
        string $structuredResume,
        string $position,
        string $difficulty,
        int $questionCount
    ): array {

        $apiKey = config('services.openrouter.api_key');

        if (empty($apiKey)) {
            throw new Exception('API Key OpenRouter tidak ditemukan.');
        }

        $systemPrompt = <<<SYSTEM
Anda adalah Senior Technical Interviewer, HR Recruiter, dan Software Engineering Mentor.

Tugas Anda adalah membuat simulasi interview berdasarkan resume yang diberikan.

Resume diberikan dalam bentuk JSON terstruktur.

ATURAN WAJIB

- Gunakan HANYA informasi pada JSON.
- Jangan mengarang pengalaman.
- Jangan mengarang skill.
- Jangan mengarang proyek.
- Jangan mengarang teknologi.
- Jangan melakukan inferensi.
- Jangan menambahkan fakta baru.
- Pertanyaan harus relevan dengan posisi yang dilamar.
- Tingkat kesulitan harus mengikuti parameter difficulty.
- Output HARUS berupa JSON valid.
- Jangan menggunakan Markdown.
- Jangan menggunakan ```json.
- Jangan memberikan penjelasan di luar JSON.

Format JSON:

{
  "questions":[
    {
      "question":"",
      "sample_answer":"",
      "tips":""
    }
  ]
}
SYSTEM;

        $userPrompt = <<<PROMPT
Buat simulasi interview.

Posisi:
{$position}

Difficulty:
{$difficulty}

Jumlah Pertanyaan:
{$questionCount}

==================================================
RESUME (JSON)
==================================================

{$structuredResume}

==================================================
ATURAN
==================================================

Gunakan hanya data JSON.

Jangan:

- menambahkan pengalaman
- menambahkan skill
- menambahkan teknologi
- menambahkan framework
- menambahkan sertifikasi
- menambahkan organisasi
- menambahkan pencapaian
- membuat asumsi

Setiap pertanyaan harus memiliki:

- question
- sample_answer
- tips

==================================================
OUTPUT
==================================================

{
  "questions":[
    {
      "question":"",
      "sample_answer":"",
      "tips":""
    }
  ]
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
                    'Authorization' => 'Bearer '.$apiKey,
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

                        'temperature' => 0.2,
                        'top_p' => 0.8,
                        'max_tokens' => 1800,
                    ]
                );

                Log::info('Interview Response', [
                    'model' => $model,
                    'status' => $response->status(),
                ]);

                if (! $response->successful()) {

                    Log::warning('Interview model gagal.', [
                        'model' => $model,
                        'status' => $response->status(),
                    ]);

                    if (! in_array($response->status(), [
                        429,
                        500,
                        502,
                        503,
                        504,
                    ])) {

                        throw new Exception(
                            data_get(
                                $response->json(),
                                'error.message',
                                'Provider Error'
                            )
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

                if ($content === '') {

                    Log::warning('Interview content kosong.', [
                        'model' => $model,
                    ]);

                    continue;
                }

                // Bersihkan markdown
                $content = preg_replace('/^```json\s*/i', '', $content);
                $content = preg_replace('/^```\s*/', '', $content);
                $content = preg_replace('/\s*```$/', '', $content);

                $content = trim($content);

                // Ambil JSON saja jika model menambahkan teks
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

                    Log::warning('Interview JSON tidak valid.', [
                        'model' => $model,
                        'error' => json_last_error_msg(),
                        'content' => $content,
                    ]);

                    continue;
                }

                if (
                    ! isset($result['questions']) ||
                    ! is_array($result['questions'])
                ) {

                    Log::warning('Interview JSON tidak memiliki questions.', [
                        'model' => $model,
                    ]);

                    continue;
                }

                Log::info('Interview berhasil dibuat.', [
                    'model' => $model,
                    'questions' => count($result['questions']),
                ]);

                return $result;

            } catch (\Throwable $e) {

                Log::error('Interview Error', [
                    'model' => $model,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }
        }

        throw new Exception(
            'Semua model OpenRouter sedang sibuk atau tidak tersedia. Silakan coba beberapa menit lagi.'
        );
    }
}