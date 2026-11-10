<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AiAnalyzer
{
    /**
     * Analisis foto dan deskripsi menggunakan Gemini Multimodal.
     *
     * @return array{relevan:bool,kepercayaan:int,kategori:string,tingkat_keparahan:int,ringkasan:string,alasan:string}|null
     */
    public function analyze(string $fotoPath, ?string $deskripsi): ?array
    {
        $kategori = config('domain.kategori');
        $disk = Storage::disk('public');

        $body = [
            'contents' => [[
                'parts' => [
                    ['text' => config('domain.prompt')."\nDeskripsi warga: ".($deskripsi ?: '-')],
                    ['inline_data' => [
                        'mime_type' => $disk->mimeType($fotoPath),
                        'data' => base64_encode($disk->get($fotoPath)),
                    ]],
                ],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                // Deskripsi properti ditaruh DI SINI (petunjuk untuk model), bukan di Validator.
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'relevan' => [
                            'type' => 'BOOLEAN',
                            'description' => 'true jika foto menunjukkan sampah dan sesuai deskripsi warga.',
                        ],
                        'kepercayaan' => [
                            'type' => 'INTEGER',
                            'description' => 'Keyakinan 0-100 bahwa nilai relevan benar. Foto sampah yang jelas = 80-100. '
                                .'Nilai rendah hanya jika foto gelap, buram, atau ambigu. Ini BUKAN skor keparahan.',
                        ],
                        'kategori' => ['type' => 'STRING', 'enum' => $kategori],
                        'tingkat_keparahan' => ['type' => 'INTEGER'],
                        'ringkasan' => ['type' => 'STRING'],
                        'alasan' => ['type' => 'STRING'],
                    ],
                    'required' => ['relevan', 'kepercayaan', 'kategori', 'tingkat_keparahan', 'ringkasan', 'alasan'],
                ],
            ],
        ];

        // Ambil daftar model berurutan (dari GEMINI_MODELS atau GEMINI_MODEL)
        $rawModels = config('services.gemini.models');
        if (empty($rawModels)) {
            $rawModels = config('services.gemini.model', 'gemini-3.8-flash');
        }

        $models = is_array($rawModels)
            ? $rawModels
            : array_values(array_filter(array_map('trim', explode(',', $rawModels))));

        // Coba model secara berurutan; jika 429 atau 503, langsung beralih ke model cadangan berikutnya
        foreach ($models as $model) {
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';

            try {
                $res = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                    ->timeout(15)
                    ->post($url, $body);

                // 404 (tidak ditemukan), 429 (kuota habis), 500/502 (server error), 503 (layanan sibuk):
                // langsung coba model berikutnya tanpa tidur lama
                if (in_array($res->status(), [404, 429, 500, 502, 503])) {
                    Log::warning("AI model {$model} tidak dapat melayani (HTTP {$res->status()}), mencoba model cadangan berikutnya.", [
                        'model' => $model,
                        'status' => $res->status(),
                    ]);
                    continue;
                }

                $json = json_decode($res->json('candidates.0.content.parts.0.text', ''), true);

                $v = Validator::make($json ?? [], [
                    'relevan' => ['required', 'boolean'],
                    'kepercayaan' => ['required', 'integer', 'between:0,100'],
                    'kategori' => ['required', 'in:'.implode(',', $kategori)],
                    'tingkat_keparahan' => ['required', 'integer', 'between:1,5'],
                    'ringkasan' => ['required', 'string'],
                    'alasan' => ['required', 'string'],
                ]);

                if ($res->ok() && ! $v->fails()) {
                    Log::info("AI analisis berhasil menggunakan model {$model}.", ['model' => $model]);

                    return $v->validated();
                }

                Log::warning("AI respons dari model {$model} tidak dipakai atau validasi gagal", [
                    'model' => $model,
                    'status' => $res->status(),
                    'body' => mb_substr($res->body(), 0, 500),
                    'validasi' => $res->ok() ? $v->errors()->all() : [],
                ]);
            } catch (\Throwable $e) {
                Log::warning("AI analyze gagal/timeout pada model {$model}: ".$e->getMessage(), [
                    'model' => $model,
                ]);
                continue;
            }
        }

        Log::warning('Semua model AI cadangan gagal memproses laporan.');

        return null;
    }
}