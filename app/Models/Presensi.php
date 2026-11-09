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

    /** Pilihan perlakuan presensi datang setelah batas (Pengaturan "mode_lewat_batas"). */
    public const MODE_CATAT = 'catat';
    public const MODE_TOLAK = 'tolak';

    /**
     * Batas presensi datang hari itu (Pengaturan "batas_presensi_datang", maks. jadwal pulang).
     * Lewat dari jam ini presensi dicatat "Lewat Batas" atau ditolak, sesuai mode di Pengaturan.
     */
    public static function batasDatang(array $jadwal): Carbon
    {
        return Carbon::parse($jadwal['masuk']->toDateString().' '.Pengaturan::ambil('batas_presensi_datang', '12:00'))
            ->min($jadwal['pulang']);
    }

    /** true bila administrator memilih: presensi datang setelah batas ditolak. */
    public static function tolakLewatBatas(): bool
    {
        return Pengaturan::ambil('mode_lewat_batas', self::MODE_CATAT) === self::MODE_TOLAK;
    }

    /**
     * Perbandingan jam dilakukan per menit: detik diabaikan.
     * Contoh jam masuk 07.00 tanpa toleransi: 07.00.59 masih Tepat Waktu, 07.01.00 Terlambat.
     */
    private static function menit(Carbon $waktu): Carbon
    {
        return $waktu->copy()->startOfMinute();
    }

    /** Apakah jam datang sudah melewati batas presensi datang? */
    public static function lewatBatas(Carbon $datang, array $jadwal): bool
    {
        return self::menit($datang)->gt(self::batasDatang($jadwal));
    }

    /**
     * Status & lama terlambat untuk jam datang tertentu.
     * Tepat Waktu : menit datang ≤ jam masuk + toleransi
     * Terlambat   : > toleransi s.d. batas presensi datang
     * Lewat Batas : > batas presensi datang (tetap Hadir; bila mode Tolak, scan ditolak sebelum sampai sini)
     * Lama terlambat selalu dihitung dari jam masuk, sampai detik.
     */
    public static function hitungDatang(Carbon $datang, array $jadwal): array
    {
        $terlambat = self::menit($datang)->gt($jadwal['masuk']->copy()->addMinutes($jadwal['toleransi']));

        return [
            'status_datang'   => match (true) {
                self::lewatBatas($datang, $jadwal) => self::LEWAT_BATAS,
                $terlambat                         => 'Terlambat',
                default                            => 'Tepat Waktu',
            },
            'detik_terlambat' => $terlambat ? $datang->timestamp - $jadwal['masuk']->timestamp : 0,
        ];
    }
}