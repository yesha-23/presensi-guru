<?php

namespace App\Services;

use App\Models\Lembur;
use App\Models\Presensi;
use Carbon\Carbon;

class LemburService
{
    /**
     * Durasi lembur yang diakui (menit) = jam yang diajukan,
     * dibatasi jam presensi pulang aktual (SR-F22).
     * null = presensi pulang belum ada, dihitung nanti.
     */
    public function hitungDurasi(Lembur $lembur): ?int
    {
        $presensi = Presensi::where('user_id', $lembur->user_id)
            ->whereDate('tanggal', $lembur->tanggal)
            ->first();

        if (! $presensi?->jam_pulang) {
            return null;
        }

        $tgl = $lembur->tanggal->toDateString();
        $mulai = Carbon::parse("{$tgl} {$lembur->jam_mulai}");
        $selesai = Carbon::parse("{$tgl} {$lembur->jam_selesai}")
            ->min(Carbon::parse("{$tgl} {$presensi->jam_pulang}"));

        return max(0, intdiv($selesai->timestamp - $mulai->timestamp, 60));
    }

    /** Dipanggil saat presensi pulang tercatat atau dibatalkan. */
    public function perbaruiUntuk(int $userId, Carbon $tanggal): void
    {
        $lembur = Lembur::where('user_id', $userId)
            ->whereDate('tanggal', $tanggal)
            ->where('status', 'Disetujui')
            ->first();

        if ($lembur) {
            $lembur->update(['durasi_disetujui_menit' => $this->hitungDurasi($lembur)]);
        }
    }

    /** Lama yang diajukan dalam menit. */
    public static function menitDiajukan(Lembur $lembur): int
    {
        $mulai = Carbon::parse($lembur->jam_mulai);
        $selesai = Carbon::parse($lembur->jam_selesai);

        return max(0, intdiv($selesai->timestamp - $mulai->timestamp, 60));
    }

    /** 90 -> "01:30:00"; null -> "--" */
    public static function formatMenit(?int $menit): string
    {
        if ($menit === null) {
            return '--';
        }

        return sprintf('%02d:%02d:00', intdiv($menit, 60), $menit % 60);
    }
}