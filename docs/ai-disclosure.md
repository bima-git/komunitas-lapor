# AI Disclosure

Dokumen ini mencatat pengungkapan dan transparansi penggunaan kecerdasan buatan (*AI agent* & *AI model*) dalam pengembangan dan operasional aplikasi **Komunitas Lapor**.

---

## 1. Penggunaan AI Agent dalam Pengembangan Kode (Pair Programming)

Pengembangan fitur-fitur berikut diimplementasikan dan diverifikasi dengan bantuan AI coding assistant (**Antigravity**):

### a. Dashboard Admin (`/admin`)
- **Peta Sebaran Titik Sampah Leaflet:** Visualisasi titik lokasi laporan dengan warna marker yang mencerminkan tingkat keparahan (memperhitungkan koreksi admin).
- **Tabel & Filter Laporan:** Penyaringan laporan berdasarkan status, kategori, keparahan, relevansi AI, dan status keberhasilan AI (`ai_status`).
- **Form Verifikasi & Koreksi Admin:** Memungkinkan admin mengoreksi kategori (dari `config/domain.php`) dan keparahan tanpa menimpa data rekomendasi AI asli (`kategori_admin`, `keparahan_admin`, `dikoreksi_oleh`).
- **Penanda AI Gagal & Fitur Analisis Ulang:** Deteksi visual laporan `ai_status = 'gagal'` dan tombol aksi bagi admin untuk memicu ulang analisis AI sewaktu-waktu.

### b. Riwayat Status Laporan (Timeline)
- **Tabel `status_logs` & Migrasi `tampil_ke_pelapor`:** Penambahan opsi privasi agar admin dapat memilih apakah catatan petugas ditampilkan ke pelapor atau disimpan sebagai catatan internal.
- **Timeline Pelapor:** Tampilan urutan kronologis terlama ke terbaru di halaman detail laporan warga (`reports/show.blade.php`), dimulai dari entri awal *"Laporan dikirim"*, dengan anonimisasi nama petugas (tertulis *"Admin"*).
- **Penegakan `ReportPolicy`:** Memastikan warga hanya dapat melihat riwayat laporannya sendiri (403 Forbidden untuk laporan warga lain).

### c. Halaman Statistik Admin (`/admin/statistik`)
- **Kartu Ringkasan KPI:** Menghitung total laporan, jumlah per status (`baru`, `diproses`, `selesai`, `perlu_verifikasi`), kartu jumlah duplikat, serta persentase laporan selesai (khusus laporan non-duplikat agar penutupan cepat duplikat tidak memanipulasi rasio penyelesaian).
- **Rata-rata Waktu Penyelesaian:** Mengukur selisih antara `created_at` laporan dan entri `status_logs` pertama berstatus *"selesai"* langsung di level basis data (menggunakan `TIMESTAMPDIFF` di MySQL / `strftime` di SQLite) tanpa memuat koleksi ke memori, dengan mengecualikan laporan yang ditandai duplikat (`duplikat_dari IS NOT NULL`).
- **Visualisasi Chart.js:**
  - Grafik batang jumlah laporan per kategori menggunakan nilai efektif (`COALESCE(kategori_admin, kategori)`).
  - Grafik garis tren pengaduan 8 minggu terakhir dengan zona waktu `APP_TIMEZONE` (`Asia/Jakarta`) dan label bahasa Indonesia. Minggu tanpa laporan tetap tercatat sebagai 0 dan tidak terlewati.
  - Penguncian versi CDN pada `chart.js@4.4.7`.

### d. Optimasi Pengunggahan & Keamanan Pelaporan
- **Pengecilan Foto Otomatis (`ImageResizer`):** Memperkecil resolusi foto secara otomatis ke sisi terpanjang maksimal 1280 px dengan format JPEG kualitas 80 serta rotasi orientasi EXIF menggunakan ekstensi GD bawaan PHP sebelum disimpan dan dikirim ke AI. Jika terjadi kegagalan (file rusak/GD tidak tersedia), sistem secara aman melakukan *graceful fallback* ke file asli.
- **Pembatasan Laju Pelaporan (Rate Limiting):** Melindungi endpoint pengiriman laporan (`POST /laporan`) dengan batas maksimal 10 laporan per jam dan 2 per menit untuk warga (admin dibebaskan dari pembatasan). Menampilkan pesan peringatan ramah berbahasa Indonesia dan mengembalikan form dengan input terisi saat batas terlampaui.
- **Penyempurnaan Teks Relevansi AI:** Memperbaiki teks notifikasi dan peringatan untuk kasus foto dan deskripsi yang tidak cocok menjadi *"Foto dan deskripsi tampak tidak sesuai. Laporan ini akan diverifikasi admin."*

