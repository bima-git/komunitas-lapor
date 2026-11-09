<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusLog extends Model
{
    protected $fillable = ['report_id', 'status', 'catatan', 'diubah_oleh'];
}
