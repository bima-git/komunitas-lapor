# Komunitas Lapor — Platform Pelaporan Sampah Liar dengan Analisis AI

**Komunitas Lapor** adalah aplikasi web berbasis komunitas untuk mendeteksi, melaporkan, dan memantau penanganan sampah liar secara cepat, transparan, dan terstruktur.

Aplikasi ini mengatasi kendala klasik pelaporan manual: laporan sampah yang menumpuk tanpa prioritas yang jelas, foto yang tidak sesuai dengan isi aduan, dan lambatnya respon penanganan. Dengan mengintegrasikan model kecerdasan buatan multimodal (**Google Gemini**), setiap foto dan deskripsi yang dikirim warga langsung dianalisis untuk menilai keabsahan laporan, mendeteksi jenis sampah, serta menentukan tingkat keparahan lokasi secara otomatis.

---

## 1. Fitur Utama

### Untuk Warga (Pelapor)
- **Pelaporan Berbasis Peta:** Menandai lokasi tumpukan sampah secara presisi menggunakan peta interaktif Leaflet.
- **Pengecilan Foto Otomatis:** Foto lapangan otomatis diperkecil ukurannya (sisi terpanjang maksimal 1280 px, format JPEG kualitas 80) di sisi server sebelum disimpan dan dianalisis, sehingga hemat kuota dan menjaga orientasi foto ponsel (EXIF).
- **Analisis AI Instan:** Memperoleh saran kategori, skala keparahan (1–5), ringkasan kondisi, dan evaluasi kesesuaian foto vs deskripsi secara transparan.
- **Perlindungan Anti-Spam (Rate Limiting):** Pembatasan laju pengiriman laporan yang ramah (maksimal 2 laporan per menit dan 10 per jam). Jika batas terlampaui, isian form tidak hilang dan pengguna menerima pesan informatif.
- **Timeline Riwayat Status:** Memantau progres penanganan laporan dari awal (*Laporan dikirim*) hingga selesai secara kronologis. Nama pribadi petugas disamarkan demi privasi (ditampilkan sebagai *"Admin"*).
- **Akses Mandiri Terproteksi:** Setiap warga hanya dapat mengakses dan melihat rincian laporannya sendiri (`ReportPolicy`).

### Untuk Admin & Petugas Kebersihan
- **Dashboard Pemantauan & Verifikasi (`/admin`):** Tabel pemantauan seluruh laporan masuk dengan filter dinamis (status, kategori, tingkat keparahan, relevansi AI, dan status AI). Pengurutan default memprioritaskan tingkat keparahan tertinggi.
- **Peta Sebaran Titik Sampah:** Visualisasi spasial seluruh titik laporan dengan penanda (*marker*) berwarna yang merefleksikan tingkat keparahan lapangan.
- **Verifikasi & Koreksi Admin:** Formulir penyesuaian status, validasi relevansi, serta koreksi kategori dan keparahan tanpa menimpa data rekomendasi AI asli (`kategori_admin`, `keparahan_admin`, `dikoreksi_oleh`).
- **Penanda `ai_status = gagal` & Tombol Analisis Ulang AI:** Deteksi visual laporan yang gagal dianalisis AI (akibat kuota harian habis atau gangguan jaringan) dengan badge khusus dan filter `ai_status`, disertai tombol aksi *Analisis Ulang AI* pada halaman detail untuk memproses ulang kapan saja.
- **Pengaturan Privasi Catatan:** Admin dapat menentukan apakah catatan tindak lanjut dapat dibaca oleh pelapor (`tampil_ke_pelapor`) atau hanya sebagai catatan audit internal.
- **Dashboard Kinerja & Statistik (`/admin/statistik`):**
  - Kartu ringkasan metrik KPI (Total, Baru, Diproses, Perlu Verifikasi, Selesai, dan Persentase).
  - Rata-rata waktu penyelesaian laporan dihitung langsung pada level basis data.
  - Grafik batang distribusi laporan per kategori efektif.
  - Grafik garis tren pengaduan masuk selama 8 minggu terakhir (versi Chart.js 4.4.7 terkunci).

