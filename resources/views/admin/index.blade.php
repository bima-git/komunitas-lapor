<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Panel Admin — Pemantauan & Verifikasi Laporan
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.statistics') }}" class="inline-flex items-center gap-1.5 text-xs bg-blue-600 hover:bg-blue-700 text-white font-medium px-3 py-1.5 rounded-lg shadow-sm transition">
                    📊 Statistik & Kinerja
                </a>
                <span class="text-xs bg-gray-100 text-gray-700 px-2.5 py-1 rounded-full font-medium border">
                    Total: {{ $reports->total() }} Laporan
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Peta Sebaran Leaflet --}}
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-gray-100 gap-2 mb-3">
                <div>
                    <h3 class="font-semibold text-gray-900 text-base">Peta Sebaran Titik Sampah</h3>
                    <p class="text-xs text-gray-500">Titik lokasi laporan berdasarkan filter aktif. Warna marker berdasarkan tingkat keparahan (memperhitungkan koreksi admin).</p>
                </div>
                {{-- Legend / Keterangan Warna --}}
                <div class="flex items-center gap-3 text-xs text-gray-600 flex-wrap">
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-600 inline-block"></span> 5 (Sangat Mendesak)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-orange-500 inline-block"></span> 4 (Tinggi)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-yellow-500 inline-block"></span> 3 (Sedang)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> 2 (Rendah)</span>
                    <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span> 1 (Sangat Rendah)</span>
                </div>
            </div>
            <div id="admin-map" class="h-80 w-full rounded-lg border border-gray-200 z-0"></div>
        </div>

        {{-- Filter Box --}}
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
            <form method="GET" action="{{ route('admin.dashboard') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                {{-- Filter Status --}}
                <div>
                    <label for="status" class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status" class="w-full text-xs border-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Status</option>
                        <option value="baru" {{ request('status') === 'baru' ? 'selected' : '' }}>Baru</option>
                        <option value="perlu_verifikasi" {{ request('status') === 'perlu_verifikasi' ? 'selected' : '' }}>Perlu Verifikasi</option>
                        <option value="diproses" {{ request('status') === 'diproses' ? 'selected' : '' }}>Diproses</option>
                        <option value="selesai" {{ request('status') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                        <option value="ditolak" {{ request('status') === 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                    </select>
                </div>

                {{-- Filter Kategori --}}
                <div>
                    <label for="kategori" class="block text-xs font-medium text-gray-700 mb-1">Kategori</label>
                    <select id="kategori" name="kategori" class="w-full text-xs border-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Kategori</option>
                        @foreach ($kategoriList as $kat)
                            <option value="{{ $kat }}" {{ request('kategori') === $kat ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_', ' ', $kat)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Filter Keparahan --}}
                <div>
                    <label for="keparahan" class="block text-xs font-medium text-gray-700 mb-1">Tingkat Keparahan</label>
                    <select id="keparahan" name="keparahan" class="w-full text-xs border-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua Keparahan</option>
                        <option value="5" {{ request('keparahan') === '5' ? 'selected' : '' }}>5 (Sangat Mendesak)</option>
                        <option value="4" {{ request('keparahan') === '4' ? 'selected' : '' }}>4 (Tinggi)</option>
                        <option value="3" {{ request('keparahan') === '3' ? 'selected' : '' }}>3 (Sedang)</option>
                        <option value="2" {{ request('keparahan') === '2' ? 'selected' : '' }}>2 (Rendah)</option>
                        <option value="1" {{ request('keparahan') === '1' ? 'selected' : '' }}>1 (Sangat Rendah)</option>
                    </select>
                </div>

                {{-- Filter Relevansi AI --}}
                <div>
                    <label for="relevan" class="block text-xs font-medium text-gray-700 mb-1">Relevansi AI</label>
                    <select id="relevan" name="relevan" class="w-full text-xs border-gray-300 rounded-lg p-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Semua</option>
                        <option value="1" {{ request('relevan') === '1' ? 'selected' : '' }}>Relevan</option>
                        <option value="0" {{ request('relevan') === '0' ? 'selected' : '' }}>Tidak Relevan</option>
                    </select>
                </div>

                {{-- Tombol Aksi --}}
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold py-2 px-3 rounded-lg shadow-sm transition">
                        Terapkan
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold py-2 px-3 rounded-lg border transition">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Tabel Laporan --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-gray-600">
                    <thead class="bg-gray-50 text-gray-700 uppercase font-semibold border-b">
                        <tr>
                            <th class="p-3.5">Foto</th>
                            <th class="p-3.5">Judul & Pelapor</th>
                            <th class="p-3.5">Kategori</th>
                            <th class="p-3.5 text-center">Keparahan</th>
                            <th class="p-3.5 text-center">Relevansi AI</th>
                            <th class="p-3.5">Status</th>
                            <th class="p-3.5">Tanggal</th>
                            <th class="p-3.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($reports as $r)
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="p-3.5">
                                    <img src="{{ asset('storage/'.$r->foto_path) }}" class="w-12 h-12 object-cover rounded-md border" alt="Foto">
                                </td>
                                <td class="p-3.5">
                                    <a href="{{ route('admin.reports.show', $r) }}" class="font-semibold text-gray-900 hover:text-blue-600 hover:underline block text-sm">
                                        {{ $r->judul }}
                                    </a>
                                    <span class="text-[11px] text-gray-400">Oleh: {{ $r->user->name ?? 'Anonim' }}</span>
                                </td>
                                <td class="p-3.5 whitespace-nowrap">
                                    <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-[11px] font-medium block w-fit">
                                        {{ ucwords(str_replace('_', ' ', $r->effective_kategori ?: '-')) }}
                                    </span>
                                    @if ($r->kategori_admin)
                                        <span class="text-[10px] text-blue-600 font-medium block mt-0.5">Dikoreksi admin</span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    @php $keparahan = $r->effective_keparahan; @endphp
                                    @if ($keparahan)
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full font-bold text-white text-xs
                                            {{ $keparahan >= 5 ? 'bg-red-600' : ($keparahan == 4 ? 'bg-orange-500' : ($keparahan == 3 ? 'bg-yellow-500' : ($keparahan == 2 ? 'bg-blue-500' : 'bg-emerald-500'))) }}"
                                            title="{{ $r->keparahan_admin ? 'Dikoreksi admin (Saran AI: ' . ($r->tingkat_keparahan ?: '-') . ')' : 'Saran AI' }}">
                                            {{ $keparahan }}
                                        </span>
                                        @if ($r->keparahan_admin)
                                            <span class="block text-[9px] text-blue-600 font-semibold leading-tight mt-0.5">admin</span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="p-3.5 text-center">
                                    @if ($r->ai_status === 'berhasil')
                                        @if ($r->relevan)
                                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-green-100 text-green-800">
                                                Relevan ({{ $r->kepercayaan }}%)
                                            </span>
                                        @else
                                            <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-red-100 text-red-800">
                                                Tidak Relevan
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-gray-400 italic text-[11px]">AI Gagal</span>
                                    @endif
                                </td>
                                <td class="p-3.5 whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold
                                        {{ $r->status === 'perlu_verifikasi' ? 'bg-amber-100 text-amber-800 border border-amber-300' :
                                           ($r->status === 'selesai' ? 'bg-green-100 text-green-800' :
                                           ($r->status === 'diproses' ? 'bg-blue-100 text-blue-800' :
                                           ($r->status === 'ditolak' ? 'bg-gray-200 text-gray-700' : 'bg-purple-100 text-purple-800'))) }}">
                                        {{ ucwords(str_replace('_', ' ', $r->status)) }}
                                    </span>
                                </td>
                                <td class="p-3.5 whitespace-nowrap text-gray-500 text-[11px]">
                                    {{ $r->created_at->format('d M Y, H:i') }}
                                </td>
                                <td class="p-3.5 text-center whitespace-nowrap">
                                    <a href="{{ route('admin.reports.show', $r) }}" class="inline-block bg-blue-50 text-blue-700 hover:bg-blue-100 px-3 py-1.5 rounded-lg font-medium transition text-xs">
                                        Periksa &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-8 text-center text-gray-400">
                                    Tidak ada laporan yang sesuai dengan filter yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginasi --}}
            @if ($reports->hasPages())
                <div class="p-4 border-t border-gray-100">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Leaflet Map Scripts --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mapReports = @json($mapReports);

            // Inisialisasi peta Leaflet
            const map = L.map('admin-map').setView([-6.9175, 107.6191], 12);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            function getColor(keparahan) {
                switch (parseInt(keparahan)) {
                    case 5: return '#dc2626'; // Merah
                    case 4: return '#ea580c'; // Oranye
                    case 3: return '#eab308'; // Kuning
                    case 2: return '#3b82f6'; // Biru
                    case 1: return '#10b981'; // Hijau
                    default: return '#6b7280'; // Abu-abu
                }
            }

            const markers = [];

            mapReports.forEach(r => {
                if (!r.lat || !r.lng) return;

                const keparahan = r.keparahan_admin || r.tingkat_keparahan;
                const kategori = r.kategori_admin || r.kategori;
                const color = getColor(keparahan);

                const marker = L.circleMarker([r.lat, r.lng], {
                    radius: 9,
                    fillColor: color,
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.9
                }).addTo(map);

                const popupHtml = `
                    <div style="min-width: 175px;">
                        <h4 style="font-weight: 700; font-size: 13px; margin: 0 0 4px 0; color: #111827;">${r.judul}</h4>
                        <div style="font-size: 11px; color: #4b5563; margin-bottom: 2px;">
                            Keparahan: <strong>${keparahan ? keparahan + '/5' : '-'}</strong> ${r.keparahan_admin ? '<span style="color:#2563eb; font-size:10px;">(Admin)</span>' : ''}
                        </div>
                        <div style="font-size: 11px; color: #4b5563; margin-bottom: 2px;">
                            Kategori: <strong>${kategori ? kategori.replace('_', ' ') : '-'}</strong> ${r.kategori_admin ? '<span style="color:#2563eb; font-size:10px;">(Admin)</span>' : ''}
                        </div>
                        <div style="font-size: 11px; color: #4b5563; margin-bottom: 6px;">
                            Status: <strong style="text-transform: capitalize;">${r.status.replace('_', ' ')}</strong>
                        </div>
                        <a href="/admin/laporan/${r.id}" style="color: #2563eb; font-weight: 600; font-size: 11px; text-decoration: underline;">
                            Lihat Detail &rarr;
                        </a>
                    </div>
                `;

                marker.bindPopup(popupHtml);
                markers.push(marker);
            });

            // Sesuaikan zoom peta agar mencakup seluruh marker yang ada
            if (markers.length > 0) {
                const group = L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.15));
            }
        });
    </script>
</x-app-layout>
