<?php

namespace App\Services;

use App\Models\Presensi;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class KehadiranService
{
    private const URUTAN = [
        'Tepat Waktu' => 0, 'Terlambat' => 0, 'Lewat Batas' => 0, 'Izin' => 1, 'Sakit' => 1,
        'Tugas Khusus' => 2, 'Belum Presensi' => 3, 'Libur' => 4,
    ];

    public function __construct(private JadwalService $jadwal)
    {
    }

    /**
     * Status kehadiran hari ini untuk setiap pegawai wajib presensi:
     * Tepat Waktu / Terlambat / Lewat Batas (sudah datang), Izin, Sakit, Tugas Khusus, Libur, atau Belum Presensi.
     */
    public function hariIni(?int $unitId = null): Collection
    {
        $sekarang = now();

        $pegawai = User::with('unit')
            ->where('status', 'Aktif')
            ->whereNotNull('kategori')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::PEGAWAI))
            ->when($unitId, fn ($q, $id) => $q->where('unit_id', $id))
            ->orderBy('nama_lengkap')
            ->get();

        $presensi = Presensi::whereIn('user_id', $pegawai->pluck('id'))
            ->whereDate('tanggal', $sekarang)->get()->keyBy('user_id');

        return $pegawai->map(function (User $u) use ($presensi, $sekarang) {
            $p = $presensi->get($u->id);

            if ($p?->jam_datang) {
                $status = $p->status_datang;
            } elseif ($khusus = $this->jadwal->kondisiKhusus($u, $sekarang)) {
                $status = $khusus['status']; // Izin, Sakit, Tugas Khusus, Libur
            } else {
                $status = 'Belum Presensi';
            }

            return ['user' => $u, 'presensi' => $p, 'status' => $status];
        })
            // Urut: sudah datang (berdasarkan jam), izin/sakit, tugas, belum, libur
            ->sortBy(fn ($r) => sprintf('%d-%s', self::URUTAN[$r['status']] ?? 9, $r['presensi']?->jam_datang ?? '99:99:99'))
            ->values();
    }

    /** Angka ringkas untuk kartu (pegawai yang libur tidak dihitung wajib). */
    public static function ringkas(Collection $daftar): array
    {
        $wajib = $daftar->where('status', '!=', 'Libur');

        return [
            'total'      => $wajib->count(),
            'hadir'      => $wajib->whereIn('status', ['Tepat Waktu', 'Terlambat', 'Lewat Batas'])->count(),
            'terlambat'  => $wajib->whereIn('status', ['Terlambat', 'Lewat Batas'])->count(),
            'belum'      => $wajib->where('status', 'Belum Presensi')->count(),
            'izin_sakit' => $wajib->whereIn('status', ['Izin', 'Sakit'])->count(),
            'tugas'      => $wajib->where('status', 'Tugas Khusus')->count(),
        ];
    }
}