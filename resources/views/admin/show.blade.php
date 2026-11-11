<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.dashboard') }}" class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg border transition">
                    &larr; Kembali ke Dashboard Admin
                </a>
                <h2 class="font-semibold text-lg text-gray-800 leading-tight">
                    Detail & Verifikasi Laporan #{{ $report->id }}
                </h2>
            </div>
            <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold
                {{ $report->status === 'perlu_verifikasi' ? 'bg-amber-100 text-amber-800 border border-amber-300' :
                   ($report->status === 'selesai' ? 'bg-green-100 text-green-800' :
                   ($report->status === 'diproses' ? 'bg-blue-100 text-blue-800' :
                   ($report->status === 'ditolak' ? 'bg-gray-200 text-gray-700' : 'bg-purple-100 text-purple-800'))) }}">
                Status: {{ ucwords(str_replace('_', ' ', $report->status)) }}
            </span>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Flash Notification --}}
        @if (session('status'))
            <div class="p-4 rounded-xl text-sm bg-green-50 text-green-800 border border-green-200 flex items-center justify-between shadow-sm">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (session('warning'))
            <div class="p-4 rounded-xl text-sm bg-red-50 text-red-800 border border-red-200 flex items-center justify-between shadow-sm">
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        {{-- Banner jika AI Gagal Memproses --}}
        @if ($report->ai_status === 'gagal')
            <div class="p-4 rounded-xl bg-red-50 border border-red-300 text-red-900 text-sm shadow-sm flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="text-xl">⚠️</span>
                    <div>
                        <h4 class="font-semibold text-red-900">Analisis AI Belum Tersedia (AI Gagal)</h4>
                        <p class="text-xs text-red-700 mt-0.5">
                            Laporan ini tidak berhasil dianalisis oleh AI saat dikirim (kemungkinan kuota API Gemini habis atau gangguan koneksi). Silakan jalankan analisis ulang sekarang menggunakan tombol di samping, atau lengkapi verifikasi secara manual.
                        </p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.reports.reanalyze', $report) }}">
                    @csrf
                    <button type="submit" class="whitespace-nowrap bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-2 px-3.5 rounded-lg shadow-sm transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Analisis Ulang AI &rarr;
                    </button>
                </form>
            </div>
        @endif

        {{-- Banner jika Kemungkinan Duplikat --}}
        @if ($report->duplikat_dari)
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-900 text-sm shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="text-xl">🔁</span>
                        <div>
                            <h4 class="font-semibold text-rose-900">Kemungkinan Duplikat Terdeteksi</h4>
                            <p class="text-xs text-rose-700 mt-0.5">
                                Sistem mendeteksi laporan ini berada dalam radius &le; {{ config('laporan.duplikat.jarak_meter', 50) }} meter dari laporan lain yang belum selesai.
                                Laporan asli acuan:
                                <a href="{{ route('admin.reports.show', $report->duplikat_dari) }}" class="font-bold underline text-rose-900 hover:text-rose-950">
                                    Laporan #{{ $report->duplikat_dari }} ({{ $report->duplikatAsli->judul ?? 'Lihat Laporan' }}) &rarr;
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-2 border-t border-rose-200">
                    <form method="POST" action="{{ route('admin.reports.bukanDuplikat', $report) }}" onsubmit="return confirm('Hapus tanda duplikat dari laporan ini?');">
                        @csrf
                        <button type="submit" class="bg-white hover:bg-gray-100 text-gray-800 text-xs font-semibold py-1.5 px-3 rounded-lg border border-gray-300 shadow-sm transition">
                            Bukan duplikat
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.reports.selesaiDuplikat', $report) }}" onsubmit="return confirm('Tandai laporan ini selesai sebagai duplikat? Status akan diubah menjadi selesai tanpa catatan ke pelapor.');">
                        @csrf
                        <button type="submit" class="bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold py-1.5 px-3 rounded-lg shadow-sm transition">
                            Tandai selesai sebagai duplikat
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Banner Peringatan jika Perlu Verifikasi / Tidak Relevan --}}
        @if ($report->relevan === false || $report->status === 'perlu_verifikasi')
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 text-sm shadow-sm flex items-start gap-3">
                <span class="text-xl">⚠️</span>
                <div>
                    <h4 class="font-semibold">Perhatian: Laporan Memerlukan Verifikasi Admin</h4>
                    <p class="text-xs text-amber-800 mt-0.5">
                        Sistem AI menandai laporan ini tidak relevan atau foto tidak sesuai deskripsi. Harap teliti foto dan gunakan formulir koreksi di samping kanan jika perlu memperbaiki kategori atau tingkat keparahan.
                    </p>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Kolom Kiri / Utama (2 Kolom): Rincian Laporan, Foto, Peta & Analisis AI --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Informasi Utama Laporan --}}
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                    <div>
                        <h1 class="text-xl font-bold text-gray-900">{{ $report->judul }}</h1>
                        <p class="text-xs text-gray-500 mt-1">
                            Dilaporkan oleh: <strong class="text-gray-700">{{ $report->user->name ?? 'Anonim' }}</strong> ({{ $report->user->email ?? '-' }})
                            pada {{ $report->created_at->format('d F Y, H:i') }} WIB
                        </p>
                    </div>

                    {{-- Foto Laporan --}}
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Foto Lapangan:</span>
                        <div class="rounded-lg overflow-hidden border border-gray-200 bg-gray-50 flex justify-center">
                            <img src="{{ asset('storage/'.$report->foto_path) }}" class="max-h-96 w-auto object-contain" alt="Foto Laporan">
                        </div>
                    </div>

                    {{-- Deskripsi Warga --}}
                    <div>
                        <span class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Deskripsi Warga:</span>
                        <p class="text-sm text-gray-700 bg-gray-50 p-3 rounded-lg border border-gray-100">
                            {{ $report->deskripsi ?: 'Tidak ada deskripsi tertulis dari pelapor.' }}
                        </p>
                    </div>

                    {{-- Lokasi dan Peta Mini --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Lokasi Koordinat:</span>
                            <span class="text-xs font-mono text-gray-600">{{ $report->lat }}, {{ $report->lng }}</span>
                        </div>
                        <div id="detail-map" class="h-56 rounded-lg border border-gray-200"></div>
                    </div>
                </div>

                {{-- Kotak Perbandingan: Saran AI vs Dikoreksi Admin --}}
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                    <h3 class="font-semibold text-base text-gray-900 border-b pb-2">
                        Klasifikasi Laporan: Saran AI vs Dikoreksi Admin
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Perbandingan Kategori --}}
                        <div class="border rounded-xl p-4 bg-gray-50/50 space-y-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-700 block">Kategori</span>
                            <div class="text-xs space-y-1">
                                <div class="flex items-center justify-between py-1 border-b border-gray-200">
                                    <span class="text-gray-500 font-medium">Saran AI:</span>
                                    <span class="font-semibold {{ $report->ai_status === 'gagal' ? 'text-red-600 italic text-[11px]' : 'text-gray-800' }}">
                                        {{ $report->ai_status === 'gagal' ? 'AI Gagal' : ucwords(str_replace('_', ' ', $report->kategori ?: '-')) }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <span class="text-gray-500 font-medium">Dikoreksi admin:</span>
                                    @if ($report->kategori_admin)
                                        <span class="font-bold text-blue-700">
                                            {{ ucwords(str_replace('_', ' ', $report->kategori_admin)) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 italic">Belum dikoreksi</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Perbandingan Tingkat Keparahan --}}
                        <div class="border rounded-xl p-4 bg-gray-50/50 space-y-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-gray-700 block">Tingkat Keparahan</span>
                            <div class="text-xs space-y-1">
                                <div class="flex items-center justify-between py-1 border-b border-gray-200">
                                    <span class="text-gray-500 font-medium">Saran AI:</span>
                                    <span class="font-semibold {{ $report->ai_status === 'gagal' ? 'text-red-600 italic text-[11px]' : 'text-gray-800' }}">
                                        {{ $report->ai_status === 'gagal' ? 'AI Gagal' : ($report->tingkat_keparahan ? $report->tingkat_keparahan . ' / 5' : '-') }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between py-1">
                                    <span class="text-gray-500 font-medium">Dikoreksi admin:</span>
                                    @if ($report->keparahan_admin)
                                        <span class="font-bold text-blue-700">
                                            {{ $report->keparahan_admin }} / 5
                                        </span>
                                    @else
                                        <span class="text-gray-400 italic">Belum dikoreksi</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($report->editor)
                        <p class="text-[11px] text-gray-500 italic">
                            * Terakhir dikoreksi oleh admin: <strong>{{ $report->editor->name }}</strong> ({{ $report->editor->email }})
                        </p>
                    @endif

                    {{-- Detail Lengkap Analisis AI --}}
                    <div class="pt-2 border-t space-y-3">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-semibold text-gray-700">Analisis Relevansi AI:</span>
                            @if ($report->ai_status === 'berhasil')
                                <span class="font-semibold {{ $report->relevan ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $report->relevan ? 'Relevan' : 'Tidak Relevan' }}
                                    @if ($report->kepercayaan !== null)
                                        <span class="text-gray-500 font-normal">({{ $report->kepercayaan }}% yakin)</span>
                                    @endif
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-red-100 text-red-800 border border-red-200">
                                    Gagal Diproses
                                </span>
                            @endif
                        </div>

                        @if ($report->ai_status === 'berhasil' && $report->ringkasan_ai)
                            <div class="bg-blue-50/60 border border-blue-100 p-3 rounded-lg text-xs text-blue-900">
                                <strong class="block mb-0.5">Ringkasan AI:</strong>
                                <p>{{ $report->ringkasan_ai }}</p>
                            </div>
                        @elseif ($report->ai_status === 'gagal')
                            <div class="bg-red-50 border border-red-200 p-3 rounded-lg text-xs text-red-900 space-y-1">
                                <strong class="block font-semibold">Status AI: Gagal Memproses</strong>
                                <p class="text-red-700">Analisis otomatis belum tersedia untuk laporan ini (kemungkinan karena kuota API Gemini habis atau gangguan koneksi saat pengiriman awal).</p>
                            </div>
                        @endif

                        {{-- Tombol Analisis Ulang AI --}}
                        <div class="pt-1 flex items-center justify-end">
                            <form method="POST" action="{{ route('admin.reports.reanalyze', $report) }}" onsubmit="return confirm('Jalankan analisis ulang AI untuk laporan ini?');">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg shadow-sm transition {{ $report->ai_status === 'gagal' ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700 border border-gray-300' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                    {{ $report->ai_status === 'gagal' ? 'Analisis Ulang AI Sekarang' : 'Analisis Ulang AI' }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Daftar Laporan Terdekat di Sekitar Lokasi --}}
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-3">
                    <div class="flex items-center justify-between border-b pb-2">
                        <div>
                            <h3 class="font-semibold text-base text-gray-900">
                                Daftar Laporan Terdekat (&le; {{ config('laporan.duplikat.jarak_meter', 50) }} meter)
                            </h3>
                            <p class="text-xs text-gray-500">
                                Laporan aktif di sekitar lokasi ini dalam 30 hari terakhir.
                            </p>
                        </div>
                        <span class="text-xs bg-gray-100 text-gray-700 font-semibold px-2 py-0.5 rounded-full">
                            {{ count($nearbyReports ?? []) }} laporan
                        </span>
                    </div>

                    @if (!empty($nearbyReports) && count($nearbyReports) > 0)
                        <div class="divide-y divide-gray-100 text-xs">
                            @foreach ($nearbyReports as $nr)
                                <div class="py-2.5 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ asset('storage/'.$nr->foto_path) }}" class="w-10 h-10 object-cover rounded-md border" alt="Foto">
                                        <div>
                                            <a href="{{ route('admin.reports.show', $nr) }}" class="font-semibold text-gray-900 hover:text-blue-600 hover:underline">
                                                #{{ $nr->id }} - {{ $nr->judul }}
                                            </a>
                                            <div class="text-[11px] text-gray-500 flex items-center gap-2 mt-0.5">
                                                <span>{{ ucwords(str_replace('_', ' ', $nr->effective_kategori ?: '-')) }}</span>
                                                <span>&bull;</span>
                                                <span>Keparahan: {{ $nr->effective_keparahan ?? '-' }}/5</span>
                                                <span>&bull;</span>
                                                <span>{{ $nr->created_at->format('d M Y, H:i') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right whitespace-nowrap">
                                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                                            {{ $nr->jarak_meter }} m
                                        </span>
                                        <span class="block text-[10px] text-gray-400 mt-0.5 capitalize">
                                            {{ str_replace('_', ' ', $nr->status) }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400 italic py-2">
                            Tidak ada laporan aktif lain yang ditemukan dalam radius {{ config('laporan.duplikat.jarak_meter', 50) }} meter.
                        </p>
                    @endif
                </div>

            </div>

            {{-- Kolom Kanan (1 Kolom): Form Verifikasi & Koreksi Admin + Riwayat Logs --}}
            <div class="space-y-6">

                {{-- Form Verifikasi & Koreksi Admin --}}
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                    <h3 class="font-semibold text-base text-gray-900 border-b pb-2">
                        Verifikasi & Koreksi Admin
                    </h3>

                    <form method="POST" action="{{ route('admin.reports.updateStatus', $report) }}" class="space-y-4">
                        @csrf
                        @method('PATCH')

                        {{-- Pilihan Status --}}
                        <div>
                            <label for="status" class="block text-xs font-semibold text-gray-700 mb-1">Status Laporan *</label>
                            <select id="status" name="status" required class="w-full text-xs border-gray-300 rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
                                <option value="baru" {{ old('status', $report->status) === 'baru' ? 'selected' : '' }}>Baru</option>
                                <option value="perlu_verifikasi" {{ old('status', $report->status) === 'perlu_verifikasi' ? 'selected' : '' }}>Perlu Verifikasi</option>
                                <option value="diproses" {{ old('status', $report->status) === 'diproses' ? 'selected' : '' }}>Diproses (Ditindaklanjuti)</option>
                                <option value="selesai" {{ old('status', $report->status) === 'selesai' ? 'selected' : '' }}>Selesai (Sampah Dibersihkan)</option>
                                <option value="ditolak" {{ old('status', $report->status) === 'ditolak' ? 'selected' : '' }}>Ditolak (Spam / Bukan Sampah)</option>
                            </select>
                            @error('status') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Form Koreksi Kategori Admin (dari config/domain.php) --}}
                        <div>
                            <label for="kategori_admin" class="block text-xs font-semibold text-gray-700 mb-1">
                                Koreksi Kategori (Admin)
                            </label>
                            <select id="kategori_admin" name="kategori_admin" class="w-full text-xs border-gray-300 rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- Tetap Gunakan Saran AI ({{ ucwords(str_replace('_', ' ', $report->kategori ?: '-')) }}) --</option>
                                @foreach ($kategoriList as $kat)
                                    <option value="{{ $kat }}" {{ old('kategori_admin', $report->kategori_admin) === $kat ? 'selected' : '' }}>
                                        {{ ucwords(str_replace('_', ' ', $kat)) }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="text-[10px] text-gray-500">Memperbaiki kategori tanpa menimpa data saran AI asli.</span>
                            @error('kategori_admin') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Form Koreksi Keparahan Admin (1-5) --}}
                        <div>
                            <label for="keparahan_admin" class="block text-xs font-semibold text-gray-700 mb-1">
                                Koreksi Tingkat Keparahan (Admin: 1 - 5)
                            </label>
                            <select id="keparahan_admin" name="keparahan_admin" class="w-full text-xs border-gray-300 rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">-- Tetap Gunakan Saran AI ({{ $report->tingkat_keparahan ? $report->tingkat_keparahan . '/5' : '-' }}) --</option>
                                <option value="5" {{ (string) old('keparahan_admin', $report->keparahan_admin) === '5' ? 'selected' : '' }}>5 (Sangat Mendesak / Darurat)</option>
                                <option value="4" {{ (string) old('keparahan_admin', $report->keparahan_admin) === '4' ? 'selected' : '' }}>4 (Tinggi)</option>
                                <option value="3" {{ (string) old('keparahan_admin', $report->keparahan_admin) === '3' ? 'selected' : '' }}>3 (Sedang)</option>
                                <option value="2" {{ (string) old('keparahan_admin', $report->keparahan_admin) === '2' ? 'selected' : '' }}>2 (Rendah)</option>
                                <option value="1" {{ (string) old('keparahan_admin', $report->keparahan_admin) === '1' ? 'selected' : '' }}>1 (Sangat Rendah)</option>
                            </select>
                            <span class="text-[10px] text-gray-500">Nilai keparahan admin akan dipakai untuk prioritas pengurutan laporan.</span>
                            @error('keparahan_admin') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Koreksi Relevansi --}}
                        <div>
                            <label for="relevan" class="block text-xs font-semibold text-gray-700 mb-1">Validasi Relevansi Foto</label>
                            <select id="relevan" name="relevan" class="w-full text-xs border-gray-300 rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">
                                <option value="1" {{ old('relevan', $report->relevan ? '1' : '0') === '1' ? 'selected' : '' }}>Foto Relevan (Benar Sampah)</option>
                                <option value="0" {{ old('relevan', $report->relevan ? '1' : '0') === '0' ? 'selected' : '' }}>Foto Tidak Relevan (Bukan Sampah / Palsu)</option>
                            </select>
                        </div>

                        {{-- Catatan Perubahan --}}
                        <div>
                            <label for="catatan" class="block text-xs font-semibold text-gray-700 mb-1">Catatan Admin (Tersimpan ke Log) *</label>
                            <textarea id="catatan" name="catatan" rows="3" required placeholder="Contoh: Kategori disesuaikan ke sampah plastik, tim dinas kebersihan dikirim..." class="w-full text-xs border-gray-300 rounded-lg p-2.5 focus:ring-blue-500 focus:border-blue-500">{{ old('catatan') }}</textarea>
                            @error('catatan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Checkbox Tampilkan Catatan ke Pelapor --}}
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="tampil_ke_pelapor" name="tampil_ke_pelapor" value="1" {{ old('tampil_ke_pelapor') ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                            <label for="tampil_ke_pelapor" class="text-xs text-gray-700 font-medium cursor-pointer">
                                Tampilkan catatan ini ke pelapor
                            </label>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg text-xs shadow-sm transition">
                            Simpan Perubahan & Koreksi
                        </button>
                    </form>
                </div>

                {{-- Riwayat Perubahan Status (status_logs) --}}
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-3">
                    <h3 class="font-semibold text-base text-gray-900 border-b pb-2">
                        Riwayat Penanganan (Audit Trail)
                    </h3>

                    <div class="space-y-3">
                        @forelse ($report->logs as $log)
                            <div class="p-3 bg-gray-50 rounded-lg border border-gray-100 text-xs space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold px-2 py-0.5 rounded text-[10px]
                                        {{ $log->status === 'perlu_verifikasi' ? 'bg-amber-100 text-amber-800' :
                                           ($log->status === 'selesai' ? 'bg-green-100 text-green-800' :
                                           ($log->status === 'diproses' ? 'bg-blue-100 text-blue-800' :
                                           ($log->status === 'ditolak' ? 'bg-gray-200 text-gray-700' : 'bg-purple-100 text-purple-800'))) }}">
                                        {{ ucwords(str_replace('_', ' ', $log->status)) }}
                                    </span>
                                    <span class="text-gray-400 text-[11px]">{{ $log->created_at->format('d M Y, H:i') }}</span>
                                </div>
                                <p class="text-gray-700 pt-1">{{ $log->catatan ?: '-' }}</p>
                                <div class="flex items-center justify-between pt-1">
                                    <span class="text-[10px] text-gray-400">Oleh: {{ $log->pengubah->name ?? 'Petugas' }}</span>
                                    @if ($log->tampil_ke_pelapor)
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[9px] bg-green-50 text-green-700 border border-green-200">Terlihat pelapor</span>
                                    @else
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[9px] bg-gray-100 text-gray-500">Internal</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 text-center py-3">Belum ada riwayat perubahan status.</p>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Leaflet Map Scripts untuk Detail --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lat = {{ $report->lat }};
            const lng = {{ $report->lng }};

            const map = L.map('detail-map').setView([lat, lng], 15);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            L.marker([lat, lng]).addTo(map)
                .bindPopup('<b>{{ $report->judul }}</b><br>Koordinat: ' + lat + ', ' + lng)
                .openPopup();
        });
    </script>
</x-app-layout>
