<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

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

    public const LEWAT_BATAS = 'Lewat Batas';

    /**
     * Batas presensi datang hari itu (Pengaturan "batas_presensi_datang", maks. jadwal pulang).
     * Lewat dari jam ini presensi tetap dicatat, tetapi berstatus "Lewat Batas".
     */
    public static function batasDatang(array $jadwal): Carbon
    {
        return Carbon::parse($jadwal['masuk']->toDateString().' '.Pengaturan::ambil('batas_presensi_datang', '12:00'))
            ->min($jadwal['pulang']);
    }

    /**
     * Status & lama terlambat untuk jam datang tertentu.
     * Tepat Waktu : ≤ jam masuk + toleransi
     * Terlambat   : > toleransi s.d. batas presensi datang
     * Lewat Batas : > batas presensi datang (tetap Hadir)
     * Lama terlambat selalu dihitung dari jam masuk.
     */
    public static function hitungDatang(Carbon $datang, array $jadwal): array
    {
        $terlambat = $datang->gt($jadwal['masuk']->copy()->addMinutes($jadwal['toleransi']));

        return [
            'status_datang'   => match (true) {
                $datang->gt(self::batasDatang($jadwal)) => self::LEWAT_BATAS,
                $terlambat                              => 'Terlambat',
                default                                 => 'Tepat Waktu',
            },
            'detik_terlambat' => $terlambat ? $datang->timestamp - $jadwal['masuk']->timestamp : 0,
        ];
    }
}