---

## 2. Alur Analisis AI & Ketahanan Sistem

Aplikasi menggunakan service `AiAnalyzer` yang berkomunikasi dengan API Google Gemini menggunakan format *Structured Outputs*:

### Input Analisis
- Foto lapangan yang telah dikompresi (JPEG $\le$ 1280 px).
- Deskripsi teks yang ditulis oleh pelapor.
- Prompt panduan dan daftar kategori resmi dari `config/domain.php`.

### Keluaran JSON Terstruktur
```json
{
  "foto_menunjukkan_sampah": true,
  "sesuai_deskripsi": true,
  "kepercayaan": 95,
  "kategori": "sampah_plastik",
  "tingkat_keparahan": 4,
  "ringkasan": "Tumpukan limbah botol dan kantong plastik menutupi bahu jalan.",
  "alasan": "Foto jelas memperlihatkan tumpukan sampah plastik yang menghalangi akses pejalan kaki."
}
```

### Rubrik Tingkat Keparahan (1–5)
1. **Tingkat 1 (Sangat Rendah):** Tidak ada sampah atau sampah sangat sedikit (hanya 1–2 item kecil).
2. **Tingkat 2 (Rendah):** Sampah sedikit, tersebar, dan tidak menghalangi fasilitas apa pun.
3. **Tingkat 3 (Sedang):** Sampah cukup banyak atau terkumpul di satu titik, belum menghalangi akses publik.
4. **Tingkat 4 (Tinggi):** Tumpukan besar yang menghalangi jalan, menyumbat saluran air, atau berbau/menarik hama.
5. **Tingkat 5 (Sangat Mendesak / Darurat):** Tumpukan sangat masif yang membahayakan kesehatan masyarakat atau mencemari badan air/permukiman.
*Catatan:* Jika foto berada di area pembuangan resmi (TPS/TPA), keparahan diturunkan maksimal 3.

### Penanda Relevansi & Kesesuaian Foto vs Deskripsi
- Evaluasi relevansi dilakukan secara lebih konsisten (`temperature = 0`) melalui dua tahap:
  1. `foto_menunjukkan_sampah`: Bernilai `true` jika foto menampakkan sampah liar.
  2. `sesuai_deskripsi`: Bernilai `false` HANYA jika deskripsi warga jelas membahas masalah lain yang tidak terlihat di foto (misalnya trotoar rusak, jalan berlubang). Deskripsi kosong, singkat, atau netral (misalnya *"iya"*, *"tolong dibersihkan"*) bernilai `true`. Jika bernilai `false`, alasan AI secara spesifik menyebutkan apa yang terlihat di foto dan apa yang diklaim deskripsi.
- Status relevansi akhir dihitung di kode aplikasi: `relevan = foto_menunjukkan_sampah && sesuai_deskripsi`.
- Jika `relevan = false`, sistem otomatis mengalihkan status laporan menjadi `perlu_verifikasi`, kategori menjadi `lainnya`, keparahan diset ke `1`, dan menampilkan pesan ramah: *"Foto dan deskripsi tampak tidak sesuai. Laporan ini akan diverifikasi admin."*

### Ketahanan Kuota & Model Cadangan (Multi-Model Fallback)
Aplikasi mendukung daftar model berurutan melalui konfigurasi `GEMINI_MODELS` (contoh: `gemini-3.5-flash-lite,gemini-3.8-flash`):
1. Sistem mencoba model utama terlebih dahulu dengan batas waktu (timeout) 15 detik.
2. Jika model utama mengalami kendala HTTP `404`, `429` (kuota habis), `500`, `502`, `503` (layanan sibuk), atau kendala koneksi/timeout, sistem **langsung beralih ke model cadangan berikutnya tanpa jeda panjang**.
3. Jika seluruh model gagal, sistem menjalankan *graceful fallback*: laporan tetap tersimpan di basis data dengan status `ai_status = 'gagal'`, dan admin dapat melengkapi klasifikasi secara manual.

---

## 3. Tech Stack

