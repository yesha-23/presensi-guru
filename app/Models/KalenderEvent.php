<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KalenderEvent extends Model
{
    public const KATEGORI_LIBUR = ['Libur Nasional', 'Cuti Bersama', 'Libur Internal'];
    public const HARI_KERJA_TAMBAHAN = 'Hari Kerja Tambahan';
    public const KEGIATAN = 'Kegiatan Sekolah';

    protected $table = 'kalender_events';
    protected $fillable = [
        'unit_id', 'nama', 'kategori', 'berlaku_untuk',
        'tanggal_mulai', 'tanggal_selesai', 'jam_masuk', 'jam_pulang', 'created_by',
    ];

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}