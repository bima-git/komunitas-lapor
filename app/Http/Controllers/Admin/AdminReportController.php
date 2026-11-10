<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\AiAnalyzer;
use Illuminate\Http\Request;

class AdminReportController extends Controller
{
    /**
     * Tampilkan dashboard admin: tabel laporan terfilter dan peta sebaran Leaflet.
     */
    public function index(Request $request)
    {
        $query = Report::with(['user', 'editor']);

        // Filter status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter kategori (memperhitungkan koreksi admin bila ada)
        if ($request->filled('kategori')) {
            $kat = $request->kategori;
            $query->where(function ($q) use ($kat) {
                $q->where('kategori_admin', $kat)
                  ->orWhere(function ($sub) use ($kat) {
                      $sub->whereNull('kategori_admin')
                          ->where('kategori', $kat);
                  });
            });
        }

        // Filter keparahan (memperhitungkan koreksi admin bila ada)
        if ($request->filled('keparahan')) {
            $val = (int) $request->keparahan;
            $query->where(function ($q) use ($val) {
                $q->where('keparahan_admin', $val)
                  ->orWhere(function ($sub) use ($val) {
                      $sub->whereNull('keparahan_admin')
                          ->where('tingkat_keparahan', $val);
                  });
            });
        }

        // Filter relevan (1 = ya, 0 = tidak)
        if ($request->filled('relevan')) {
            $query->where('relevan', $request->relevan === '1');
        }

        // Filter status AI (berhasil / gagal)
        if ($request->filled('ai_status')) {
            $query->where('ai_status', $request->ai_status);
        }

        // Kloning query untuk data titik peta sebelum dipaginasi
        $mapReports = (clone $query)
            ->select([
                'id', 'judul', 'lat', 'lng',
                'tingkat_keparahan', 'keparahan_admin',
                'kategori', 'kategori_admin',
                'status', 'relevan', 'ai_status'
            ])
            ->get();

        // Urutan default: keparahan tertinggi (pakai nilai keparahan_admin jika ada, fallback ke tingkat_keparahan), lalu terbaru
        $reports = $query->orderByRaw('COALESCE(keparahan_admin, tingkat_keparahan) IS NULL, COALESCE(keparahan_admin, tingkat_keparahan) DESC')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $kategoriList = config('domain.kategori', []);

        return view('admin.index', compact('reports', 'mapReports', 'kategoriList'));
    }

    /**
     * Tampilkan detail laporan untuk admin beserta form verifikasi & koreksi admin.
     */
    public function show(Report $report)
    {
        $report->load(['user', 'editor', 'logs.pengubah']);
        $kategoriList = config('domain.kategori', []);

        return view('admin.show', compact('report', 'kategoriList'));
    }

    /**
     * Ubah status laporan dan simpan koreksi kategori/keparahan admin tanpa menimpa saran AI asli.
     */
    public function updateStatus(Request $request, Report $report)
    {
        $kategoriList = config('domain.kategori', []);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:baru,perlu_verifikasi,diproses,selesai,ditolak'],
            'catatan' => ['nullable', 'string', 'max:1000'],
            'tampil_ke_pelapor' => ['nullable', 'boolean'],
            'kategori_admin' => ['nullable', 'string', 'in:'.implode(',', $kategoriList)],
            'keparahan_admin' => ['nullable', 'integer', 'between:1,5'],
            'relevan' => ['nullable', 'in:0,1'],
        ]);

        $updateData = [
            'status' => $data['status'],
            'kategori_admin' => $data['kategori_admin'] ?? null,
            'keparahan_admin' => $data['keparahan_admin'] ?? null,
        ];

        if (isset($data['relevan'])) {
            $updateData['relevan'] = (bool) $data['relevan'];
        }

        // Catat admin yang melakukan koreksi jika field koreksi diisi
        if ($request->filled('kategori_admin') || $request->filled('keparahan_admin')) {
            $updateData['dikoreksi_oleh'] = $request->user()->id;
        }

        // Perbarui data laporan
        $report->update($updateData);

        // Catat ke log status
        $report->logs()->create([
            'status' => $data['status'],
            'catatan' => $data['catatan'] ?? null,
            'tampil_ke_pelapor' => $request->boolean('tampil_ke_pelapor'),
            'diubah_oleh' => $request->user()->id,
        ]);

        return redirect()->route('admin.reports.show', $report)
            ->with('status', 'Status dan koreksi laporan berhasil disimpan.');
    }

    /**
     * Jalankan analisis ulang AI untuk laporan tertentu (misal ketika sebelumnya ai_status = gagal).
     */
    public function reanalyze(Report $report, AiAnalyzer $ai)
    {
        $hasil = $ai->analyze($report->foto_path, $report->deskripsi);

        if ($hasil) {
            $isRelevan = (bool) $hasil['relevan'];

            $updateData = [
                'relevan' => $isRelevan,
                'kepercayaan' => $hasil['kepercayaan'],
                'kategori' => $hasil['kategori'],
                'tingkat_keparahan' => $hasil['tingkat_keparahan'],
                'ringkasan_ai' => $hasil['ringkasan'],
                'ai_status' => 'berhasil',
            ];

            // Jika status masih baru dan dinilai tidak relevan, ubah menjadi perlu_verifikasi
            if (! $isRelevan && $report->status === 'baru') {
                $updateData['status'] = 'perlu_verifikasi';
            }

            $report->update($updateData);

            return redirect()->route('admin.reports.show', $report)
                ->with('status', "Laporan #{$report->id} berhasil dianalisis ulang oleh AI.");
        }

        $report->update(['ai_status' => 'gagal']);

        return redirect()->route('admin.reports.show', $report)
            ->with('warning', 'Analisis ulang AI belum berhasil (seluruh model AI sedang sibuk atau kuota harian habis). Silakan coba lagi nanti.');
    }
}