### e. Deteksi Laporan Duplikat Berbasis Lokasi & Waktu (Fitur Spasial Non-AI)
- **Komputasi Matematis & Spasial Murni:** Deteksi duplikasi dilakukan sepenuhnya menggunakan algoritma spasial (penyaringan awal *bounding box* koordinat pada query basis data yang dilanjutkan dengan perhitungan jarak rumus Haversine di service `DuplicateDetector`). Fitur ini **tidak memanggil API model AI tambahan**, sehingga menghemat kuota dan latensi pengiriman.
- **Kriteria & Konfigurasi Fleksibel:** Laporan diklasifikasikan sebagai kandidat duplikat jika berada dalam radius $\le 50$ meter, berstatus belum selesai, dibuat dalam rentang 30 hari terakhir, dan memiliki kesamaan kategori efektif (`COALESCE(kategori_admin, kategori)`). Parameter ini dapat diatur melalui `config/laporan.php`.
- **Integritas & Hak Kontrol Admin:** Laporan yang terdeteksi tidak pernah dihapus atau digabungkan secara otomatis, melainkan hanya ditandai dengan foreign key `duplikat_dari`. Admin memiliki hak kendali penuh untuk melepaskan tanda (*"Bukan duplikat"*) atau menyelesaikan laporan (*"Tandai selesai sebagai duplikat"* beserta pencatatan riwayat di `status_logs`).
- **Perlindungan Privasi Pelapor:** Warga pelapor tidak pernah diblokir saat mengirimkan laporan duplikat dan hanya menerima pemberitahuan netral: *"Ada laporan serupa di sekitar lokasi ini, laporan Anda tetap kami terima."* Identitas, foto, maupun detail laporan warga lain dijaga kerahasiaannya dan tidak ditampilkan ke pelapor (`ReportPolicy`).
- **Metrik Statistik Admin:** Menambahkan kartu indikator KPI laporan duplikat pada `/admin/statistik` untuk memantau konsentrasi laporan serupa di lapangan.

---

## 2. Penggunaan AI Service dalam Aplikasi (Gemini API)

Aplikasi mengintegrasikan model Gemini (`AiAnalyzer`) untuk menganalisis laporan sampah liar yang dikirim oleh warga:
- **Analisis Relevansi & Kesesuaian Terpisah:** Model menghasilkan dua indikator boolean terpisah dengan konfigurasi suhu nol (`temperature = 0`) agar evaluasi lebih konsisten: `foto_menunjukkan_sampah` (apakah ada sampah di foto) dan `sesuai_deskripsi` (false hanya jika deskripsi warga jelas membahas masalah lain yang tidak terlihat di foto seperti jalan/trotoar rusak; deskripsi netral atau singkat bernilai true). Nilai `relevan` dihitung langsung di kode aplikasi (`relevan = foto_menunjukkan_sampah && sesuai_deskripsi`), dan jika tidak sesuai alasan AI diinstruksikan untuk secara transparan menyebutkan isi foto dan apa yang diklaim oleh deskripsi warga.
- **Saran Klasifikasi & Keparahan:** Menyarankan kategori sampah (sesuai domain di `config/domain.php`), tingkat keparahan (skala 1–5), dan ringkasan singkat maksimal 2 kalimat.
- **Model Cadangan Otomatis (Fallback Models):** Mendukung daftar model berurutan melalui `GEMINI_MODELS` (misal `gemini-3.5-flash-lite,gemini-3.8-flash`). Jika model utama mengalami kendala (HTTP 404, 429, 500, 502, atau 503), atau connection timeout, sistem langsung berpindah ke model cadangan secara otomatis tanpa jeda lama.
- **Fallback Keandalan & Analisis Ulang Admin:** Jika seluruh model AI mengalami kendala atau gagal merespons, laporan tetap tersimpan dengan `ai_status = 'gagal'` dan status dialihkan ke verifikasi admin tanpa menghilangkan data warga. Admin dapat memicu pemrosesan ulang AI kapan saja melalui tombol *"Analisis Ulang AI"* di panel admin.
