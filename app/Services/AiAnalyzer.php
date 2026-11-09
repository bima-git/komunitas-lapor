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
     * Mengembalikan data relevansi, kepercayaan, kategori, dan keparahan.
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
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'relevan' => ['type' => 'BOOLEAN'],
                        'kepercayaan' => ['type' => 'INTEGER'],
                        'kategori' => ['type' => 'STRING', 'enum' => $kategori],
                        'tingkat_keparahan' => ['type' => 'INTEGER'],
                        'ringkasan' => ['type' => 'STRING'],
                        'alasan' => ['type' => 'STRING'],
                    ],
                    'required' => ['relevan', 'kepercayaan', 'kategori', 'tingkat_keparahan', 'ringkasan', 'alasan'],
                ],
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            .config('services.gemini.model').':generateContent';

        // Maksimal 2 percobaan; jika tetap gagal, pemanggil memakai fallback.
        for ($i = 0; $i < 2; $i++) {
            try {
                $res = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                    ->timeout(30)->post($url, $body);

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
                    return $v->validated();
                }
            } catch (\Throwable $e) {
                Log::warning('AI analyze gagal: '.$e->getMessage());
            }
        }

        return null;
    }
}
