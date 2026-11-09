<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $fillable = [
        'user_id', 'judul', 'deskripsi', 'foto_path', 'lat', 'lng',
        'kategori', 'tingkat_keparahan', 'ringkasan_ai', 'ai_status', 'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StatusLog::class);
    }
}