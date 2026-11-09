<?php

// Satu-satunya tempat yang diganti saat tema lomba berubah.
return [
    'nama' => 'Lapor Sampah',
    'kategori' => ['sampah_rumah_tangga', 'sampah_plastik', 'limbah_konstruksi', 'sampah_organik', 'lainnya'],
    'prompt' => 'Kamu asisten analisis laporan sampah liar dari warga. '
        .'Dari foto dan deskripsi, tentukan kategori (pilih dari daftar), '
        .'tingkat_keparahan 1-5 (5 = sangat mendesak), ringkasan maksimal 2 kalimat bahasa Indonesia, '
        .'dan alasan singkat. Jika foto tidak relevan, pilih kategori "lainnya" dan keparahan 1.',
];