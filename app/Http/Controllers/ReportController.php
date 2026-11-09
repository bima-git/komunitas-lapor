<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\AiAnalyzer;
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

    public function store(Request $request, AiAnalyzer $ai)
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'foto' => ['required', 'image', 'max:4096'], // maks 4 MB
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $report = Report::create([
            'user_id' => $request->user()->id,
            'judul' => $data['judul'],
            'deskripsi' => $data['deskripsi'] ?? null,
            'foto_path' => $request->file('foto')->store('reports', 'public'),
            'lat' => $data['lat'],
            'lng' => $data['lng'],
        ]);

        $hasil = $ai->analyze($report->foto_path, $report->deskripsi);

        $report->update($hasil ? [
            'kategori' => $hasil['kategori'],
            'tingkat_keparahan' => $hasil['tingkat_keparahan'],
            'ringkasan_ai' => $hasil['ringkasan'],
            'ai_status' => 'berhasil',
        ] : ['ai_status' => 'gagal']); // laporan tetap tersimpan, admin isi manual

        return redirect()->route('reports.show', $report)->with('status', $hasil
            ? 'Laporan terkirim dan dianalisis AI.'
            : 'Laporan terkirim. Analisis AI belum tersedia, admin akan melengkapi.');
    }

    public function show(Report $report)
    {
        Gate::authorize('view', $report);

        return view('reports.show', compact('report'));
    }
}