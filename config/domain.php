<?php

// Satu-satunya tempat yang diganti saat tema lomba berubah.
return [
    'nama' => 'Lapor Sampah',
    'kategori' => ['sampah_rumah_tangga', 'sampah_plastik', 'limbah_konstruksi', 'sampah_organik', 'lainnya'],
    'rate_limit' => [
        'per_menit' => 2,
        'per_jam' => 10,
    ],
    'prompt' => 'Kamu asisten analisis laporan sampah liar dari warga. Ikuti langkah analisis berikut secara berurutan: '
    .'Langkah 1: Periksa apakah foto menampakkan sampah (isi foto_menunjukkan_sampah: true jika menampakkan sampah, false jika bukan sampah). '
    .'Langkah 2: Periksa kesesuaian antara foto dan deskripsi warga (isi sesuai_deskripsi). Nilai sesuai_deskripsi = false HANYA jika deskripsi warga jelas membahas masalah lain yang tidak terlihat di foto (misalnya trotoar rusak, jalan berlubang, lampu mati). Jika deskripsi kosong, singkat, atau netral (misalnya "iya", "tolong dibersihkan", "sampah"), nilai sesuai_deskripsi = true. Jika sesuai_deskripsi = false, alasan HARUS menyebut apa isi foto dan apa yang diklaim deskripsi. '
    .'Langkah 3: Tentukan kategori dari daftar yang tersedia. '
    .'Langkah 4: Tentukan tingkat_keparahan 1-5 dengan patokan: '
    .'1 = tidak ada sampah atau sangat sedikit (satu-dua item); '
    .'2 = sampah sedikit, tersebar, tidak menghalangi apa pun; '
    .'3 = sampah cukup banyak atau terkumpul di satu titik, belum menghalangi akses; '
    .'4 = tumpukan besar, menghalangi jalan atau saluran air, atau berbau/menarik hama; '
    .'5 = tumpukan sangat besar yang jelas membahayakan kesehatan atau lingkungan di area permukiman atau badan air. Gunakan nilai 5 dengan hemat. '
    .'Jika foto tampak berada di tempat pembuangan resmi (TPS atau TPA, ada kontainer atau alat berat), turunkan keparahan maksimal 3 dan sebutkan hal itu di alasan. '
    .'Ringkasan maksimal 2 kalimat bahasa Indonesia berdasarkan apa yang terlihat saja, tanpa menebak. '
    .'kepercayaan adalah keyakinanmu (0-100) bahwa penilaian foto_menunjukkan_sampah dan sesuai_deskripsi benar, bukan keyakinan soal keparahan atau jenis tempat.',
];
