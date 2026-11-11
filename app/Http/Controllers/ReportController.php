<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\AiAnalyzer;
use App\Services\DuplicateDetector;
use App\Services\ImageResizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = Report::where('user_id', $request->user()->id)->latest()->paginate(10);

        return view('reports.index', compact('reports'));
    }

    public function create()
    {
        return view('reports.create');
    }

    public function store(Request $request, AiAnalyzer $ai, ImageResizer $resizer, DuplicateDetector $detector)
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'], // maks 4 MB gambar valid
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        // Kecilkan foto sebelum disimpan dan sebelum dikirim ke AI (sisi terpanjang maks 1280px, JPEG q=80)
        $fotoPath = $resizer->resizeAndStore($request->file('foto'), 'reports', 'public');

        $report = Report::create([
            'user_id' => $request->user()->id,
            'judul' => $data['judul'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'foto_path' => $fotoPath,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
        ]);

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

            // Jika foto tidak relevan, status dialihkan ke 'perlu_verifikasi'
            if (! $isRelevan) {
                $updateData['status'] = 'perlu_verifikasi';
            }

            $report->update($updateData);

            $pesan = $isRelevan
                ? 'Laporan terkirim dan dianalisis AI.'
                : 'Foto dan deskripsi tampak tidak sesuai. Laporan ini akan diverifikasi admin.';
        } else {
            // Laporan tetap tersimpan jika AI gagal, admin akan mengisi manual
            $report->update([
                'ai_status' => 'gagal',
            ]);

            $pesan = 'Laporan terkirim. Analisis AI belum tersedia, admin akan melengkapi.';
        }

        // Deteksi laporan duplikat berdasarkan lokasi dan waktu tanpa memanggil AI tambahan
        $duplikatAsli = $detector->detectDuplicateFor($report);
        if ($duplikatAsli) {
            $report->update(['duplikat_dari' => $duplikatAsli->id]);
            $pesan = 'Ada laporan serupa di sekitar lokasi ini, laporan Anda tetap kami terima.';
        }

        return redirect()->route('reports.show', $report)->with('status', $pesan);
    }

    public function show(Report $report)
    {
        Gate::authorize('view', $report);

        // Muat log status urut waktu terlama ke terbaru untuk timeline
        $report->load(['logs' => fn ($query) => $query->oldest()]);

        return view('reports.show', compact('report'));
    }
}
