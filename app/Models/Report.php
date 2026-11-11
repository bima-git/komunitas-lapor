<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $attributes = [
        'status' => 'baru',
        'ai_status' => 'menunggu',
    ];

    protected $fillable = [
        'user_id', 'judul', 'deskripsi', 'foto_path', 'lat', 'lng',
        'kategori', 'tingkat_keparahan', 'ringkasan_ai', 'ai_status',
        'relevan', 'kepercayaan', 'status',
        // Kolom koreksi admin
        'kategori_admin', 'keparahan_admin', 'dikoreksi_oleh',
        // Kolom deteksi duplikat
        'duplikat_dari',
    ];

    /**
     * Konversi tipe data atribut model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relevan' => 'boolean',
            'kepercayaan' => 'integer',
            'tingkat_keparahan' => 'integer',
            'keparahan_admin' => 'integer',
            'duplikat_dari' => 'integer',
        ];
    }

    /**
     * Nilai kategori efektif: jika admin mengoreksi, gunakan nilai admin; jika tidak, gunakan saran AI.
     */
    public function getEffectiveKategoriAttribute(): ?string
    {
        return $this->kategori_admin ?: $this->kategori;
    }

    /**
     * Nilai keparahan efektif: jika admin mengoreksi, gunakan nilai admin; jika tidak, gunakan saran AI.
     */
    public function getEffectiveKeparahanAttribute(): ?int
    {
        return $this->keparahan_admin ?: $this->tingkat_keparahan;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin yang melakukan koreksi terakhir pada kategori/keparahan.
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StatusLog::class)->latest();
    }

    /**
     * Laporan asli jika laporan ini ditandai sebagai duplikat.
     */
    public function duplikatAsli(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'duplikat_dari');
    }

    /**
     * Daftar laporan lain yang ditandai sebagai duplikat dari laporan ini.
     */
    public function laporanDuplikat(): HasMany
    {
        return $this->hasMany(Report::class, 'duplikat_dari');
    }
}
