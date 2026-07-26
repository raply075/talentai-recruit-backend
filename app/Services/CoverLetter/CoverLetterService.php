<?php

namespace App\Services\CoverLetter;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CoverLetterService
{
    public function generate(
        string $structuredResume,
        string $company,
        string $position,
        string $tone = 'professional'
    ): string {

        $apiKey = config('services.openrouter.api_key');

        if (empty($apiKey)) {
            throw new Exception('API Key OpenRouter tidak ditemukan.');
        }

        $systemPrompt = <<<SYSTEM
Anda adalah HR Recruiter Senior, Career Coach, dan Talent Acquisition Specialist di Indonesia.

Tugas Anda adalah membuat Cover Letter profesional berdasarkan resume yang diberikan.

RESUME YANG DIBERIKAN BERBENTUK JSON TERSTRUKTUR.

ATURAN WAJIB

- Gunakan HANYA data yang terdapat pada JSON.
- Jangan menggunakan informasi di luar JSON.
- Jangan mengarang informasi.
- Jangan melakukan inferensi.
- Jangan melakukan asumsi.
- Jangan menggabungkan dua informasi menjadi fakta baru.
- Jika suatu field kosong atau bernilai null, abaikan.
- Jangan menambahkan pengalaman kerja.
- Jangan menambahkan proyek.
- Jangan menambahkan organisasi.
- Jangan menambahkan sertifikasi.
- Jangan menambahkan pencapaian.
- Jangan menambahkan skill.
- Jangan menambahkan teknologi.
- Jangan menambahkan soft skill.
- Jangan menambahkan hard skill.
- Jangan membuat klaim yang tidak terdapat pada JSON.
- Gunakan Bahasa Indonesia yang formal, natural, profesional, dan mudah dibaca.
- Jika pelamar merupakan mahasiswa atau fresh graduate, gunakan pengalaman proyek, organisasi, pendidikan, penelitian, atau sertifikasi sebagai nilai jual.
- Hasil harus siap dikirim sebagai surat lamaran kerja.
SYSTEM;

        $userPrompt = <<<PROMPT
Buat Cover Letter berdasarkan data berikut.

Perusahaan:
{$company}

Posisi:
{$position}

Gaya Penulisan:
{$tone}

==================================================
DATA RESUME TERSTRUKTUR (JSON)
==================================================

{$structuredResume}

==================================================
ATURAN
==================================================

Gunakan HANYA data JSON di atas.

Jangan:

- membuat asumsi baru
- membuat interpretasi baru
- menambahkan teknologi
- menambahkan metodologi
- menambahkan framework
- menambahkan arsitektur
- menambahkan pengalaman
- menambahkan pencapaian
- menambahkan skill

Apabila field profile tersedia,
gunakan hanya sebagai informasi pendukung.
Jangan mengambil fakta baru dari profile apabila fakta tersebut tidak muncul pada field lain.

Saat menjelaskan proyek:

Gunakan hanya:

- name
- description
- technologies
- period

Jangan:

- menjelaskan proses pengembangan
- menyebut MVC
- menyebut Clean Architecture
- menyebut SOLID
- menyebut Design Pattern
- menyebut Agile
- menyebut Scrum
- menyebut CI/CD
- menyebut Docker
- menyebut Kubernetes
- menyebut Microservices

kecuali benar-benar tertulis pada JSON.

Gunakan hanya skill yang terdapat pada array skills.

Pilih maksimal 2 proyek atau pengalaman yang PALING relevan dengan posisi {$position}.

Jangan menjelaskan seluruh isi resume.

Parafrase informasi agar natural.

Panjang sekitar 250–350 kata.

Gunakan personal.name sebagai nama pelamar.

==================================================
FORMAT
==================================================

Kepada Yth.
HRD {$company}

Perihal: Lamaran Posisi {$position}

Dengan hormat,

Paragraf 1
Perkenalkan diri dan alasan melamar.

Paragraf 2
Jelaskan maksimal dua pengalaman atau proyek yang paling relevan.

Paragraf 3
Jelaskan kemampuan berdasarkan field skills tanpa menambahkan kemampuan baru.

Paragraf 4
Sampaikan harapan mengikuti proses seleksi.

Penutup:

Hormat saya,

gunakan personal.name.

==================================================
OUTPUT
==================================================

Hanya tampilkan isi Cover Letter.

Jangan menggunakan Markdown.

Jangan menggunakan bullet.

Jangan menggunakan emoji.
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

                        'temperature' => 0.2,
                        'top_p' => 0.8,
                        'frequency_penalty' => 0.4,
                        'presence_penalty' => 0.0,
                        'max_tokens' => 1000,
                    ]
                );

                if (! $response->successful()) {

                    Log::warning('Model gagal.', [
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
                            data_get(
                                $response->json(),
                                'error.message',
                                'Provider mengembalikan error.'
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

                    Log::warning(
                        'Model mengembalikan content kosong.',
                        [
                            'model' => $model,
                        ]
                    );

                    continue;
                }

                $content = preg_replace("/\n{3,}/", "\n\n", $content);
                $content = trim($content);

                $finishReason = data_get(
                    $response->json(),
                    'choices.0.finish_reason',
                    ''
                );

                if (
                    $finishReason === 'stop'
                    && mb_strlen($content) >= 300
                    && str_contains($content, 'Hormat saya')
                ) {

                    Log::info(
                        'Cover Letter berhasil dibuat.',
                        [
                            'model' => $model,
                            'characters' => mb_strlen($content),
                            'finish_reason' => $finishReason,
                        ]
                    );

                    return $content;
                }

                Log::warning(
                    'Output tidak valid.',
                    [
                        'model' => $model,
                        'finish_reason' => $finishReason,
                        'characters' => mb_strlen($content),
                    ]
                );

            } catch (\Throwable $e) {

                Log::error(
                    'Model error.',
                    [
                        'model' => $model,
                        'message' => $e->getMessage(),
                    ]
                );

                continue;
            }
        }

        throw new Exception(
            'Semua model OpenRouter sedang sibuk atau tidak tersedia. Silakan coba beberapa menit lagi.'
        );
    }
}