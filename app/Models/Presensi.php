<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Presensi extends Model
{
    protected $table = 'presensi';

    protected $fillable = [
        'user_id', 'tanggal', 'status_harian',
        'jam_datang', 'status_datang', 'detik_terlambat',
        'jam_pulang', 'pulang_cepat', 'detik_pulang_cepat',
        'foto_datang', 'foto_pulang',
        'lat_datang', 'lng_datang', 'lat_pulang', 'lng_pulang',
        'jarak_datang', 'jarak_pulang', 'skor_wajah_datang', 'skor_wajah_pulang',
        'referensi_type', 'referensi_id', 'dibatalkan_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'pulang_cepat' => 'boolean',
            'detik_terlambat' => 'integer',
            'detik_pulang_cepat' => 'integer',
            'dibatalkan_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        // withTrashed: nama pegawai Keluar yang sudah dihapus tetap tampil di arsip
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }

    /** 990 detik -> "00:16:30"; 0 -> "--" (SR-F12). */
    public static function formatDetik(?int $detik): string
    {
        if (! $detik) {
            return '--';
        }

        return sprintf('%02d:%02d:%02d', intdiv($detik, 3600), intdiv($detik % 3600, 60), $detik % 60);
    }
}