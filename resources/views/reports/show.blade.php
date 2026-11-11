<x-app-layout>
    <div class="max-w-xl mx-auto p-4 space-y-4">
        {{-- Pesan Status / Flash Message --}}
        @if (session('status'))
            <div class="p-3 rounded-lg text-sm {{ str_contains(session('status'), 'tidak sesuai') ? 'bg-amber-50 text-amber-800 border border-amber-300' : 'bg-green-50 text-green-700 border border-green-300' }}">
                {{ session('status') }}
            </div>
        @endif

        {{-- Peringatan jika foto dinilai tidak relevan / perlu verifikasi --}}
        @if ($report->relevan === false || $report->status === 'perlu_verifikasi')
            <div class="p-3 bg-amber-50 border border-amber-300 text-amber-800 rounded-lg text-sm">
                <strong>Peringatan:</strong> Foto dan deskripsi tampak tidak sesuai. Laporan ini akan diverifikasi admin.
            </div>
        @endif

        {{-- Pemberitahuan netral jika ada laporan serupa di sekitar lokasi --}}
        @if ($report->duplikat_dari)
            <div class="p-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Ada laporan serupa di sekitar lokasi ini, laporan Anda tetap kami terima.</span>
            </div>
        @endif

        <h1 class="text-xl font-semibold">{{ $report->judul }}</h1>
        <img src="{{ asset('storage/'.$report->foto_path) }}" class="rounded max-h-64 object-cover" alt="Foto laporan">
        <p class="text-gray-700">{{ $report->deskripsi }}</p>

        <div class="text-sm">
            <span class="font-medium">Status:</span>
            <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $report->status === 'perlu_verifikasi' ? 'bg-amber-100 text-amber-800' : ($report->status === 'selesai' ? 'bg-green-100 text-green-800' : ($report->status === 'diproses' ? 'bg-blue-100 text-blue-800' : ($report->status === 'ditolak' ? 'bg-gray-200 text-gray-700' : 'bg-purple-100 text-purple-800'))) }}">
                {{ ucwords(str_replace('_', ' ', $report->status)) }}
            </span>
        </div>

        {{-- Hasil Analisis AI --}}
        @if ($report->ai_status === 'berhasil')
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-sm space-y-1.5">
                <p class="font-medium text-gray-800">Hasil Analisis AI:</p>
                <p>
                    <span class="text-gray-600">Relevansi:</span>
                    @if ($report->relevan)
                        <span class="text-green-700 font-semibold">Relevan</span>
                    @else
                        <span class="text-amber-700 font-semibold">Tidak Relevan</span>
                    @endif
                    @if ($report->kepercayaan !== null)
                        <span class="text-gray-500 text-xs">({{ $report->kepercayaan }}% yakin)</span>
                    @endif
                </p>
                <p>
                    <span class="text-gray-600">Saran:</span>
                    {{ ucwords(str_replace('_', ' ', $report->kategori ?: '-')) }}, keparahan {{ $report->tingkat_keparahan }}/5.
                </p>
                <p class="text-gray-600">{{ $report->ringkasan_ai }}</p>
            </div>
        @else
            <p class="text-sm text-gray-500">Analisis AI belum tersedia.</p>
        @endif

        {{-- Timeline Riwayat Status --}}
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm space-y-3">
            <h2 class="font-semibold text-base text-gray-900 border-b pb-2">
                Riwayat Status Laporan
            </h2>

            <ol class="relative border-l border-gray-200 ml-3 space-y-5">
                {{-- Entri Awal: Laporan dikirim --}}
                <li class="pl-4 relative">
                    <span class="absolute -left-[7px] top-1.5 w-3 h-3 bg-blue-500 rounded-full border-2 border-white ring-1 ring-blue-300"></span>
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-gray-900">Laporan dikirim</span>
                        <time class="text-gray-400 text-[11px]">{{ $report->created_at->format('d M Y, H:i') }}</time>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">Laporan telah dibuat dan masuk ke sistem.</p>
                </li>

                {{-- Riwayat Perubahan Status oleh Admin --}}
                @foreach ($report->logs as $log)
                    <li class="pl-4 relative">
                        <span class="absolute -left-[7px] top-1.5 w-3 h-3 rounded-full border-2 border-white ring-1
                            {{ $log->status === 'selesai' ? 'bg-green-500 ring-green-300' :
                               ($log->status === 'diproses' ? 'bg-blue-500 ring-blue-300' :
                               ($log->status === 'ditolak' ? 'bg-red-500 ring-red-300' :
                               ($log->status === 'perlu_verifikasi' ? 'bg-amber-500 ring-amber-300' : 'bg-purple-500 ring-purple-300'))) }}">
                        </span>

                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-900">
                                    Status: {{ ucwords(str_replace('_', ' ', $log->status)) }}
                                </span>
                                <span class="text-[10px] text-gray-400 font-medium">oleh Admin</span>
                            </div>
                            <time class="text-gray-400 text-[11px]">{{ $log->created_at->format('d M Y, H:i') }}</time>
                        </div>

                        {{-- Catatan hanya tampil jika tampil_ke_pelapor = true --}}
                        @if ($log->tampil_ke_pelapor && $log->catatan)
                            <div class="mt-1.5 text-xs text-gray-700 bg-gray-50 p-2.5 rounded-lg border border-gray-100">
                                <span class="font-medium text-gray-800 block text-[11px] mb-0.5">Catatan Admin:</span>
                                {{ $log->catatan }}
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>

        <div>
            <a href="{{ route('reports.index') }}" class="text-sm text-green-700 underline">&larr; Kembali ke daftar laporan</a>
        </div>
    </div>
</x-app-layout>
