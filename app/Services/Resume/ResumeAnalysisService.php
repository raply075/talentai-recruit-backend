<?php

namespace App\Services\Resume;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ResumeAnalysisService
{
    public function analyze(string $text): array
    {
        $apiKey = config('services.openrouter.api_key');

        $prompt = <<<PROMPT
Anda adalah ATS (Applicant Tracking System).

Analisis CV berikut.

WAJIB membalas HANYA JSON VALID.

JANGAN gunakan markdown.
JANGAN gunakan ```json.
JANGAN tambahkan penjelasan.

Format yang WAJIB:

{
  "ats_score": 90,
  "career_level": "Senior",
  "skills": [
    "PHP",
    "Laravel",
    "React"
  ],
  "suggestions": [
    "Tambahkan sertifikasi",
    "Tambahkan link GitHub"
  ]
}

CV:

{$text}
PROMPT;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (! $response->successful()) {
            return [
                'status' => $response->status(),
                'response' => $response->json(),
            ];
        }

        $content = $response->json('choices.0.message.content', '');

        $content = str_replace([
            '```json',
            '```',
        ], '', $content);

        $content = trim($content);

        $result = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($result)) {

            Log::error('Resume AI JSON Error', [
                'json_error' => json_last_error_msg(),
                'content' => $content,
            ]);

            return [
                'ats_score' => 0,
                'career_level' => 'Unknown',
                'skills' => [],
                'suggestions' => [
                    'AI tidak mengembalikan JSON yang valid',
                ],
            ];
        }

        return [
            'ats_score' => (int) ($result['ats_score'] ?? 0),
            'career_level' => $result['career_level'] ?? 'Unknown',
            'skills' => is_array($result['skills'] ?? null)
                ? $result['skills']
                : [],
            'suggestions' => is_array($result['suggestions'] ?? null)
                ? $result['suggestions']
                : [],
        ];
    }
}