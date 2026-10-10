<?php

namespace App\Services;

use App\Models\Presensi;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class StatusHarianService
{
    public function __construct(private JadwalService $jadwal)
    {
    }

    /**
     * Tetapkan status harian satu pegawai pada satu tanggal yang sudah berakhir:
     * Hadir (ada presensi datang) > Izin/Sakit/Tugas Khusus/Libur > Alpa.
     * Juga dipakai scheduler akhir hari (SR-F15).
     */
    public function tetapkan(User $user, Carbon $tanggal): string
    {
        $presensi = Presensi::where('user_id', $user->id)->whereDate('tanggal', $tanggal)->first();

        if ($presensi?->jam_datang) {
            if ($presensi->status_harian !== 'Hadir') {
                $presensi->update(['status_harian' => 'Hadir', 'referensi_type' => null, 'referensi_id' => null]);
            }

            return 'Hadir';
        }

        // Hari nonkerja/libur tetap Libur meskipun tercakup izin atau sakit
        $hariKerja = $this->jadwal->jadwal($user, $tanggal) !== null;
        $khusus = $hariKerja ? $this->jadwal->kondisiKhusus($user, $tanggal) : null;
        $status = $hariKerja ? ($khusus['status'] ?? 'Alpa') : 'Libur';
        $referensi = $khusus['referensi'] ?? null;

        // Hari libur tanpa catatan apa pun tidak perlu dibuatkan baris
        if ($status === 'Libur' && ! $presensi) {
            return 'Libur';
        }

        Presensi::updateOrCreate(
            ['user_id' => $user->id, 'tanggal' => $tanggal->toDateString()],
            [
                'status_harian'  => $status,
                'referensi_type' => $referensi ? $referensi::class : null,
                'referensi_id'   => $referensi?->id,
            ]
        );

        return $status;
    }

    /**
     * Hitung ulang status presensi pada rentang tanggal yang sudah lewat (SR-F25).
     * Hanya tanggal sejak sistem mulai dipakai (presensi pertama) sampai kemarin.
     *
     * @return int jumlah tanggal yang dihitung ulang
     */
    public function hitungUlang(Carbon $mulai, Carbon $selesai, ?int $unitId = null): int
    {
        $awalSistem = Presensi::min('tanggal');
        if (! $awalSistem) {
            return 0;
        }

        $mulai = $mulai->copy()->startOfDay()->max(Carbon::parse($awalSistem)->startOfDay());
        $selesai = $selesai->copy()->startOfDay()->min(today()->subDay());

        if ($mulai->gt($selesai)) {
            return 0;
        }

        $pegawai = User::where('status', 'Aktif')
            ->whereNotNull('kategori')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::PEGAWAI))
            ->when($unitId, fn ($q, $id) => $q->where('unit_id', $id))
            ->get();

        $jumlah = 0;
        foreach (CarbonPeriod::create($mulai, $selesai) as $tanggal) {
            foreach ($pegawai as $user) {
                $this->tetapkan($user, $tanggal);
            }
            $jumlah++;
        }

        return $jumlah;
    }
}