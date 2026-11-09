# AI Disclosure

Dokumen ini mencatat pengungkapan dan transparansi penggunaan kecerdasan buatan (*AI agent* & *AI model*) dalam pengembangan dan operasional aplikasi **Komunitas Lapor**.

---

## 1. Penggunaan AI Agent dalam Pengembangan Kode (Pair Programming)

Pengembangan fitur-fitur berikut diimplementasikan dan diverifikasi dengan bantuan AI coding assistant (**Antigravity**):

### a. Dashboard Admin (`/admin`)
- **Peta Sebaran Titik Sampah Leaflet:** Visualisasi titik lokasi laporan dengan warna marker yang mencerminkan tingkat keparahan (memperhitungkan koreksi admin).
- **Tabel & Filter Laporan:** Penyaringan laporan berdasarkan status, kategori, keparahan, dan validasi relevansi.
- **Form Verifikasi & Koreksi Admin:** Memungkinkan admin mengoreksi kategori (dari `config/domain.php`) dan keparahan tanpa menimpa data rekomendasi AI asli (`kategori_admin`, `keparahan_admin`, `dikoreksi_oleh`).

### b. Riwayat Status Laporan (Timeline)
- **Tabel `status_logs` & Migrasi `tampil_ke_pelapor`:** Penambahan opsi privasi agar admin dapat memilih apakah catatan petugas ditampilkan ke pelapor atau disimpan sebagai catatan internal.
- **Timeline Pelapor:** Tampilan urutan kronologis terlama ke terbaru di halaman detail laporan warga (`reports/show.blade.php`), dimulai dari entri awal *"Laporan dikirim"*, dengan anonimisasi nama petugas (tertulis *"Admin"*).
- **Penegakan `ReportPolicy`:** Memastikan warga hanya dapat melihat riwayat laporannya sendiri (403 Forbidden untuk laporan warga lain).

### c. Halaman Statistik Admin (`/admin/statistik`)
- **Kartu Ringkasan KPI:** Menghitung total laporan, jumlah per status (`baru`, `diproses`, `selesai`, `perlu_verifikasi`), serta persentase laporan selesai.
- **Rata-rata Waktu Penyelesaian:** Mengukur selisih antara `created_at` laporan dan entri `status_logs` pertama berstatus *"selesai"* langsung di level basis data (menggunakan `TIMESTAMPDIFF` di MySQL / `strftime` di SQLite) tanpa memuat koleksi ke memori.
- **Visualisasi Chart.js:**
  - Grafik batang jumlah laporan per kategori menggunakan nilai efektif (`COALESCE(kategori_admin, kategori)`).
  - Grafik garis tren pengaduan 8 minggu terakhir dengan zona waktu `APP_TIMEZONE` (`Asia/Jakarta`) dan label bahasa Indonesia. Minggu tanpa laporan tetap tercatat sebagai 0 dan tidak terlewati.
  - Penguncian versi CDN pada `chart.js@4.4.7`.

---

## 2. Penggunaan AI Service dalam Aplikasi (Gemini API)

Aplikasi mengintegrasikan model Gemini (`AiAnalyzer`) untuk menganalisis laporan sampah liar yang dikirim oleh warga:
- **Analisis Relevansi:** Menilai apakah foto menunjukkan sampah liar dan apakah sesuai dengan deskripsi warga (`relevan: true/false`, skor `kepercayaan: 0-100`).
- **Saran Klasifikasi:** Menyarankan kategori sampah (sesuai domain di `config/domain.php`), tingkat keparahan (skala 1–5), dan ringkasan singkat.
- **Fallback Keandalan:** Jika API AI mengalami kendala atau gagal merespons, laporan tetap tersimpan dengan `ai_status = 'gagal'` dan status dialihkan ke verifikasi admin tanpa menghilangkan data warga.
