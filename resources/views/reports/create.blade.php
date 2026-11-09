<x-app-layout>
    <div class="max-w-xl mx-auto p-4">
        <h1 class="text-xl font-semibold mb-4">Laporkan titik sampah</h1>

        <form method="POST" action="{{ route('reports.store') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label for="judul" class="block text-sm font-medium">Judul</label>
                <input id="judul" name="judul" value="{{ old('judul') }}" required class="w-full border rounded p-2">
                @error('judul') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="deskripsi" class="block text-sm font-medium">Deskripsi (opsional)</label>
                <textarea id="deskripsi" name="deskripsi" rows="3" class="w-full border rounded p-2">{{ old('deskripsi') }}</textarea>
            </div>
            <div>
                <label for="foto" class="block text-sm font-medium">Foto (maks. 4 MB)</label>
                <input id="foto" type="file" name="foto" accept="image/*" required>
                @error('foto') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <p class="text-sm font-medium">Lokasi: klik peta</p>
                <div id="map" class="h-64 rounded border"></div>
                <input type="hidden" name="lat" id="lat" required>
                <input type="hidden" name="lng" id="lng" required>
                @error('lat') <p class="text-sm text-red-600">Pilih lokasi di peta.</p> @enderror
            </div>
            <button class="bg-green-700 text-white px-4 py-2 rounded">Kirim laporan</button>
        </form>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('map').setView([-6.9175, 107.6191], 13); // ganti ke kota Anda
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {attribution: '&copy; OpenStreetMap'}).addTo(map);
        let marker;
        map.on('click', e => {
            marker ? marker.setLatLng(e.latlng) : marker = L.marker(e.latlng).addTo(map);
            document.getElementById('lat').value = e.latlng.lat;
            document.getElementById('lng').value = e.latlng.lng;
        });
    </script>
</x-app-layout>