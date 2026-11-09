<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Services\AiAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_warga_dapat_membuat_laporan_dan_dianalisis_ai_jika_relevan(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'warga']);

        // Mock AiAnalyzer mengembalikan hasil relevan
        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'relevan' => true,
            'kepercayaan' => 95,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'ringkasan' => 'Ditemukan tumpukan botol plastik.',
            'alasan' => 'Banyak botol plastik di pinggir jalan.',
        ]);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        $response = $this->actingAs($user)->post(route('reports.store'), [
            'judul' => 'Tumpukan Sampah Plastik',
            'deskripsi' => 'Banyak sampah botol di selokan',
            'foto' => UploadedFile::fake()->image('sampah.jpg'),
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
            'foto' => UploadedFile::fake()->image('kucing.jpg'),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertFalse($report->relevan);
        $this->assertEquals(90, $report->kepercayaan);
        $this->assertEquals('berhasil', $report->ai_status);
        $this->assertEquals('perlu_verifikasi', $report->status);

        $response->assertRedirect(route('reports.show', $report));
        $response->assertSessionHas('status', 'Laporan terkirim, tetapi foto tampak tidak sesuai dan akan diverifikasi admin.');

        // Uji halaman show menampilkan peringatan
        $showResponse = $this->actingAs($user)->get(route('reports.show', $report));
        $showResponse->assertOk();
        $showResponse->assertSee('Peringatan:');
        $showResponse->assertSee('Tidak Relevan');
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
            'foto' => UploadedFile::fake()->image('sampah2.jpg'),
            'lat' => -6.9175,
            'lng' => 107.6191,
        ]);

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertNull($report->relevan);
        $this->assertEquals('gagal', $report->ai_status);
        $this->assertEquals('baru', $report->status); // status tetap baru, tidak perlu_verifikasi

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
}
