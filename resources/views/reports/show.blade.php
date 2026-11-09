<x-app-layout>
    <div class="max-w-xl mx-auto p-4 space-y-3">
        @if (session('status')) <p class="text-green-700">{{ session('status') }}</p> @endif
        <h1 class="text-xl font-semibold">{{ $report->judul }}</h1>
        <img src="{{ asset('storage/'.$report->foto_path) }}" class="rounded max-h-64" alt="Foto laporan">
        <p>{{ $report->deskripsi }}</p>
        <p>Status: {{ $report->status }}</p>
        @if ($report->ai_status === 'berhasil')
            <p class="text-sm">Saran AI: {{ $report->kategori }}, keparahan {{ $report->tingkat_keparahan }}/5.
               {{ $report->ringkasan_ai }}</p>
        @else
            <p class="text-sm text-gray-500">Analisis AI belum tersedia.</p>
        @endif
    </div>
</x-app-layout>