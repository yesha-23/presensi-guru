<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JamKerja extends Model
{
    protected $table = 'jam_kerja';
    protected $fillable = ['unit_id', 'kategori', 'jam_masuk', 'jam_pulang', 'toleransi_menit'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}