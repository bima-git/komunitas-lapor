<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\StatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTimelineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * (1) Warga melihat timeline laporannya: entri awal 'Laporan dikirim' dan entri perubahan status.
     */
    public function test_warga_melihat_timeline_laporannya(): void
    {
        $warga = User::factory()->create(['role' => 'warga']);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Super']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Tumpukan Sampah Dekat Jembatan',
            'deskripsi' => 'Banyak sampah menyumbat aliran air',
            'foto_path' => 'reports/dummy.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'diproses',
            'created_at' => now()->subHours(5),
        ]);

        // Log 1: Perlu Verifikasi
        StatusLog::create([
            'report_id' => $report->id,
            'status' => 'perlu_verifikasi',
            'catatan' => 'Verifikasi keabsahan lokasi laporan',
            'tampil_ke_pelapor' => true,
            'diubah_oleh' => $admin->id,
            'created_at' => now()->subHours(3),
        ]);

        // Log 2: Diproses
        StatusLog::create([
            'report_id' => $report->id,
            'status' => 'diproses',
            'catatan' => 'Petugas sudah meluncur ke lokasi',
            'tampil_ke_pelapor' => true,
            'diubah_oleh' => $admin->id,
            'created_at' => now()->subHours(1),
        ]);

        $response = $this->actingAs($warga)->get(route('reports.show', $report));

        $response->assertOk();
        // Entri awal laporan dikirim beserta waktu
        $response->assertSee('Riwayat Status Laporan');
        $response->assertSee('Laporan dikirim');
        $response->assertSee($report->created_at->format('d M Y, H:i'));

        // Riwayat status dari logs
        $response->assertSee('Perlu Verifikasi');
        $response->assertSee('Diproses');
        $response->assertSee('Admin');

        // Pastikan urutan kronologis terlama ke terbaru
        $content = $response->getContent();
        $posAwal = strpos($content, 'Laporan dikirim');
        $posVerifikasi = strpos($content, 'Status: Perlu Verifikasi');
        $posDiproses = strpos($content, 'Status: Diproses');

        $this->assertNotFalse($posAwal);
        $this->assertNotFalse($posVerifikasi);
        $this->assertNotFalse($posDiproses);
        $this->assertLessThan($posVerifikasi, $posAwal);
        $this->assertLessThan($posDiproses, $posVerifikasi);
    }

    /**
     * (2) Catatan internal tidak muncul ke pelapor, sedangkan catatan publik muncul, dan nama asli admin tidak bocor.
     */
    public function test_catatan_internal_tidak_muncul_ke_pelapor_tetapi_catatan_publik_muncul(): void
    {
        $warga = User::factory()->create(['role' => 'warga', 'name' => 'Warga Pelapor']);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Bambang Superadmin Rahasia']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Sampah Bau Busuk di Pasar',
            'foto_path' => 'reports/dummy.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'diproses',
        ]);

        // Log 1: Catatan internal (tampil_ke_pelapor = false)
        StatusLog::create([
            'report_id' => $report->id,
            'status' => 'perlu_verifikasi',
            'catatan' => 'CATATAN_INTERNAL_RAHASIA_TIM_DLH',
            'tampil_ke_pelapor' => false,
            'diubah_oleh' => $admin->id,
        ]);

        // Log 2: Catatan publik (tampil_ke_pelapor = true)
        StatusLog::create([
            'report_id' => $report->id,
            'status' => 'diproses',
            'catatan' => 'CATATAN_PUBLIK_UNTUK_PELAPOR_TIM_OTW',
            'tampil_ke_pelapor' => true,
            'diubah_oleh' => $admin->id,
        ]);

        $response = $this->actingAs($warga)->get(route('reports.show', $report));

        $response->assertOk();

        // Catatan publik HARUS muncul
        $response->assertSee('CATATAN_PUBLIK_UNTUK_PELAPOR_TIM_OTW');

        // Catatan internal TIDAK BOLEH muncul
        $response->assertDontSee('CATATAN_INTERNAL_RAHASIA_TIM_DLH');

        // Nama personal admin TIDAK BOLEH muncul ke warga, melainkan tertulis "Admin"
        $response->assertDontSee('Bambang Superadmin Rahasia');
        $response->assertSee('Admin');
    }

    /**
     * (3) Warga tidak bisa melihat riwayat atau halaman laporan orang lain (ReportPolicy: 403 Forbidden).
     */
    public function test_warga_tidak_bisa_melihat_riwayat_laporan_orang_lain(): void
    {
        $wargaA = User::factory()->create(['role' => 'warga']);
        $wargaB = User::factory()->create(['role' => 'warga']);
        $admin = User::factory()->create(['role' => 'admin']);

        $reportA = Report::create([
            'user_id' => $wargaA->id,
            'judul' => 'Laporan Rahasia Milik Warga A',
            'foto_path' => 'reports/a.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
        ]);

        StatusLog::create([
            'report_id' => $reportA->id,
            'status' => 'diproses',
            'catatan' => 'Sedang dicek',
            'tampil_ke_pelapor' => true,
            'diubah_oleh' => $admin->id,
        ]);

        // Warga B dilarang melihat laporan Warga A
        $response = $this->actingAs($wargaB)->get(route('reports.show', $reportA));
        $response->assertForbidden();

        // Warga A dapat melihat laporannya sendiri
        $responseA = $this->actingAs($wargaA)->get(route('reports.show', $reportA));
        $responseA->assertOk();
    }

    /**
     * Admin dapat memilih untuk menampilkan catatan ke pelapor atau merahasiakannya via checkbox form.
     */
    public function test_admin_dapat_mencentang_tampil_ke_pelapor_saat_update_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        $report = Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan Uji Form Admin',
            'foto_path' => 'reports/test.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'baru',
        ]);

        // 1. Submit dengan checkbox dicentang (tampil_ke_pelapor = 1)
        $this->actingAs($admin)->patch(route('admin.reports.updateStatus', $report), [
            'status' => 'diproses',
            'catatan' => 'Tim armada kuning telah ditugaskan.',
            'tampil_ke_pelapor' => '1',
        ]);

        $this->assertDatabaseHas('status_logs', [
            'report_id' => $report->id,
            'status' => 'diproses',
            'catatan' => 'Tim armada kuning telah ditugaskan.',
            'tampil_ke_pelapor' => true,
        ]);

        // 2. Submit tanpa checkbox (tampil_ke_pelapor tidak dikirim / unchecked)
        $this->actingAs($admin)->patch(route('admin.reports.updateStatus', $report), [
            'status' => 'selesai',
            'catatan' => 'Catatan privat untuk koordinator dinas.',
        ]);

        $this->assertDatabaseHas('status_logs', [
            'report_id' => $report->id,
            'status' => 'selesai',
            'catatan' => 'Catatan privat untuk koordinator dinas.',
            'tampil_ke_pelapor' => false,
        ]);
    }
}
