<?php

namespace Tests\Feature;

use App\Services\AiAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    private string $fakeFotoPath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        config([
            'services.gemini.key' => 'fake-key',
            'services.gemini.models' => ['gemini-test-model'],
        ]);

        $foto = UploadedFile::fake()->image('uji_sampah.jpg', 640, 480);
        $this->fakeFotoPath = $foto->store('reports', 'public');
    }

    /**
     * (a) foto sampah + sesuai_deskripsi false -> relevan false
     */
    public function test_foto_sampah_dan_sesuai_deskripsi_false_menghasilkan_relevan_false(): void
    {
        Http::fake([
            '*/gemini-test-model:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'foto_menunjukkan_sampah' => true,
                                        'sesuai_deskripsi' => false,
                                        'kepercayaan' => 95,
                                        'kategori' => 'sampah_organik',
                                        'tingkat_keparahan' => 2,
                                        'ringkasan' => 'Foto memperlihatkan sampah sisa makanan.',
                                        'alasan' => 'Foto menunjukkan sampah organik, tetapi deskripsi warga membahas trotoar rusak yang tidak terlihat di foto.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $analyzer = app(AiAnalyzer::class);
        $result = $analyzer->analyze($this->fakeFotoPath, 'trotoar yogya rusak');

        $this->assertNotNull($result);
        $this->assertTrue($result['foto_menunjukkan_sampah']);
        $this->assertFalse($result['sesuai_deskripsi']);
        $this->assertFalse($result['relevan']);
        $this->assertEquals(95, $result['kepercayaan']);
        $this->assertEquals('sampah_organik', $result['kategori']);
        $this->assertStringContainsString('trotoar rusak', $result['alasan']);
    }

    /**
     * (b) foto bukan sampah -> relevan false
     */
    public function test_foto_bukan_sampah_menghasilkan_relevan_false(): void
    {
        Http::fake([
            '*/gemini-test-model:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'foto_menunjukkan_sampah' => false,
                                        'sesuai_deskripsi' => true,
                                        'kepercayaan' => 90,
                                        'kategori' => 'lainnya',
                                        'tingkat_keparahan' => 1,
                                        'ringkasan' => 'Foto bukan sampah melainkan halaman taman.',
                                        'alasan' => 'Foto menampakkan halaman rumput bersih tanpa sampah.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $analyzer = app(AiAnalyzer::class);
        $result = $analyzer->analyze($this->fakeFotoPath, 'halaman taman');

        $this->assertNotNull($result);
        $this->assertFalse($result['foto_menunjukkan_sampah']);
        $this->assertTrue($result['sesuai_deskripsi']);
        $this->assertFalse($result['relevan']);
        $this->assertEquals('lainnya', $result['kategori']);
    }

    /**
     * (c) keduanya true -> relevan true
     */
    public function test_keduanya_true_menghasilkan_relevan_true(): void
    {
        Http::fake([
            '*/gemini-test-model:generateContent' => Http::response([
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
                                        'tingkat_keparahan' => 4,
                                        'ringkasan' => 'Tumpukan kantong plastik menghalangi selokan.',
                                        'alasan' => 'Foto dan deskripsi sama-sama menunjukkan tumpukan sampah plastik di selokan.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $analyzer = app(AiAnalyzer::class);
        $result = $analyzer->analyze($this->fakeFotoPath, 'Banyak sampah plastik di selokan');

        $this->assertNotNull($result);
        $this->assertTrue($result['foto_menunjukkan_sampah']);
        $this->assertTrue($result['sesuai_deskripsi']);
        $this->assertTrue($result['relevan']);
        $this->assertEquals(4, $result['tingkat_keparahan']);
    }

    /**
     * (d) deskripsi netral tetap relevan true (diwakili respons fake)
     */
    public function test_deskripsi_netral_tetap_relevan_true(): void
    {
        Http::fake([
            '*/gemini-test-model:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'foto_menunjukkan_sampah' => true,
                                        'sesuai_deskripsi' => true,
                                        'kepercayaan' => 95,
                                        'kategori' => 'sampah_organik',
                                        'tingkat_keparahan' => 2,
                                        'ringkasan' => 'Sisa makanan dan kulit buah berserakan di tanah.',
                                        'alasan' => 'Foto menunjukkan sampah organik dan deskripsi warga netral ("tolong dibersihkan") sehingga dinilai sesuai.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $analyzer = app(AiAnalyzer::class);
        $result = $analyzer->analyze($this->fakeFotoPath, 'tolong dibersihkan');

        $this->assertNotNull($result);
        $this->assertTrue($result['foto_menunjukkan_sampah']);
        $this->assertTrue($result['sesuai_deskripsi']);
        $this->assertTrue($result['relevan']);
        $this->assertEquals('sampah_organik', $result['kategori']);
    }
}
