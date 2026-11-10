<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Services\AiAnalyzer;
use App\Services\ImageResizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('reports');
    }

    public function test_warga_dapat_membuat_laporan_dan_dianalisis_ai_jika_relevan(): void
    {
        Storage::fake('public');

        // Gunakan Http::fake untuk mock pemanggilan API Gemini sungguhan
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
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
                                        'ringkasan' => 'Ditemukan tumpukan botol plastik.',
                                        'alasan' => 'Banyak botol plastik di pinggir jalan.',
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
            'judul' => 'Tumpukan Sampah Plastik',
            'deskripsi' => 'Banyak sampah botol di selokan',
            'foto' => UploadedFile::fake()->image('sampah.jpg', 800, 600),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertTrue($report->relevan);
        $this->assertEquals(95, $report->kepercayaan);
        $this->assertEquals('berhasil', $report->ai_status);
        $this->assertEquals('baru', $report->status);

        $response->assertRedirect(route('reports.show', $report));
        $response->assertSessionHas('status', 'Laporan terkirim dan dianalisis AI.');
    }

    public function test_laporan_ditandai_perlu_verifikasi_jika_foto_tidak_relevan(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'warga']);

        // Mock AiAnalyzer mengembalikan hasil tidak relevan
        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'relevan' => false,
            'kepercayaan' => 90,
            'kategori' => 'lainnya',
            'tingkat_keparahan' => 1,
            'ringkasan' => 'Foto tidak memperlihatkan tumpukan sampah.',
            'alasan' => 'Foto menampilkan kucing peliharaan.',
        ]);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'judul' => 'Laporan Palsu / Kucing',
            'deskripsi' => 'Foto kucing',
            'foto' => UploadedFile::fake()->image('kucing.jpg', 600, 400),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertFalse($report->relevan);
        $this->assertEquals(90, $report->kepercayaan);
        $this->assertEquals('berhasil', $report->ai_status);
        $this->assertEquals('perlu_verifikasi', $report->status);

        $expectedMessage = 'Foto dan deskripsi tampak tidak sesuai. Laporan ini akan diverifikasi admin.';

        $response->assertRedirect(route('reports.show', $report));
        $response->assertSessionHas('status', $expectedMessage);

        // Uji halaman show menampilkan peringatan baru
        $showResponse = $this->actingAs($user)->get(route('reports.show', $report));
        $showResponse->assertOk();
        $showResponse->assertSee('Peringatan:');
        $showResponse->assertSee($expectedMessage);
    }

    public function test_fallback_berjalan_jika_ai_gagal(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'warga']);

        // Mock AiAnalyzer gagal (return null)
        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn(null);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'judul' => 'Laporan Saat API Down',
            'deskripsi' => 'Sampah di trotoar',
            'foto' => UploadedFile::fake()->image('sampah2.jpg', 600, 400),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertNull($report->relevan);
        $this->assertEquals('gagal', $report->ai_status);
        $this->assertEquals('baru', $report->status);

        $response->assertRedirect(route('reports.show', $report));
        $response->assertSessionHas('status', 'Laporan terkirim. Analisis AI belum tersedia, admin akan melengkapi.');
    }

    public function test_policy_warga_hanya_bisa_melihat_laporannya_sendiri(): void
    {
        $warga1 = User::factory()->create(['role' => 'warga']);
        $warga2 = User::factory()->create(['role' => 'warga']);
        $admin = User::factory()->create(['role' => 'admin']);

        $report = Report::create([
            'user_id' => $warga1->id,
            'judul' => 'Laporan Warga 1',
            'foto_path' => 'reports/dummy.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
        ]);

        // Warga 1 bisa melihat laporannya
        $this->actingAs($warga1)->get(route('reports.show', $report))->assertOk();

        // Warga 2 ditolak (403)
        $this->actingAs($warga2)->get(route('reports.show', $report))->assertForbidden();

        // Admin bisa melihat semua laporan
        $this->actingAs($admin)->get(route('reports.show', $report))->assertOk();
    }

    /**
     * (a) Unggahan non-gambar ditolak oleh validasi ketat.
     */
    public function test_unggahan_non_gambar_ditolak(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'warga']);

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'judul' => 'Laporan Berkas PDF',
            'deskripsi' => 'Bukan file gambar',
            'foto' => UploadedFile::fake()->create('dokumen.pdf', 500, 'application/pdf'),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $response->assertSessionHasErrors('foto');
        $this->assertEquals(0, Report::count());
    }

    /**
     * (b) Foto besar dikecilkan sehingga sisi terpanjang <= 1280 px (format JPEG q=80).
     */
    public function test_foto_besar_dikecilkan_sehingga_sisi_terpanjang_maksimal_1280px(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'warga']);

        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'relevan' => true,
            'kepercayaan' => 92,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'ringkasan' => 'Sampah botol plastik.',
            'alasan' => 'Banyak botol plastik.',
        ]);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        // Buat file gambar berukuran 2000 x 1000 px
        $largePhoto = UploadedFile::fake()->image('foto_besar.png', 2000, 1000);

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'judul' => 'Sampah Plastik Besar',
            'deskripsi' => 'Uji coba pengecilan resolusi foto besar',
            'foto' => $largePhoto,
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertTrue(Storage::disk('public')->exists($report->foto_path));

        // Cek dimensi file yang disimpan
        $binary = Storage::disk('public')->get($report->foto_path);
        $size = getimagesizefromstring($binary);
        $this->assertNotFalse($size);

        $width = $size[0];
        $height = $size[1];

        // Sisi terpanjang maksimal 1280 px dengan rasio terjaga
        $this->assertLessThanOrEqual(1280, max($width, $height));
        $this->assertEquals(1280, $width);
        $this->assertEquals(640, $height);
        $this->assertEquals('image/jpeg', $size['mime']);
    }

    /**
     * (c) Jika pengecilan gagal, laporan tetap tersimpan menggunakan file asli.
     */
    public function test_jika_pengecilan_gagal_laporan_tetap_tersimpan(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'warga']);

        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn(null);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        // Mock ImageResizer untuk mensimulasikan kegagalan resize
        $mockResizer = $this->createMock(ImageResizer::class);
        $mockResizer->method('resizeAndStore')->willReturnCallback(function ($file, $dir, $disk) {
            return $file->store($dir, $disk);
        });
        $this->app->instance(ImageResizer::class, $mockResizer);

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'judul' => 'Laporan Saat Resize Gagal',
            'deskripsi' => 'Tetap tersimpan via fallback',
            'foto' => UploadedFile::fake()->image('normal.jpg', 600, 400),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertTrue(Storage::disk('public')->exists($report->foto_path));
        $response->assertRedirect(route('reports.show', $report));
    }

    /**
     * (d) Pengguna kena throttle setelah melewati batas dan melihat pesan ramah serta input terisi.
     */
    public function test_pengguna_kena_throttle_setelah_melewati_batas_dan_melihat_pesan_ramah(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'warga']);

        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn(null);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        // Batas per menit = 2
        for ($i = 0; $i < 2; $i++) {
            $res = $this->actingAs($user)->from(route('reports.create'))->post(route('reports.store'), [
                'judul' => 'Laporan Lolos ke-'.($i + 1),
                'deskripsi' => 'Deskripsi laporan',
                'foto' => UploadedFile::fake()->image('foto.jpg', 400, 300),
                'lat' => -6.9175,
                'lng' => 107.6191,
            ]);
            $res->assertRedirect();
            $res->assertSessionHasNoErrors();
        }

        // Laporan ke-3 dalam menit yang sama harus di-throttle
        $throttledRes = $this->actingAs($user)->from(route('reports.create'))->post(route('reports.store'), [
            'judul' => 'Laporan ke-3 Kena Throttle',
            'deskripsi' => 'Input ini harus tetap tersimpan di form',
            'foto' => UploadedFile::fake()->image('foto.jpg', 400, 300),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $pesanRamah = 'Terlalu banyak laporan dalam waktu singkat, coba lagi beberapa menit lagi';

        $throttledRes->assertRedirect(route('reports.create'));
        $throttledRes->assertSessionHas('error', $pesanRamah);

        // Pastikan halaman form create menampilkan pesan ramah dan input lama
        $formRes = $this->actingAs($user)->get(route('reports.create'));
        $formRes->assertOk();
        $formRes->assertSee($pesanRamah);
        $formRes->assertSee('Laporan ke-3 Kena Throttle');
        $formRes->assertSee('Input ini harus tetap tersimpan di form');
    }

    /**
     * (e) Admin tidak kena throttle.
     */
    public function test_admin_tidak_kena_throttle(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn(null);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        // Admin mengirim 5 laporan berturut-turut tanpa terkena throttle
        for ($i = 0; $i < 5; $i++) {
            $res = $this->actingAs($admin)->from(route('reports.create'))->post(route('reports.store'), [
                'judul' => 'Laporan Admin ke-'.($i + 1),
                'deskripsi' => 'Deskripsi admin',
                'foto' => UploadedFile::fake()->image('admin.jpg', 400, 300),
                'lat' => -6.9175,
                'lng' => 107.6191,
            ]);
            $res->assertRedirect();
            $res->assertSessionHasNoErrors();
        }

        $this->assertEquals(5, Report::count());
    }

    /**
     * (f) Pesan peringatan baru muncul untuk laporan tidak relevan.
     */
    public function test_pesan_peringatan_baru_muncul_untuk_laporan_tidak_relevan(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'warga']);

        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'relevan' => false,
            'kepercayaan' => 85,
            'kategori' => 'lainnya',
            'tingkat_keparahan' => 1,
            'ringkasan' => 'Foto tidak menunjukkan sampah liar yang sesuai.',
            'alasan' => 'Foto taman bersih.',
        ]);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'judul' => 'Foto Taman Bersih',
            'deskripsi' => 'Laporan sampah tapi foto bersih',
            'foto' => UploadedFile::fake()->image('taman.jpg', 600, 400),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertFalse($report->relevan);

        $expectedMessage = 'Foto dan deskripsi tampak tidak sesuai. Laporan ini akan diverifikasi admin.';

        $response->assertRedirect(route('reports.show', $report));
        $response->assertSessionHas('status', $expectedMessage);

        $showResponse = $this->actingAs($user)->get(route('reports.show', $report));
        $showResponse->assertOk();
        $showResponse->assertSee($expectedMessage);
    }
}
