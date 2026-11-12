<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use App\Services\AiAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DuplicateReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        RateLimiter::clear('reports');
    }

    /**
     * (a) Laporan dalam 50 m dan kategori sama ditandai sebagai duplikat.
     */
    public function test_laporan_dalam_50_meter_dan_kategori_sama_ditandai_duplikat(): void
    {
        $warga1 = User::factory()->create(['role' => 'warga']);
        $warga2 = User::factory()->create(['role' => 'warga']);

        // Laporan awal (asli)
        $report1 = Report::create([
            'user_id' => $warga1->id,
            'judul' => 'Tumpukan Sampah Plastik Pertama',
            'deskripsi' => 'Banyak botol plastik',
            'foto_path' => 'reports/1.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'status' => 'baru',
            'created_at' => now()->subDays(2),
        ]);

        // Mock AI untuk laporan baru mengembalikan kategori yang sama
        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'foto_menunjukkan_sampah' => true,
            'sesuai_deskripsi' => true,
            'relevan' => true,
            'kepercayaan' => 95,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'ringkasan' => 'Ditemukan botol plastik.',
            'alasan' => 'Sampah plastik di pinggir jalan.',
        ]);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        // Laporan kedua berjarak ~33 meter (perbedaan 0.0003 lat)
        $response = $this->actingAs($warga2)->post(route('reports.store'), [
            'judul' => 'Sampah Botol Plastik Dekat Situ',
            'deskripsi' => 'Tolong dibersihkan botol ini',
            'foto' => UploadedFile::fake()->image('foto2.jpg', 640, 480),
            'lat' => -6.9175 + 0.0003,
            'lng' => 107.6191,
        ]);

        $report2 = Report::where('id', '!=', $report1->id)->first();
        $this->assertNotNull($report2);
        $this->assertEquals($report1->id, $report2->duplikat_dari);

        // Pesan netral untuk pelapor
        $response->assertRedirect(route('reports.show', $report2));
        $response->assertSessionHas('status', 'Ada laporan serupa di sekitar lokasi ini, laporan Anda tetap kami terima.');
    }

    /**
     * (b) Laporan di luar 50 m tidak ditandai sebagai duplikat.
     */
    public function test_laporan_di_luar_50_meter_tidak_ditandai_duplikat(): void
    {
        $warga1 = User::factory()->create(['role' => 'warga']);
        $warga2 = User::factory()->create(['role' => 'warga']);

        $report1 = Report::create([
            'user_id' => $warga1->id,
            'judul' => 'Laporan Awal',
            'foto_path' => 'reports/1.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'status' => 'baru',
        ]);

        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'foto_menunjukkan_sampah' => true,
            'sesuai_deskripsi' => true,
            'relevan' => true,
            'kepercayaan' => 90,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'ringkasan' => 'Ditemukan sampah plastik.',
            'alasan' => 'Sampah plastik.',
        ]);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        // Laporan kedua berjarak ~66.7 meter (perbedaan 0.0006 lat > 50m)
        $response = $this->actingAs($warga2)->post(route('reports.store'), [
            'judul' => 'Laporan Berjarak Jauh',
            'foto' => UploadedFile::fake()->image('foto2.jpg', 640, 480),
            'lat' => -6.9175 + 0.0006,
            'lng' => 107.6191,
        ]);

        $report2 = Report::where('id', '!=', $report1->id)->first();
        $this->assertNotNull($report2);
        $this->assertNull($report2->duplikat_dari);
        $response->assertSessionHas('status', 'Laporan terkirim dan dianalisis AI.');
    }

    /**
     * Laporan lebih dari rentang_hari (30 hari) tidak dihitung sebagai duplikat.
     */
    public function test_laporan_lebih_dari_rentang_hari_tidak_dihitung_sebagai_duplikat(): void
    {
        $warga1 = User::factory()->create(['role' => 'warga']);
        $warga2 = User::factory()->create(['role' => 'warga']);

        // Laporan awal dibuat 31 hari lalu (melewati rentang_hari = 30 hari)
        $report1 = Report::forceCreate([
            'user_id' => $warga1->id,
            'judul' => 'Laporan Sebulan Lalu',
            'foto_path' => 'reports/1.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'status' => 'baru',
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);

        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'foto_menunjukkan_sampah' => true,
            'sesuai_deskripsi' => true,
            'relevan' => true,
            'kepercayaan' => 90,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'ringkasan' => 'Sampah plastik di lokasi lama.',
            'alasan' => 'Sampah plastik.',
        ]);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        // Laporan baru berada dalam 33m dan kategori sama, tetapi laporan acuan sudah > 30 hari
        $this->actingAs($warga2)->post(route('reports.store'), [
            'judul' => 'Laporan Hari Ini',
            'foto' => UploadedFile::fake()->image('foto2.jpg', 640, 480),
            'lat' => -6.9175 + 0.0003,
            'lng' => 107.6191,
        ]);

        $report2 = Report::where('id', '!=', $report1->id)->first();
        $this->assertNotNull($report2);
        $this->assertNull($report2->duplikat_dari);
    }

    /**
     * Laporan dengan kategori kosong (AI gagal) tetap mendeteksi duplikat
     * menggunakan kriteria jarak dan waktu saja.
     */
    public function test_laporan_dengan_kategori_kosong_karena_ai_gagal_memakai_jarak_dan_waktu_saja(): void
    {
        $warga1 = User::factory()->create(['role' => 'warga']);
        $warga2 = User::factory()->create(['role' => 'warga']);

        $report1 = Report::create([
            'user_id' => $warga1->id,
            'judul' => 'Laporan Sampah Pertama',
            'foto_path' => 'reports/1.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'status' => 'baru',
            'created_at' => now()->subDays(2),
        ]);

        // Simulasikan AI gagal (mengembalikan null) sehingga kategori laporan 2 kosong
        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn(null);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        $response = $this->actingAs($warga2)->post(route('reports.store'), [
            'judul' => 'Laporan Kedua yang AI-nya Gagal',
            'foto' => UploadedFile::fake()->image('foto2.jpg', 640, 480),
            'lat' => -6.9175 + 0.0003, // ~33m
            'lng' => 107.6191,
        ]);

        $report2 = Report::where('id', '!=', $report1->id)->first();
        $this->assertNotNull($report2);
        $this->assertEquals('gagal', $report2->ai_status);
        $this->assertNull($report2->kategori);
        // Karena berada dalam 33m dan < 30 hari, tetap terdeteksi duplikat dari report1
        $this->assertEquals($report1->id, $report2->duplikat_dari);
        $response->assertSessionHas('status', 'Ada laporan serupa di sekitar lokasi ini, laporan Anda tetap kami terima.');
    }

    /**
     * (c) Laporan berstatus 'selesai' tidak dihitung sebagai duplikat.
     */
    public function test_laporan_berstatus_selesai_tidak_dihitung_sebagai_duplikat(): void
    {
        $warga1 = User::factory()->create(['role' => 'warga']);
        $warga2 = User::factory()->create(['role' => 'warga']);

        $report1 = Report::create([
            'user_id' => $warga1->id,
            'judul' => 'Laporan Sudah Dibersihkan',
            'foto_path' => 'reports/1.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'status' => 'selesai', // Sudah selesai!
        ]);

        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'foto_menunjukkan_sampah' => true,
            'sesuai_deskripsi' => true,
            'relevan' => true,
            'kepercayaan' => 90,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 3,
            'ringkasan' => 'Sampah baru di tempat yang sama.',
            'alasan' => 'Sampah plastik.',
        ]);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        // Laporan baru berada dalam 33m, tapi laporan lama sudah selesai
        $this->actingAs($warga2)->post(route('reports.store'), [
            'judul' => 'Sampah Muncul Lagi',
            'foto' => UploadedFile::fake()->image('foto2.jpg', 640, 480),
            'lat' => -6.9175 + 0.0003,
            'lng' => 107.6191,
        ]);

        $report2 = Report::where('id', '!=', $report1->id)->first();
        $this->assertNotNull($report2);
        $this->assertNull($report2->duplikat_dari);
    }

    /**
     * Kategori berbeda dalam 50m tidak ditandai sebagai duplikat.
     */
    public function test_kategori_berbeda_dalam_50_meter_tidak_ditandai_duplikat(): void
    {
        $warga1 = User::factory()->create(['role' => 'warga']);
        $warga2 = User::factory()->create(['role' => 'warga']);

        $report1 = Report::create([
            'user_id' => $warga1->id,
            'judul' => 'Sampah Daun Kering',
            'foto_path' => 'reports/1.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'kategori' => 'sampah_organik',
            'tingkat_keparahan' => 2,
            'status' => 'baru',
        ]);

        $mockAi = $this->createMock(AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'foto_menunjukkan_sampah' => true,
            'sesuai_deskripsi' => true,
            'relevan' => true,
            'kepercayaan' => 90,
            'kategori' => 'sampah_plastik', // Kategori berbeda!
            'tingkat_keparahan' => 3,
            'ringkasan' => 'Sampah botol plastik.',
            'alasan' => 'Plastik.',
        ]);
        $this->app->instance(AiAnalyzer::class, $mockAi);

        $this->actingAs($warga2)->post(route('reports.store'), [
            'judul' => 'Sampah Plastik di Dekat Daun',
            'foto' => UploadedFile::fake()->image('foto2.jpg', 640, 480),
            'lat' => -6.9175 + 0.0003,
            'lng' => 107.6191,
        ]);

        $report2 = Report::where('id', '!=', $report1->id)->first();
        $this->assertNotNull($report2);
        $this->assertNull($report2->duplikat_dari);
    }

    /**
     * (d) Pelapor tidak melihat data laporan orang lain.
     */
    public function test_pelapor_tidak_melihat_data_laporan_orang_lain(): void
    {
        $warga1 = User::factory()->create(['role' => 'warga', 'name' => 'Budi Santoso']);
        $warga2 = User::factory()->create(['role' => 'warga', 'name' => 'Siti Rahma']);

        $report1 = Report::create([
            'user_id' => $warga1->id,
            'judul' => 'Judul Rahasia Warga Budi',
            'foto_path' => 'reports/budi.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
        ]);

        $report2 = Report::create([
            'user_id' => $warga2->id,
            'judul' => 'Laporan Siti',
            'foto_path' => 'reports/siti.jpg',
            'lat' => -6.9175 + 0.0002,
            'lng' => 107.6191,
            'status' => 'baru',
            'duplikat_dari' => $report1->id,
        ]);

        $res = $this->actingAs($warga2)->get(route('reports.show', $report2));

        $res->assertOk();
        // Melihat pesan netral
        $res->assertSee('Ada laporan serupa di sekitar lokasi ini, laporan Anda tetap kami terima.');
        // TIDAK melihat nama pelapor lain, judul laporan lain, atau nomor ID laporan lain
        $res->assertDontSee('Budi Santoso');
        $res->assertDontSee('Judul Rahasia Warga Budi');
        $res->assertDontSee('reports/budi.jpg');
    }

    /**
     * (e) Tombol "Bukan duplikat" menghapus tanda, dan tombol "Tandai selesai sebagai duplikat" mengubah status.
     */
    public function test_tombol_bukan_duplikat_dan_selesai_duplikat_berfungsi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        $reportAsli = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Asli',
            'foto_path' => 'reports/asli.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
        ]);

        $reportDuplikat = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Duplikat',
            'foto_path' => 'reports/duplikat.jpg',
            'lat' => -6.9175 + 0.0002,
            'lng' => 107.6191,
            'status' => 'baru',
            'duplikat_dari' => $reportAsli->id,
        ]);

        // Aksi 1: Bukan duplikat
        $res1 = $this->actingAs($admin)->post(route('admin.reports.bukanDuplikat', $reportDuplikat));
        $res1->assertRedirect(route('admin.reports.show', $reportDuplikat));

        $reportDuplikat->refresh();
        $this->assertNull($reportDuplikat->duplikat_dari);

        // Pasang kembali tanda duplikat untuk menguji selesai duplikat
        $reportDuplikat->update(['duplikat_dari' => $reportAsli->id]);

        // Aksi 2: Tandai selesai sebagai duplikat
        $res2 = $this->actingAs($admin)->post(route('admin.reports.selesaiDuplikat', $reportDuplikat));
        $res2->assertRedirect(route('admin.reports.show', $reportDuplikat));

        $reportDuplikat->refresh();
        $this->assertEquals('selesai', $reportDuplikat->status);

        // Verifikasi log status tercatat otomatis dan tidak tampil ke pelapor
        $this->assertDatabaseHas('status_logs', [
            'report_id' => $reportDuplikat->id,
            'status' => 'selesai',
            'tampil_ke_pelapor' => false,
            'diubah_oleh' => $admin->id,
        ]);
    }

    /**
     * (f) Warga tidak bisa mengakses aksi admin (403 Forbidden).
     */
    public function test_warga_tidak_bisa_mengakses_aksi_admin(): void
    {
        $warga = User::factory()->create(['role' => 'warga']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Coba Akses',
            'foto_path' => 'reports/test.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
            'duplikat_dari' => 1,
        ]);

        $this->actingAs($warga)
            ->post(route('admin.reports.bukanDuplikat', $report))
            ->assertForbidden();

        $this->actingAs($warga)
            ->post(route('admin.reports.selesaiDuplikat', $report))
            ->assertForbidden();
    }

    /**
     * Filter duplikat di dashboard admin.
     */
    public function test_filter_duplikat_di_dashboard_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        $reportAsli = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Non Duplikat',
            'foto_path' => 'reports/a.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
            'duplikat_dari' => null,
        ]);

        $reportDuplikat = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Ini Duplikat',
            'foto_path' => 'reports/b.jpg',
            'lat' => -6.9175 + 0.0001,
            'lng' => 107.6191,
            'status' => 'baru',
            'duplikat_dari' => $reportAsli->id,
        ]);

        // Filter duplikat=1
        $res = $this->actingAs($admin)->get(route('admin.dashboard', ['duplikat' => '1']));
        $res->assertOk();
        $res->assertSee('Laporan Ini Duplikat');
        $res->assertDontSee('Laporan Non Duplikat');

        // Filter duplikat=0
        $res0 = $this->actingAs($admin)->get(route('admin.dashboard', ['duplikat' => '0']));
        $res0->assertOk();
        $res0->assertSee('Laporan Non Duplikat');
        $res0->assertDontSee('Laporan Ini Duplikat');
    }
}
