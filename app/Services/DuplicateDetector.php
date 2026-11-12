<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Collection;

class DuplicateDetector
{
    /**
     * Hitung jarak dua titik koordinat dalam meter menggunakan rumus Haversine.
     */
    public static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // jari-jari bumi dalam meter

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Hitung batas kotak koordinat (bounding box) untuk optimasi kueri basis data.
     */
    public static function calculateBoundingBox(float $lat, float $lng, float $radiusMeter): array
    {
        // 1 derajat lintang ~ 111.195 meter
        $deltaLat = ($radiusMeter / 111195) * 1.05;
        $cosLat = max(cos(deg2rad($lat)), 0.01);
        $deltaLng = ($radiusMeter / (111195 * $cosLat)) * 1.05;

        return [
            'min_lat' => $lat - $deltaLat,
            'max_lat' => $lat + $deltaLat,
            'min_lng' => $lng - $deltaLng,
            'max_lng' => $lng + $deltaLng,
        ];
    }

    /**
     * Cari laporan lain di sekitar lokasi laporan yang memenuhi kriteria:
     * (a) Jarak <= radius (config/laporan.php)
     * (b) Status belum 'selesai'
     * (c) Dibuat dalam rentang hari terakhir (config/laporan.php)
     * (d) Kategori efektif sama jika kategori laporan sudah ada
     */
    public function findNearbyReports(Report $report, ?int $limit = null, bool $sameCategoryOnly = true): Collection
    {
        $radius = (float) config('laporan.duplikat.jarak_meter', 50);
        $rentangHari = (int) config('laporan.duplikat.rentang_hari', 30);
        $maxLimit = $limit ?? (int) config('laporan.duplikat.batas_hasil', 5);

        $bbox = self::calculateBoundingBox((float) $report->lat, (float) $report->lng, $radius);

        $query = Report::query()
            ->where('id', '!=', $report->id)
            ->where('status', '!=', 'selesai')
            ->where('created_at', '>=', now()->subDays($rentangHari))
            ->whereBetween('lat', [$bbox['min_lat'], $bbox['max_lat']])
            ->whereBetween('lng', [$bbox['min_lng'], $bbox['max_lng']]);

        // Kategori efektif yang sama jika kategori sudah ada.
        // Jika kategori kosong (AI gagal / belum diverifikasi), sistem memakai kriteria jarak dan waktu saja.
        $kategori = $report->effective_kategori;
        if ($sameCategoryOnly && ! empty($kategori)) {
            $query->where(function ($q) use ($kategori) {
                $q->where('kategori_admin', $kategori)
                  ->orWhere(function ($sub) use ($kategori) {
                      $sub->whereNull('kategori_admin')
                          ->where('kategori', $kategori);
                  })
                  ->orWhere(function ($sub) {
                      $sub->whereNull('kategori_admin')
                          ->whereNull('kategori');
                  });
            });
        }

        $candidates = $query->get();

        return $candidates->map(function ($candidate) use ($report) {
            $candidate->jarak_meter = self::haversineDistance(
                (float) $report->lat,
                (float) $report->lng,
                (float) $candidate->lat,
                (float) $candidate->lng
            );
            return $candidate;
        })->filter(function ($candidate) use ($radius) {
            return $candidate->jarak_meter <= $radius;
        })->sortBy('jarak_meter')
          ->values()
          ->take($maxLimit);
    }

    /**
     * Temukan laporan asli terdekat yang menjadi acuan duplikat.
     */
    public function detectDuplicateFor(Report $report): ?Report
    {
        $nearby = $this->findNearbyReports($report, 1, true);

        return $nearby->first();
    }
}
