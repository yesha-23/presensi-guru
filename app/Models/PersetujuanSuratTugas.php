<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersetujuanSuratTugas extends Model
{
    protected $table = 'persetujuan_surat_tugas';

    protected $fillable = [
        'surat_tugas_id', 'penyetuju_id', 'urutan', 'status', 'catatan', 'ditandatangani_at',
    ];

    protected function casts(): array
    {
        return ['ditandatangani_at' => 'datetime'];
    }

    public function suratTugas(): BelongsTo
    {
        return $this->belongsTo(SuratTugas::class);
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyetuju_id');
    }
}