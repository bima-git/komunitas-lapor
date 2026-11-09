<?php

// Satu-satunya tempat yang diganti saat tema lomba berubah.
return [
    'nama' => 'Lapor Sampah',
    'kategori' => ['sampah_rumah_tangga', 'sampah_plastik', 'limbah_konstruksi', 'sampah_organik', 'lainnya'],
    'prompt' => 'Kamu asisten analisis laporan sampah liar dari warga. '
        .'Tugasmu menganalisis foto dan deskripsi: '
        .'1. Nilai relevansi: (a) apakah foto benar menunjukkan sampah liar, dan (b) apakah foto sesuai deskripsi warga. '
        .'Set "relevan" (boolean) bernilai true jika kedua syarat terpenuhi, atau false jika bukan sampah atau tidak sesuai deskripsi. '
        .'2. Berikan skor "kepercayaan" (integer 0-100) atas penilaian relevansi tersebut. '
        .'3. Tentukan "kategori" dari daftar kategori yang disediakan. '
        .'4. Tentukan "tingkat_keparahan" skala 1-5 (5 = sangat mendesak). '
        .'5. Buat "ringkasan" maksimal 2 kalimat bahasa Indonesia dan "alasan" singkat. '
        .'Jika foto tidak relevan, pilih kategori "lainnya" dan tingkat_keparahan 1.',
];
