<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LogKoreksi extends Model
{
    protected $table = 'log_koreksi';

    protected $fillable = [
        'user_id', 'target_type', 'target_id', 'nilai_lama', 'nilai_baru', 'alasan',
        'status_persetujuan', 'penyetuju_id', 'catatan_penyetuju', 'diproses_at',
    ];

    protected function casts(): array
    {
        return [
            'nilai_lama' => 'array',
            'nilai_baru' => 'array',
            'diproses_at' => 'datetime',
        ];
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyetuju_id');
    }
}