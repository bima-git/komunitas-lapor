<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminStatisticsController extends Controller
{
    /**
     * Tampilkan halaman statistik admin dengan ringkasan status, rata-rata waktu penyelesaian,
     * serta visualisasi grafik batang (kategori) dan grafik garis tren mingguan (Chart.js).
     */
    public function index()
    {
        Carbon::setLocale('id');

        // 1. Kartu Ringkasan: Total Laporan & Jumlah per Status
        $totalReports = Report::count();

        $statusCountsRaw = Report::select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $statusCounts = [
            'baru' => $statusCountsRaw['baru'] ?? 0,
            'diproses' => $statusCountsRaw['diproses'] ?? 0,
            'selesai' => $statusCountsRaw['selesai'] ?? 0,
            'perlu_verifikasi' => $statusCountsRaw['perlu_verifikasi'] ?? 0,
            'ditolak' => $statusCountsRaw['ditolak'] ?? 0,
        ];

        // Persentase laporan selesai
        $persentaseSelesai = $totalReports > 0
            ? round(($statusCounts['selesai'] / $totalReports) * 100, 1)
            : 0;

        // Total laporan yang ditandai kemungkinan duplikat
        $totalDuplikat = Report::whereNotNull('duplikat_dari')->count();

        // 2. Rata-rata Waktu Penyelesaian:
        // Selisih antara created_at laporan dan entri status_logs pertama bernilai "selesai".
        $firstSelesaiSub = DB::table('status_logs')
            ->select('report_id', DB::raw('MIN(created_at) as selesai_at'))
            ->where('status', 'selesai')
            ->groupBy('report_id');

        $driver = DB::connection()->getDriverName();
        $diffExpr = $driver === 'sqlite'
            ? "strftime('%s', first_selesai.selesai_at) - strftime('%s', reports.created_at)"
            : "TIMESTAMPDIFF(SECOND, reports.created_at, first_selesai.selesai_at)";

        $avgSeconds = DB::table('reports')
            ->joinSub($firstSelesaiSub, 'first_selesai', function ($join) {
                $join->on('reports.id', '=', 'first_selesai.report_id');
            })
            ->avg(DB::raw($diffExpr));

        $avgCompletionFormatted = $this->formatCompletionTime($avgSeconds);

        // 3. Grafik Batang: Laporan per Kategori Efektif (kategori_admin jika ada, jika tidak kategori AI)
        $categoryCountsRaw = Report::query()
            ->select(DB::raw('COALESCE(kategori_admin, kategori) as kategori_efektif'), DB::raw('COUNT(*) as total'))
            ->groupBy('kategori_efektif')
            ->pluck('total', 'kategori_efektif')
            ->toArray();

        $domainCategories = config('domain.kategori', []);
        $categoryLabels = [];
        $categoryData = [];

        foreach ($domainCategories as $cat) {
            $categoryLabels[] = ucwords(str_replace('_', ' ', $cat));
            $categoryData[] = $categoryCountsRaw[$cat] ?? 0;
        }

        // Tangani jika ada laporan tanpa kategori (belum diisi AI dan belum dikoreksi)
        $unclassified = ($categoryCountsRaw[''] ?? 0) + ($categoryCountsRaw[null] ?? 0);
        if ($unclassified > 0) {
            $categoryLabels[] = 'Belum Diklasifikasi';
            $categoryData[] = $unclassified;
        }

        // 4. Grafik Garis: Tren Laporan per Minggu untuk 8 Minggu Terakhir
        $tz = config('app.timezone', env('APP_TIMEZONE', 'Asia/Jakarta'));
        $now = now($tz);
        $weeks = [];

        for ($i = 7; $i >= 0; $i--) {
            $weekDate = (clone $now)->subWeeks($i);
            $startOfWeek = (clone $weekDate)->startOfWeek();
            $endOfWeek = (clone $weekDate)->endOfWeek();

            $startLabel = $startOfWeek->locale('id')->translatedFormat('d M');
            $endLabel = $endOfWeek->locale('id')->translatedFormat('d M');

            $weeks[] = [
                'label' => "{$startLabel} - {$endLabel}",
                'start' => $startOfWeek->copy()->utc()->toDateTimeString(),
                'end' => $endOfWeek->copy()->utc()->toDateTimeString(),
            ];
        }

        // Eksekusi agregasi hitung mingguan dalam 1 query database tanpa memuat koleksi ke memori
        $selectClauses = [];
        foreach ($weeks as $idx => $w) {
            $selectClauses[] = "COUNT(CASE WHEN created_at >= '{$w['start']}' AND created_at <= '{$w['end']}' THEN 1 END) as w_{$idx}";
        }

        $weeklyCounts = Report::selectRaw(implode(', ', $selectClauses))->first();

        $weeklyLabels = [];
        $weeklyData = [];
        foreach ($weeks as $idx => $w) {
            $weeklyLabels[] = $w['label'];
            $weeklyData[] = (int) ($weeklyCounts->{"w_{$idx}"} ?? 0);
        }

        return view('admin.statistics', compact(
            'totalReports',
            'statusCounts',
            'persentaseSelesai',
            'totalDuplikat',
            'avgCompletionFormatted',
            'categoryLabels',
            'categoryData',
            'weeklyLabels',
            'weeklyData'
        ));
    }

    /**
     * Format durasi detik ke dalam label bahasa Indonesia (jam/hari).
     * Jika null atau negatif, kembalikan 'Belum ada data'.
     */
    private function formatCompletionTime(?float $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return 'Belum ada data';
        }

        $days = floor($seconds / 86400);

        if ($days >= 1) {
            $remainingHours = round(($seconds % 86400) / 3600);
            if ($remainingHours > 0) {
                return "{$days} hari {$remainingHours} jam";
            }
            return "{$days} hari";
        }

        $hours = round($seconds / 3600, 1);
        $cleanHours = (floor($hours) == $hours) ? (int) $hours : $hours;

        return "{$cleanHours} jam";
    }
}
