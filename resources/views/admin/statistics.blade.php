<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Statistik & Kinerja Penanganan Sampah
                </h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Ringkasan performa pelaporan, kecepatan penyelesaian, dan tren pengaduan warga.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1.5 text-xs bg-white hover:bg-gray-50 text-gray-700 px-3 py-2 rounded-lg border border-gray-300 shadow-sm transition">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Daftar Laporan & Peta</span>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Pemberitahuan jika belum ada data sama sekali --}}
        @if ($totalReports === 0)
            <div class="p-4 bg-blue-50 border border-blue-200 text-blue-900 rounded-xl text-sm flex items-start gap-3 shadow-sm">
                <span class="text-xl">ℹ️</span>
                <div>
                    <h3 class="font-semibold">Belum Ada Data Laporan</h3>
                    <p class="text-xs text-blue-700 mt-0.5">
                        Sistem belum memiliki laporan yang tercatat. Statistik dan visualisasi grafik akan terisi secara otomatis setelah warga mengirimkan laporan.
                    </p>
                </div>
            </div>
        @endif

        {{-- 1. Kartu Ringkasan (KPI Cards) --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">

            {{-- Total Laporan --}}
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm">
                <span class="block text-xs font-medium text-gray-500">Total Laporan</span>
                <span class="text-2xl font-bold text-gray-900 mt-1 block">{{ number_format($totalReports, 0, ',', '.') }}</span>
                <span class="text-[11px] text-gray-400 mt-0.5 block">Semua laporan masuk</span>
            </div>

            {{-- Status: Baru --}}
            <div class="bg-white p-4 rounded-xl border border-purple-200 shadow-sm">
                <span class="block text-xs font-medium text-purple-700">Laporan Baru</span>
                <span class="text-2xl font-bold text-purple-900 mt-1 block">{{ number_format($statusCounts['baru'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-purple-600 mt-0.5 block">Menunggu diproses</span>
            </div>

            {{-- Status: Diproses --}}
            <div class="bg-white p-4 rounded-xl border border-blue-200 shadow-sm">
                <span class="block text-xs font-medium text-blue-700">Diproses</span>
                <span class="text-2xl font-bold text-blue-900 mt-1 block">{{ number_format($statusCounts['diproses'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-blue-600 mt-0.5 block">Dalam penanganan</span>
            </div>

            {{-- Status: Perlu Verifikasi --}}
            <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm">
                <span class="block text-xs font-medium text-amber-700">Perlu Verifikasi</span>
                <span class="text-2xl font-bold text-amber-900 mt-1 block">{{ number_format($statusCounts['perlu_verifikasi'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-amber-600 mt-0.5 block">Foto tidak sesuai</span>
            </div>

            {{-- Status: Selesai & Persentase --}}
            <div class="bg-white p-4 rounded-xl border border-green-200 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="block text-xs font-medium text-green-700">Selesai</span>
                    <span class="text-[10px] bg-green-100 text-green-800 font-semibold px-1.5 py-0.5 rounded">
                        {{ $persentaseSelesai }}%
                    </span>
                </div>
                <span class="text-2xl font-bold text-green-900 mt-1 block">{{ number_format($statusCounts['selesai'], 0, ',', '.') }}</span>
                <span class="text-[11px] text-green-600 mt-0.5 block">Tingkat selesai: {{ $persentaseSelesai }}%</span>
            </div>

            {{-- 2. Rata-rata Waktu Penyelesaian --}}
            <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm">
                <span class="block text-xs font-medium text-emerald-800">Rata-rata Penyelesaian</span>
                <span class="text-lg font-bold text-emerald-900 mt-1 block tracking-tight">
                    {{ $avgCompletionFormatted }}
                </span>
                <span class="text-[10px] text-gray-500 mt-0.5 block leading-tight">
                    Hingga status 'selesai' pertama
                </span>
            </div>

        </div>

        {{-- 3 & 4. Visualisasi Grafik Chart.js --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Grafik Batang: Laporan per Kategori --}}
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b pb-3">
                    <div>
                        <h3 class="font-semibold text-base text-gray-900">Jumlah Laporan per Kategori</h3>
                        <p class="text-xs text-gray-500">Nilai efektif (koreksi admin jika tersedia, atau saran AI).</p>
                    </div>
                </div>

                <div class="h-72 w-full">
                    <canvas id="chartKategori"></canvas>
                </div>
            </div>

            {{-- Grafik Garis: Tren Mingguan (8 Minggu Terakhir) --}}
            <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b pb-3">
                    <div>
                        <h3 class="font-semibold text-base text-gray-900">Tren Laporan Masuk (8 Minggu Terakhir)</h3>
                        <p class="text-xs text-gray-500">Jumlah pengaduan per minggu (Zona Waktu: {{ config('app.timezone') }}).</p>
                    </div>
                </div>

                <div class="h-72 w-full">
                    <canvas id="chartMingguan"></canvas>
                </div>
            </div>

        </div>

    </div>

    {{-- Chart.js via CDN versi terkunci (tanpa library tambahan) --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // 1. Inisialisasi Grafik Batang Kategori
            const canvasKategori = document.getElementById('chartKategori');
            if (canvasKategori) {
                new Chart(canvasKategori, {
                    type: 'bar',
                    data: {
                        labels: @json($categoryLabels),
                        datasets: [{
                            label: 'Jumlah Laporan',
                            data: @json($categoryData),
                            backgroundColor: [
                                '#3B82F6', // Biru
                                '#10B981', // Hijau
                                '#F59E0B', // Kuning/Amber
                                '#8B5CF6', // Ungu
                                '#6B7280', // Abu-abu
                                '#EC4899', // Pink
                            ],
                            borderRadius: 6,
                            maxBarThickness: 45,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return ' ' + context.parsed.y + ' laporan';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0,
                                    font: { size: 11 }
                                },
                                grid: {
                                    color: '#F3F4F6'
                                }
                            },
                            x: {
                                ticks: {
                                    font: { size: 11 }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // 2. Inisialisasi Grafik Garis Mingguan
            const canvasMingguan = document.getElementById('chartMingguan');
            if (canvasMingguan) {
                new Chart(canvasMingguan, {
                    type: 'line',
                    data: {
                        labels: @json($weeklyLabels),
                        datasets: [{
                            label: 'Laporan Masuk',
                            data: @json($weeklyData),
                            borderColor: '#059669',
                            backgroundColor: 'rgba(16, 185, 129, 0.12)',
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2.5,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#059669',
                            pointBorderColor: '#FFFFFF',
                            pointBorderWidth: 2,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return ' ' + context.parsed.y + ' laporan masuk';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0,
                                    font: { size: 11 }
                                },
                                grid: {
                                    color: '#F3F4F6'
                                }
                            },
                            x: {
                                ticks: {
                                    font: { size: 10 }
                                },
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</x-app-layout>
