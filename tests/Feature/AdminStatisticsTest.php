<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\StatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStatisticsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * (1) Akses admin berhasil (200 OK) dan menampilkan elemen-elemen statistik.
     */
    public function test_akses_admin_berhasil_menampilkan_halaman_statistik(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.statistics'));

        $response->assertOk();
        $response->assertSee('Statistik & Kinerja Penanganan Sampah', false);
        $response->assertSee('Total Laporan');
        $response->assertSee('Laporan Baru');
        $response->assertSee('Diproses');
        $response->assertSee('Perlu Verifikasi');
        $response->assertSee('Selesai');
        $response->assertSee('Rata-rata Penyelesaian');
        $response->assertSee('Jumlah Laporan per Kategori');
        $response->assertSee('Tren Laporan Masuk');
        $response->assertSee('chart.js', false);
    }

    /**
     * (2) Warga tidak boleh mengakses halaman statistik (403 Forbidden).
     */
    public function test_warga_tidak_boleh_mengakses_halaman_statistik(): void
    {
        $warga = User::factory()->create(['role' => 'warga']);

        $response = $this->actingAs($warga)->get(route('admin.statistics'));

        $response->assertForbidden();
    }

    /**
     * Guest / tamu belum login diarahkan ke login.
     */
    public function test_guest_diarahkan_ke_login(): void
    {
        $response = $this->get(route('admin.statistics'));

        $response->assertRedirect(route('login'));
    }

    /**
     * (3) Halaman tetap tampil tanpa error saat belum ada data (data kosong).
     */
    public function test_halaman_tetap_tampil_saat_belum_ada_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.statistics'));

        $response->assertOk();
        $response->assertSee('Belum Ada Data Laporan');
        $response->assertSee('Belum ada data'); // Nilai default rata-rata penyelesaian
        $response->assertSee('0%'); // Persentase selesai
    }

    /**
     * (4) Perhitungan rata-rata waktu penyelesaian benar dengan data contoh.
     * Mengukur selisih created_at laporan dengan entri status_logs pertama bernilai 'selesai'.
     */
    public function test_perhitungan_rata_rata_waktu_penyelesaian_benar_dengan_data_contoh(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        // Laporan 1: Dibuat jam 10:00, selesai jam 12:00 (Durasi: 2 jam)
        $report1 = Report::forceCreate([
            'user_id' => $warga->id,
            'judul' => 'Sampah Jembatan A',
            'foto_path' => 'reports/1.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'selesai',
            'created_at' => '2026-11-01 10:00:00',
            'updated_at' => '2026-11-01 10:00:00',
        ]);

        // Log awal: diproses
        StatusLog::forceCreate([
            'report_id' => $report1->id,
            'status' => 'diproses',
            'catatan' => 'Sedang diangkut',
            'diubah_oleh' => $admin->id,
            'created_at' => '2026-11-01 11:00:00',
            'updated_at' => '2026-11-01 11:00:00',
        ]);

        // Log PERTAMA 'selesai' (jam 12:00 -> selisih 2 jam)
        StatusLog::forceCreate([
            'report_id' => $report1->id,
            'status' => 'selesai',
            'catatan' => 'Sampah selesai diangkut armada',
            'diubah_oleh' => $admin->id,
            'created_at' => '2026-11-01 12:00:00',
            'updated_at' => '2026-11-01 12:00:00',
        ]);

        // Log KEDUA 'selesai' (jam 14:00 -> harus diabaikan, wajib pakai entri pertama!)
        StatusLog::forceCreate([
            'report_id' => $report1->id,
            'status' => 'selesai',
            'catatan' => 'Verifikasi kedua selesai',
            'diubah_oleh' => $admin->id,
            'created_at' => '2026-11-01 14:00:00',
            'updated_at' => '2026-11-01 14:00:00',
        ]);

        // Laporan 2: Dibuat jam 08:00, selesai jam 12:00 (Durasi: 4 jam)
        $report2 = Report::forceCreate([
            'user_id' => $warga->id,
            'judul' => 'Sampah Pasar B',
            'foto_path' => 'reports/2.jpg',
            'lat' => -6.9180,
            'lng' => 107.6200,
            'status' => 'selesai',
            'created_at' => '2026-11-02 08:00:00',
            'updated_at' => '2026-11-02 08:00:00',
        ]);

        StatusLog::forceCreate([
            'report_id' => $report2->id,
            'status' => 'selesai',
            'catatan' => 'Pembersihan tuntas',
            'diubah_oleh' => $admin->id,
            'created_at' => '2026-11-02 12:00:00',
            'updated_at' => '2026-11-02 12:00:00',
        ]);

        // Laporan 3: Masih berstatus baru (belum selesai -> tidak boleh merusak perhitungan)
        Report::forceCreate([
            'user_id' => $warga->id,
            'judul' => 'Sampah Baru C',
            'foto_path' => 'reports/3.jpg',
            'lat' => -6.9190,
            'lng' => 107.6210,
            'status' => 'baru',
            'created_at' => '2026-11-03 09:00:00',
            'updated_at' => '2026-11-03 09:00:00',
        ]);

        // Rata-rata durasi: (2 jam + 4 jam) / 2 = 3 jam
        $response = $this->actingAs($admin)->get(route('admin.statistics'));

        $response->assertOk();
        $response->assertSee('3 jam');
    }

    /**
     * Perhitungan durasi dalam format hari dan jam jika durasi >= 24 jam.
     */
    public function test_perhitungan_durasi_dalam_hari_dan_jam(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        // Laporan memakan waktu 30 jam (1 hari 6 jam)
        $report = Report::forceCreate([
            'user_id' => $warga->id,
            'judul' => 'Sampah Menumpuk Lama',
            'foto_path' => 'reports/long.jpg',
            'lat' => -6.9175,
            'lng' => 107.6191,
            'status' => 'selesai',
            'created_at' => '2026-11-01 08:00:00',
            'updated_at' => '2026-11-01 08:00:00',
        ]);

        StatusLog::forceCreate([
            'report_id' => $report->id,
            'status' => 'selesai',
            'catatan' => 'Selesai 30 jam kemudian',
            'diubah_oleh' => $admin->id,
            'created_at' => '2026-11-02 14:00:00',
            'updated_at' => '2026-11-02 14:00:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.statistics'));

        $response->assertOk();
        $response->assertSee('1 hari 6 jam');
    }

    /**
     * Kartu ringkasan status dan persentase selesai terhitung akurat.
     */
    public function test_kartu_ringkasan_dan_persentase_selesai_akurat(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        // 4 laporan total: 1 baru, 1 diproses, 1 perlu_verifikasi, 1 selesai -> 25% selesai
        Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan 1',
            'foto_path' => 'a.jpg',
            'lat' => 0,
            'lng' => 0,
            'status' => 'baru',
        ]);

        Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan 2',
            'foto_path' => 'b.jpg',
            'lat' => 0,
            'lng' => 0,
            'status' => 'diproses',
        ]);

        Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan 3',
            'foto_path' => 'c.jpg',
            'lat' => 0,
            'lng' => 0,
            'status' => 'perlu_verifikasi',
        ]);

        Report::create([
            'user_id' => $warga->id,
            'judul' => 'Laporan 4',
            'foto_path' => 'd.jpg',
            'lat' => 0,
            'lng' => 0,
            'status' => 'selesai',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.statistics'));

        $response->assertOk();
        $response->assertSee('25%'); // 1 dari 4 laporan selesai
    }

    /**
     * Grafik kategori menggunakan nilai efektif (kategori_admin jika ada, fallback ke kategori AI).
     */
    public function test_grafik_kategori_menggunakan_nilai_efektif(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        // AI mengklasifikasikan sebagai sampah_plastik, tetapi dikoreksi admin ke limbah_konstruksi
        Report::create([
            'user_id' => $warga->id,
            'judul' => 'Sampah Puing Bangunan',
            'foto_path' => 'puing.jpg',
            'lat' => 0,
            'lng' => 0,
            'kategori' => 'sampah_plastik',
            'kategori_admin' => 'limbah_konstruksi',
            'status' => 'diproses',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.statistics'));

        $response->assertOk();
        // Cek data yang dikirim ke view: Limbah Konstruksi bernilai 1, Sampah Plastik 0
        $response->assertViewHas('categoryLabels', function ($labels) {
            return in_array('Limbah Konstruksi', $labels);
        });
        $response->assertViewHas('categoryData', function ($data) {
            // Karena satu-satunya laporan efektif adalah limbah_konstruksi, totalnya harus ada 1
            return in_array(1, $data);
        });
    }

    /**
     * Grafik mingguan: minggu tanpa laporan harus tampil sebagai 0, bukan terlewati (panjang 8 minggu).
     */
    public function test_grafik_mingguan_menampilkan_0_untuk_minggu_tanpa_laporan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $warga = User::factory()->create(['role' => 'warga']);

        // Buat 1 laporan pada minggu berjalan (minggu terakhir / ke-8)
        Report::create([
            'user_id' => $warga->id,
            'judul' => 'Sampah Minggu Ini',
            'foto_path' => 'now.jpg',
            'lat' => 0,
            'lng' => 0,
            'status' => 'baru',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.statistics'));

        $response->assertOk();
        $response->assertViewHas('weeklyLabels', function ($labels) {
            // Wajib ada tepat 8 minggu label
            return count($labels) === 8;
        });

        $response->assertViewHas('weeklyData', function ($data) {
            // Wajib ada tepat 8 data titik, minggu tanpa laporan harus bernilai 0 dan minggu ini bernilai 1
            return count($data) === 8
                && in_array(0, $data)
                && end($data) === 1;
        });
    }
}
