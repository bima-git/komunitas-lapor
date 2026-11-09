<x-app-layout>
    <div class="max-w-xl mx-auto p-4">
        <a href="{{ route('reports.create') }}" class="text-green-700 underline">Buat laporan baru</a>
        <ul class="mt-4 space-y-2">
            @forelse ($reports as $r)
                <li><a href="{{ route('reports.show', $r) }}" class="underline">{{ $r->judul }}</a> ({{ $r->status }})</li>
            @empty
                <li>Belum ada laporan.</li>
            @endforelse
        </ul>
        {{ $reports->links() }}
    </div>
</x-app-layout>