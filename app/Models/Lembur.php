<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lembur extends Model
{
    protected $table = 'lembur';

    protected $fillable = [
        'user_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'uraian', 'status',
        'alasan_penolakan', 'durasi_disetujui_menit', 'disetujui_oleh', 'diproses_at',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'diproses_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }
}