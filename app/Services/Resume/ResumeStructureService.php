<?php

namespace App\Services\Resume;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class ResumeStructureService
{
    public function structure(string $resumeText): array
    {
        $apiKey = config('services.openrouter.api_key');

        if (empty($apiKey)) {
            throw new Exception('API Key OpenRouter tidak ditemukan.');
        }

        if (trim($resumeText) === '') {
            throw new Exception('Teks resume kosong.');
        }

        $systemPrompt = <<<SYSTEM
Anda adalah Resume Parser profesional.

Tugas Anda adalah mengubah teks resume menjadi data JSON terstruktur.

ATURAN WAJIB:

- Gunakan HANYA informasi yang terdapat pada resume.
- Jangan mengarang informasi.
- Jangan menyimpulkan informasi yang tidak tertulis.
- Jangan menggabungkan informasi dari bagian resume yang berbeda menjadi fakta baru.
- Jika suatu informasi tidak tersedia, gunakan null atau array kosong.
- Jangan mengubah nama, email, nomor telepon, perusahaan, institusi, posisi, teknologi, maupun nama proyek.
- Pertahankan informasi sesuai dengan isi resume.
- Jangan menambahkan pengalaman kerja yang tidak terdapat pada resume.
- Jangan menambahkan skill yang tidak terdapat pada resume.
- Jangan menambahkan pencapaian yang tidak terdapat pada resume.
- Jangan membuat interpretasi baru terhadap proyek.
- Hasil HARUS berupa JSON valid.
- Jangan menggunakan Markdown.
- Jangan menggunakan ```json.
- Jangan memberikan penjelasan apa pun di luar JSON.
SYSTEM;

        $userPrompt = <<<PROMPT
Ubah resume berikut menjadi JSON terstruktur.

RESUME:

{$resumeText}

Gunakan struktur JSON berikut:

{
    "personal": {
        "name": null,
        "email": null,
        "phone": null,
        "location": null,
        "linkedin": null,
        "github": null,
        "portfolio": null
    },
    "profile": null,
    "education": [],
    "experience": [],
    "projects": [],
    "skills": [],
    "organizations": [],
    "certifications": [],
    "achievements": []
}

ATURAN STRUKTUR:

1. personal:
Simpan informasi identitas yang benar-benar terdapat pada resume.

2. profile:
Simpan ringkasan profil jika terdapat pada resume.
Jangan membuat ringkasan baru.

3. education:
Gunakan format:
{
    "degree": null,
    "institution": null,
    "period": null,
    "description": null
}

4. experience:
Gunakan format:
{
    "position": null,
    "company": null,
    "period": null,
    "description": null
}

5. projects:
Gunakan format:
{
    "name": null,
    "description": null,
    "technologies": [],
    "period": null
}

6. skills:
Masukkan hanya skill yang tertulis pada resume.

7. organizations:
Gunakan format:
{
    "name": null,
    "position": null,
    "period": null,
    "description": null
}

8. certifications:
Gunakan format:
{
    "name": null,
    "issuer": null,
    "year": null
}

9. achievements:
Gunakan format:
{
    "title": null,
    "description": null,
    "year": null
}

Jika informasi tidak tersedia, gunakan null atau [] sesuai tipe datanya.

Hanya tampilkan JSON valid.
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
                        'max_tokens' => 1600,
                    ]
                );

                if (! $response->successful()) {

                    Log::warning('Resume structure model gagal.', [
                        'model' => $model,
                        'status' => $response->status(),
                    ]);

                    if (! in_array($response->status(), [
                        429,
                        500,
                        502,
                        503,
                        504
                    ])) {

                        throw new Exception(
                            $response->json()['error']['message']
                                ?? 'Provider mengembalikan error.'
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

                    Log::warning(
                        'Resume structure model mengembalikan content kosong.',
                        [
                            'model' => $model,
                        ]
                    );

                    continue;
                }

                // Bersihkan Markdown JSON jika model tetap mengirim ```json
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

                $structuredData = json_decode(
                    $content,
                    true
                );

                if (
                    json_last_error() !== JSON_ERROR_NONE
                    || ! is_array($structuredData)
                ) {

                    Log::warning(
                        'Resume structure menghasilkan JSON tidak valid.',
                        [
                            'model' => $model,
                            'json_error' => json_last_error_msg(),
                            'content' => $content,
                        ]
                    );

                    continue;
                }

                Log::info(
                    'Resume berhasil diubah menjadi struktur JSON.',
                    [
                        'model' => $model,
                    ]
                );

                return $structuredData;

            } catch (\Throwable $e) {

                Log::error(
                    'Resume structure model error.',
                    [
                        'model' => $model,
                        'message' => $e->getMessage(),
                    ]
                );

                continue;
            }
        }

        throw new Exception(
            'Semua model gagal mengubah resume menjadi JSON terstruktur.'
        );
    }
}