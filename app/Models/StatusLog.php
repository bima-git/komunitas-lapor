<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusLog extends Model
{
    protected $fillable = [
        'report_id',
        'status',
        'catatan',
        'tampil_ke_pelapor',
        'diubah_oleh',
    ];

    protected $casts = [
        'tampil_ke_pelapor' => 'boolean',
    ];

    /** Relasi ke laporan */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** Relasi ke user / admin yang mengubah status */
    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
