<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_warga_tidak_bisa_mengakses_dashboard_admin(): void
    {
        $warga = User::factory()->create(['role' => 'warga']);

        $response = $this->actingAs($warga)->get(route('admin.dashboard'));

        $response->assertForbidden();
    }

    public function test_guest_diarahkan_ke_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_dapat_mengakses_dashboard_dan_melihat_daftar_laporan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Tumpukan Sampah di Pasar',
            'deskripsi' => 'Sampah organik menumpuk di depan pasar',
            'foto_path' => 'reports/dummy.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'tingkat_keparahan' => 4,
            'status' => 'baru',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Panel Admin');
        $response->assertSee('Tumpukan Sampah di Pasar');
        $response->assertSee('Peta Sebaran Titik Sampah');
    }

    public function test_admin_dapat_memfilter_laporan_berdasarkan_status_kategori_keparahan_dan_relevansi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        $kategoriList = config('domain.kategori');
        $kat1 = $kategoriList[0];
        $kat2 = $kategoriList[1] ?? 'lainnya';

        $report1 = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Butuh Verifikasi',
            'foto_path' => 'reports/1.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'kategori' => $kat1,
            'tingkat_keparahan' => 5,
            'status' => 'perlu_verifikasi',
            'relevan' => false,
        ]);

        $report2 = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Sudah Diproses',
            'foto_path' => 'reports/2.jpg',
            'lat' => -6.9180,
            'lng' => 107.6200,
            'kategori' => $kat2,
            'tingkat_keparahan' => 2,
            'status' => 'diproses',
            'relevan' => true,
        ]);

        // Filter status perlu_verifikasi
        $res = $this->actingAs($admin)->get(route('admin.dashboard', ['status' => 'perlu_verifikasi']));
        $res->assertSee('Laporan Butuh Verifikasi');
        $res->assertDontSee('Laporan Sudah Diproses');

        // Filter keparahan 5
        $res = $this->actingAs($admin)->get(route('admin.dashboard', ['keparahan' => '5']));
        $res->assertSee('Laporan Butuh Verifikasi');
        $res->assertDontSee('Laporan Sudah Diproses');

        // Filter relevan = 0 (tidak relevan)
        $res = $this->actingAs($admin)->get(route('admin.dashboard', ['relevan' => '0']));
        $res->assertSee('Laporan Butuh Verifikasi');
        $res->assertDontSee('Laporan Sudah Diproses');
    }

    public function test_admin_dapat_melihat_detail_dan_memperbarui_status_serta_mencatat_status_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Utama']);
        $warga = User::factory()->create(['role' => 'warga']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Perlu Tinjauan',
            'foto_path' => 'reports/review.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'perlu_verifikasi',
            'relevan' => false,
        ]);

        // Buka halaman detail admin
        $detailRes = $this->actingAs($admin)->get(route('admin.reports.show', $report));
        $detailRes->assertOk();
        $detailRes->assertSee('Verifikasi & Koreksi Admin', false);
        $detailRes->assertSee('Perlu Verifikasi');

        // Submit form ubah status & verifikasi
        $kategoriList = config('domain.kategori');
        $updateRes = $this->actingAs($admin)->patch(route('admin.reports.updateStatus', $report), [
            'status' => 'diproses',
            'kategori_admin' => $kategoriList[0],
            'keparahan_admin' => 4,
            'relevan' => '1',
            'catatan' => 'Foto telah dikonfirmasi valid oleh petugas lapangan.',
        ]);

        $updateRes->assertRedirect(route('admin.reports.show', $report));
        $updateRes->assertSessionHas('status', 'Status dan koreksi laporan berhasil disimpan.');

        // Verifikasi perubahan pada tabel reports
        $report->refresh();
        $this->assertEquals('diproses', $report->status);
        $this->assertEquals($kategoriList[0], $report->kategori_admin);
        $this->assertEquals(4, $report->keparahan_admin);
        $this->assertEquals($admin->id, $report->dikoreksi_oleh);
        $this->assertTrue($report->relevan);

        // Verifikasi pencatatan pada tabel status_logs
        $this->assertDatabaseHas('status_logs', [
            'report_id' => $report->id,
            'status' => 'diproses',
            'catatan' => 'Foto telah dikonfirmasi valid oleh petugas lapangan.',
            'diubah_oleh' => $admin->id,
        ]);

        // Periksa riwayat tampil di halaman show
        $afterRes = $this->actingAs($admin)->get(route('admin.reports.show', $report));
        $afterRes->assertSee('Foto telah dikonfirmasi valid oleh petugas lapangan.');
        $afterRes->assertSee('Admin Utama');
    }

    public function test_admin_dapat_mengoreksi_kategori_dan_keparahan_tanpa_menimpa_saran_ai_asli(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Korektor']);
        $warga = User::factory()->create(['role' => 'warga']);

        $kategoriList = config('domain.kategori');
        $katAi = $kategoriList[0];
        $katAdmin = $kategoriList[1] ?? 'lainnya';

        // Laporan awal dari AI
        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Tumpukan Botol Dekat Selokan',
            'foto_path' => 'reports/botol.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'kategori' => $katAi,
            'tingkat_keparahan' => 2, // Saran AI: 2
            'ringkasan_ai' => 'Saran AI mendeteksi kategori awal.',
            'ai_status' => 'berhasil',
            'status' => 'baru',
            'relevan' => true,
        ]);

        // Admin melakukan koreksi
        $this->actingAs($admin)->patch(route('admin.reports.updateStatus', $report), [
            'status' => 'diproses',
            'kategori_admin' => $katAdmin,
            'keparahan_admin' => 5, // Koreksi Admin: 5
            'catatan' => 'Kategori dikoreksi karena ada limbah konstruksi berbahaya.',
        ]);

        $report->refresh();

        // 1. Pastikan data saran AI asli TIDAK tertimpa
        $this->assertEquals($katAi, $report->kategori);
        $this->assertEquals(2, $report->tingkat_keparahan);

        // 2. Pastikan data koreksi admin tersimpan di kolom terpisah
        $this->assertEquals($katAdmin, $report->kategori_admin);
        $this->assertEquals(5, $report->keparahan_admin);
        $this->assertEquals($admin->id, $report->dikoreksi_oleh);

        // 3. Pastikan halaman detail menampilkan label "Saran AI" dan "Dikoreksi admin" secara terpisah
        $showRes = $this->actingAs($admin)->get(route('admin.reports.show', $report));
        $showRes->assertOk();
        $showRes->assertSee('Saran AI:');
        $showRes->assertSee('Dikoreksi admin:');
        $showRes->assertSee('5 / 5');
        $showRes->assertSee('Admin Korektor');
    }

    public function test_pengurutan_laporan_memakai_nilai_keparahan_admin_jika_ada(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        // Report 1: AI keparahan 1, tapi dikoreksi admin jadi 5 (seharusnya tampil pertama!)
        $reportA = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan A - Awal 1 Dikoreksi Jadi 5',
            'foto_path' => 'reports/a.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'tingkat_keparahan' => 1,
            'keparahan_admin' => 5,
            'status' => 'baru',
        ]);

        // Report 2: AI keparahan 4, belum dikoreksi admin
        $reportB = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan B - AI Keparahan 4',
            'foto_path' => 'reports/b.jpg',
            'lat' => -6.9180,
            'lng' => 107.6200,
            'tingkat_keparahan' => 4,
            'keparahan_admin' => null,
            'status' => 'baru',
        ]);

        $res = $this->actingAs($admin)->get(route('admin.dashboard'));
        $res->assertOk();

        $content = $res->getContent();
        $posA = strpos($content, 'Laporan A - Awal 1 Dikoreksi Jadi 5');
        $posB = strpos($content, 'Laporan B - AI Keparahan 4');

        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        // Laporan A (efektif 5) harus muncul sebelum Laporan B (efektif 4)
        $this->assertLessThan($posB, $posA);
    }

    public function test_admin_dapat_memicu_analisis_ulang_ai_dan_memperbarui_laporan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Gagal AI Sebelumnya',
            'deskripsi' => 'Banyak plastik di selokan',
            'foto_path' => 'reports/failed.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
            'ai_status' => 'gagal',
        ]);

        $mockAi = $this->createMock(\App\Services\AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn([
            'foto_menunjukkan_sampah' => true,
            'sesuai_deskripsi' => true,
            'relevan' => true,
            'kepercayaan' => 95,
            'kategori' => 'sampah_plastik',
            'tingkat_keparahan' => 4,
            'ringkasan' => 'Ditemukan tumpukan plastik menyumbat saluran.',
            'alasan' => 'Foto dan deskripsi sesuai.',
        ]);
        $this->app->instance(\App\Services\AiAnalyzer::class, $mockAi);

        $res = $this->actingAs($admin)->post(route('admin.reports.reanalyze', $report));

        $res->assertRedirect(route('admin.reports.show', $report));
        $res->assertSessionHas('status', "Laporan #{$report->id} berhasil dianalisis ulang oleh AI.");

        $report->refresh();
        $this->assertEquals('berhasil', $report->ai_status);
        $this->assertTrue($report->relevan);
        $this->assertEquals(95, $report->kepercayaan);
        $this->assertEquals('sampah_plastik', $report->kategori);
        $this->assertEquals(4, $report->tingkat_keparahan);
        $this->assertEquals('Ditemukan tumpukan plastik menyumbat saluran.', $report->ringkasan_ai);
    }

    public function test_analisis_ulang_ai_gagal_tetap_menjaga_ai_status_gagal_dan_menampilkan_pesan_warning(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Kuota Habis',
            'foto_path' => 'reports/failed2.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
            'ai_status' => 'gagal',
        ]);

        $mockAi = $this->createMock(\App\Services\AiAnalyzer::class);
        $mockAi->method('analyze')->willReturn(null);
        $this->app->instance(\App\Services\AiAnalyzer::class, $mockAi);

        $res = $this->actingAs($admin)->post(route('admin.reports.reanalyze', $report));

        $res->assertRedirect(route('admin.reports.show', $report));
        $res->assertSessionHas('warning');

        $report->refresh();
        $this->assertEquals('gagal', $report->ai_status);
    }

    public function test_warga_tidak_bisa_memicu_analisis_ulang_ai(): void
    {
        $warga = User::factory()->create(['role' => 'warga']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Warga',
            'foto_path' => 'reports/user.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
            'ai_status' => 'gagal',
        ]);

        $res = $this->actingAs($warga)->post(route('admin.reports.reanalyze', $report));

        $res->assertForbidden();
    }

    public function test_admin_dapat_memfilter_laporan_berdasarkan_ai_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        $reportGagal = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Gagal AI',
            'foto_path' => 'reports/gagal.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
            'ai_status' => 'gagal',
        ]);

        $reportBerhasil = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Berhasil AI',
            'foto_path' => 'reports/berhasil.jpg',
            'lat' => -6.9180,
            'lng' => 107.6200,
            'status' => 'baru',
            'ai_status' => 'berhasil',
            'relevan' => true,
        ]);

        $res = $this->actingAs($admin)->get(route('admin.dashboard', ['ai_status' => 'gagal']));
        $res->assertOk();
        $res->assertSee('Laporan Gagal AI');
        $res->assertDontSee('Laporan Berhasil AI');
    }

    public function test_tampilan_detail_admin_menampilkan_penanda_ai_gagal_dan_tombol_analisis_ulang(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Gagal Menunggu Ulang',
            'foto_path' => 'reports/wait.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
            'ai_status' => 'gagal',
        ]);

        $res = $this->actingAs($admin)->get(route('admin.reports.show', $report));
        $res->assertOk();
        $res->assertSee('Analisis AI Belum Tersedia (AI Gagal)');
        $res->assertSee('Analisis Ulang AI');
    }
}