- **Backend:** Laravel 12 (PHP 8.4)
- **Autentikasi & Starter Kit:** Laravel Breeze (Blade Components)
- **Basis Data:** MySQL
- **Frontend & Styling:** Tailwind CSS, Alpine.js
- **Peta Interaktif:** Leaflet JS (OpenStreetMap Tiles)
- **Grafik & Visualisasi Data:** Chart.js v4.4.7
- **Pengolah Gambar:** Ekstensi PHP GD (bawaan PHP)
- **Engine AI:** Google Gemini API (Multimodal Structured Outputs)

---

## 4. Cara Menjalankan di Lingkungan Lokal

### Prasyarat
- PHP $\ge$ 8.4 dengan ekstensi `gd`, `pdo_mysql`, `curl`, `mbstring`, `exif`.
- Composer $\ge$ 2.x
- Node.js & NPM
- MySQL Server

### Langkah Instalasi

1. **Clone repositori:**
   ```bash
   git clone https://github.com/bima-git/komunitas-lapor.git
   cd komunitas-lapor
   ```

2. **Pasang dependensi PHP dan Node.js:**
   ```bash
   composer install
   npm install
   ```

3. **Salin berkas konfigurasi lingkungan:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Konfigurasi basis data pada berkas `.env`:**
   Sesuaikan parameter koneksi MySQL Anda:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=komunitas_lapor
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Konfigurasi Kunci API Gemini pada berkas `.env`:**
   Dapatkan API Key dari [Google AI Studio](https://aistudio.google.com/):
   ```env
   GEMINI_API_KEY=masukkan_kunci_api_anda_di_sini
   GEMINI_MODEL=gemini-3.5-flash-lite
   GEMINI_MODELS=gemini-3.5-flash-lite,gemini-3.8-flash
   ```
   *(Jangan pernah membagikan atau meng-commit file `.env` yang berisi API key sungguhan).*

6. **Jalankan migrasi basis data dan seeder:**
   ```bash
   php artisan migrate --seed
   ```

7. **Buat symbolic link untuk direktori penyimpanan publik:**
   ```bash
   php artisan storage:link
   ```

8. **Kompilasi aset frontend:**
   ```bash
   npm run build
   # Atau untuk mode pengembangan: npm run dev
   ```

9. **Jalankan server aplikasi:**
   ```bash
   php artisan serve
   ```
   Aplikasi dapat diakses melalui browser pada alamat `http://127.0.0.1:8000` (atau port yang ditentukan).

---

## 5. Akun Demo

> [!NOTE]
> Kredensial akun untuk pengujian dan evaluasi juri:

| Peran (Role) | Email | Password | Keterangan |
|---|---|---|---|
| **Admin** | *(akan diisi)* | *(akan diisi)* | Akses penuh ke Dashboard Admin (`/admin`) dan Statistik (`/admin/statistik`) |
| **Warga** | *(akan diisi)* | *(akan diisi)* | Akses pelaporan, daftar laporan, dan detail riwayat status |

*(Untuk keperluan pengujian lokal mandiri, pengguna baru dapat langsung didaftarkan melalui halaman registrasi `/register`).*

---

## 6. Pengujian Otomatis (Automated Testing)

Aplikasi dilengkapi dengan rangkaian pengujian komprehensif (*unit & feature tests*) yang dapat dijalankan secara instan:

```bash
php artisan test
```

### Cakupan Pengujian (69 Tests, 295 Assertions):
- **Alur Pelaporan (`ReportTest`):** Pembuatan laporan warga, analisis multimodal AI, fallback kegagalan API, penolakan berkas non-gambar, pengecilan foto $\le$ 1280 px, fallback jika resize gagal, perlindungan throttle, dan proteksi hak akses `ReportPolicy`.
- **Evaluasi AI & Relevansi (`AiAnalyzerTest`):** Pengujian kesesuaian foto vs deskripsi via `Http::fake` (foto sampah + deskripsi tidak sesuai, foto bukan sampah, keduanya sesuai, dan deskripsi netral).
- **Model Cadangan AI (`AiAnalyzerFallbackTest`):** Mocking via `Http::fake` untuk memastikan transisi saat model pertama merespons 404, 429, 500, 502, 503, hingga kepastian alur laporan tetap berhasil tersimpan.
- **Panel Admin (`AdminReportTest`):** Hak akses otorisasi admin (warga 403), filter tabel multi-kriteria (termasuk `ai_status`), penandaan peta Leaflet, verifikasi dan koreksi admin tanpa menimpa saran AI asli, penanda visual `ai_status = gagal`, serta aksi analisis ulang AI (`reports.reanalyze`).
- **Riwayat Status (`ReportTimelineTest`):** Tampilan linimasa urut waktu, penyamaran identitas admin ("Admin"), serta pemisahan catatan publik vs internal.
- **Halaman Statistik (`AdminStatisticsTest`):** Penghitungan kartu KPI, rata-rata durasi penyelesaian di tingkat SQL, pengujian grafik kategori efektif, grafik mingguan 8 minggu terakhir, serta ketahanan halaman saat belum ada data.

---

## 7. Batasan yang Diketahui (Known Limitations)

1. **Batas Kuota Gemini Tier Gratis:** Pada tier gratis Google AI Studio, model memiliki batas permintaan per menit (RPM) dan per hari (RPD). Aplikasi mengantisipasi hal ini dengan fitur multi-model fallback dan pembatasan laju pengiriman laporan (*rate limiting*).
2. **Ketersediaan Penamaan Model:** Google dapat memperbarui atau mendepresiasi penamaan model sewaktu-waktu. Penamaan model dipusatkan pada `.env` (`GEMINI_MODELS`) sehingga dapat diperbarui tanpa mengubah kode program.
3. **Hasil AI Bersifat Rekomendasi:** Analisis AI dirancang sebagai alat bantu penilai awal (*first-line filter*). Validasi akhir dan penugasan armada kebersihan tetap berada di bawah wewenang admin/petugas melalui antarmuka koreksi admin.
4. **Ketergantungan Ekstensi GD:** Fitur kompresi gambar mengandalkan ekstensi GD bawaan PHP. Jika ekstensi tidak terpasang di server, sistem akan mengaktifkan mode *fallback* menyimpan berkas asli secara otomatis.

---

## 8. Pengungkapan Penggunaan AI (AI Disclosure)

Pengembangan platform ini memanfaatkan bantuan kecerdasan buatan (*AI Agent*) dalam proses *pair programming*, perancangan pengujian otomatis, serta integrasi layanan Gemini multimodal. Dokumen transparansi pengungkapan penggunaan AI dapat dipelajari secara lengkap pada:
👉 [**`docs/ai-disclosure.md`**](docs/ai-disclosure.md)

---

## 9. Dependensi & Aset Pihak Ketiga

| Komponen / Pustaka | Penggunaan | Lisensi |
|---|---|---|
| **Laravel Framework** | Kerangka kerja aplikasi web backend | [MIT License](https://github.com/laravel/framework/blob/master/LICENSE.md) |
| **Laravel Breeze** | Scaffolding autentikasi berbasis Blade | [MIT License](https://github.com/laravel/breeze/blob/1.x/LICENSE.md) |
| **Tailwind CSS** | Kerangka kerja utilitas antarmuka visual | [MIT License](https://github.com/tailwindlabs/tailwindcss/blob/master/LICENSE) |
| **Leaflet JS** | Pustaka peta interaktif | [BSD 2-Clause License](https://github.com/Leaflet/Leaflet/blob/main/LICENSE) |
| **OpenStreetMap** | Penyedia peta ubin (*map tiles*) | [ODbL & CC BY-SA](https://www.openstreetmap.org/copyright) |
| **Chart.js** (v4.4.7) | Pustaka visualisasi grafik statistik | [MIT License](https://github.com/chartjs/Chart.js/blob/master/LICENSE.md) |
| **Google Gemini API** | Layanan analisis multimodal | [Google Generative AI Terms of Service](https://ai.google.dev/terms) |
