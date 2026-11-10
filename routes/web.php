<?php

use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminStatisticsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/laporan/buat', [ReportController::class, 'create'])->name('reports.create');
    Route::post('/laporan', [ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
    Route::get('/laporan/{report}', [ReportController::class, 'show'])->name('reports.show');
});

// Route Admin (dilindungi middleware auth dan admin)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminReportController::class, 'index'])->name('dashboard');
    Route::get('/statistik', [AdminStatisticsController::class, 'index'])->name('statistics');
    Route::get('/laporan/{report}', [AdminReportController::class, 'show'])->name('reports.show');
    Route::patch('/laporan/{report}/status', [AdminReportController::class, 'updateStatus'])->name('reports.updateStatus');
    Route::post('/laporan/{report}/analisis-ulang', [AdminReportController::class, 'reanalyze'])->name('reports.reanalyze');
});

require __DIR__.'/auth.php';
