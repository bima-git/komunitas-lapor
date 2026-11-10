<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Services\AiAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiAnalyzerFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('reports');
    }

    /**
     * (a) Model pertama 429 lalu model kedua berhasil.
     */
    public function test_model_pertama_429_lalu_model_kedua_berhasil(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.models' => ['gemini-3.8-flash', 'gemini-3.5-flash'],
            'services.gemini.key' => 'fake-test-key',
        ]);

        $foto = UploadedFile::fake()->image('sampah.jpg', 640, 480);
        $fotoPath = $foto->store('reports', 'public');

        Http::fake([
            '*/gemini-3.8-flash:generateContent' => Http::response([
                'error' => ['code' => 429, 'message' => 'Quota exceeded'],
            ], 429),
            '*/gemini-3.5-flash:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'foto_menunjukkan_sampah' => true,
                                        'sesuai_deskripsi' => true,
                                        'kepercayaan' => 95,
                                        'kategori' => 'sampah_plastik',
                                        'tingkat_keparahan' => 3,
                                        'ringkasan' => 'Ditemukan botol plastik oleh model cadangan.',
                                        'alasan' => 'Foto jelas memperlihatkan botol plastik.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $analyzer = app(AiAnalyzer::class);
        $result = $analyzer->analyze($fotoPath, 'Tumpukan botol plastik di jalan');

        $this->assertNotNull($result);
        $this->assertTrue($result['relevan']);
        $this->assertEquals(95, $result['kepercayaan']);
        $this->assertEquals('sampah_plastik', $result['kategori']);
        $this->assertEquals('Ditemukan botol plastik oleh model cadangan.', $result['ringkasan']);

        // Pastikan kedua model dipanggil secara berurutan
        Http::assertSent(fn ($req) => str_contains($req->url(), 'gemini-3.8-flash'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'gemini-3.5-flash'));
    }

    /**
     * (b) Semua model gagal (misal 429 dan 503), kembalikan null (fallback ai_status = gagal tetap berjalan).
     */
    public function test_semua_model_gagal_mengembalikan_null(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.models' => ['gemini-3.8-flash', 'gemini-3.5-flash'],
            'services.gemini.key' => 'fake-test-key',
        ]);

        $foto = UploadedFile::fake()->image('sampah.jpg', 640, 480);
        $fotoPath = $foto->store('reports', 'public');

        Http::fake([
            '*/gemini-3.8-flash:generateContent' => Http::response(['error' => 'Quota exceeded'], 429),
            '*/gemini-3.5-flash:generateContent' => Http::response(['error' => 'Service Unavailable'], 503),
        ]);

        $analyzer = app(AiAnalyzer::class);
        $result = $analyzer->analyze($fotoPath, 'Sampah liar');

        // Harus mengembalikan null agar alur fallback aplikasi berjalan
        $this->assertNull($result);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'gemini-3.8-flash'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'gemini-3.5-flash'));
    }

    /**
     * Model pertama 503 (layanan sibuk) lalu model kedua berhasil tanpa jeda lama.
     */
    public function test_model_pertama_503_lalu_model_kedua_berhasil(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.models' => ['gemini-3.8-flash', 'gemini-3.5-flash'],
            'services.gemini.key' => 'fake-test-key',
        ]);

        $foto = UploadedFile::fake()->image('sampah.jpg', 640, 480);
        $fotoPath = $foto->store('reports', 'public');

        Http::fake([
            '*/gemini-3.8-flash:generateContent' => Http::response(['error' => 'Model overloaded'], 503),
            '*/gemini-3.5-flash:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'foto_menunjukkan_sampah' => false,
                                        'sesuai_deskripsi' => true,
                                        'kepercayaan' => 80,
                                        'kategori' => 'lainnya',
                                        'tingkat_keparahan' => 1,
                                        'ringkasan' => 'Bukan sampah liar.',
                                        'alasan' => 'Foto taman bersih.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $analyzer = app(AiAnalyzer::class);
        $result = $analyzer->analyze($fotoPath, 'Foto bukan sampah');

        $this->assertNotNull($result);
        $this->assertFalse($result['relevan']);
        $this->assertEquals(80, $result['kepercayaan']);
    }

    /**
     * Respons 404, 500, atau 502 juga memicu perpindahan ke model cadangan berikutnya.
     */
    public function test_respons_404_500_502_memicu_perpindahan_ke_model_cadangan(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.models' => ['gemini-deprecated', 'gemini-error-server', 'gemini-3.5-flash-lite'],
            'services.gemini.key' => 'fake-test-key',
        ]);

        $foto = UploadedFile::fake()->image('sampah.jpg', 640, 480);
        $fotoPath = $foto->store('reports', 'public');

        Http::fake([
            '*/gemini-deprecated:generateContent' => Http::response(['error' => 'Not Found'], 404),
            '*/gemini-error-server:generateContent' => Http::response(['error' => 'Internal Server Error'], 500),
            '*/gemini-3.5-flash-lite:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'foto_menunjukkan_sampah' => true,
                                        'sesuai_deskripsi' => true,
                                        'kepercayaan' => 90,
                                        'kategori' => 'sampah_organik',
                                        'tingkat_keparahan' => 2,
                                        'ringkasan' => 'Ditemukan sampah daun.',
                                        'alasan' => 'Sampah organik.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $analyzer = app(AiAnalyzer::class);
        $result = $analyzer->analyze($fotoPath, 'Sampah daun');

        $this->assertNotNull($result);
        $this->assertTrue($result['relevan']);
        $this->assertEquals('sampah_organik', $result['kategori']);

        Http::assertSent(fn ($req) => str_contains($req->url(), 'gemini-deprecated'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'gemini-error-server'));
        Http::assertSent(fn ($req) => str_contains($req->url(), 'gemini-3.5-flash-lite'));
    }

    /**
     * Integrasi penuh pengiriman laporan warga saat model pertama 429 dan model kedua berhasil.
     */
    public function test_alur_laporan_warga_sukses_ketika_model_pertama_429(): void
    {
        Storage::fake('public');

        config([
            'services.gemini.models' => ['gemini-3.8-flash', 'gemini-3.5-flash'],
            'services.gemini.key' => 'fake-test-key',
        ]);

        Http::fake([
            '*/gemini-3.8-flash:generateContent' => Http::response(['error' => 'Quota 429'], 429),
            '*/gemini-3.5-flash:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'foto_menunjukkan_sampah' => true,
                                        'sesuai_deskripsi' => true,
                                        'kepercayaan' => 88,
                                        'kategori' => 'sampah_organik',
                                        'tingkat_keparahan' => 2,
                                        'ringkasan' => 'Dedaunan kering berserakan.',
                                        'alasan' => 'Sampah daun.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'warga']);

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'judul' => 'Tumpukan Daun Kering',
            'deskripsi' => 'Banyak dedaunan di depan rumah',
            'foto' => UploadedFile::fake()->image('daun.jpg', 640, 480),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertEquals('berhasil', $report->ai_status);
        $this->assertEquals('sampah_organik', $report->kategori);
        $this->assertEquals(2, $report->tingkat_keparahan);
        $this->assertTrue($report->relevan);

        $response->assertRedirect(route('reports.show', $report));
        $response->assertSessionHas('status', 'Laporan terkirim dan dianalisis AI.');
    }
}
