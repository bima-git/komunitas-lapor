<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">
                    Selamat datang, {{ Auth::user()->name }}!
                </h3>
                <p class="text-sm text-gray-600 mb-6">
                    Anda masuk sebagai <strong class="uppercase text-blue-600">{{ Auth::user()->role }}</strong>.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <a href="{{ route('reports.create') }}" class="p-4 rounded-xl border border-green-200 bg-green-50 hover:bg-green-100 transition block">
                        <span class="text-green-800 font-bold block mb-1">+ Buat Laporan Baru</span>
                        <p class="text-xs text-green-700">Foto titik sampah liar dan tandai lokasi koordinat di peta.</p>
                    </a>

                    <a href="{{ route('reports.index') }}" class="p-4 rounded-xl border border-blue-200 bg-blue-50 hover:bg-blue-100 transition block">
                        <span class="text-blue-800 font-bold block mb-1">Laporan Saya</span>
                        <p class="text-xs text-blue-700">Lihat status penanganan dan hasil analisis AI dari laporan Anda.</p>
                    </a>

                    @if (Auth::user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="p-4 rounded-xl border border-purple-200 bg-purple-50 hover:bg-purple-100 transition block sm:col-span-2 lg:col-span-1">
                            <span class="text-purple-800 font-bold block mb-1">Panel Admin &rarr;</span>
                            <p class="text-xs text-purple-700">Pantau seluruh laporan di peta, filter keparahan, dan verifikasi status.</p>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
