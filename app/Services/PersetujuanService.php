<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class PersetujuanService
{
    private ?Collection $pejabat = null;

    /** Semua pejabat aktif yang bisa menjadi penyetuju (disimpan selama satu request). */
    private function pejabat(): Collection
    {
        return $this->pejabat ??= User::with(['unit', 'roles'])
            ->where('status', 'Aktif')
            ->whereHas('roles', fn ($q) => $q->whereIn('nama', [Role::KEPALA_SEKOLAH, Role::ADMIN, Role::KETUA_YAYASAN]))
            ->get();
    }

    /**
     * Orang yang berwenang menyetujui izin/sakit/lembur pegawai ini:
     * - Kepala sekolah                 -> Ketua Yayasan atau Kabag Adm. Umum (salah satu)
     * - Pegawai KB & TK                -> Kepala KB & TK
     * - Pegawai SD dan unit Yayasan    -> Kepala Sekolah SD
     */
    public function penyetujuUntuk(User $pengaju): Collection
    {
        $pengaju->loadMissing('unit', 'roles');

        if ($pengaju->hasRole(Role::KEPALA_SEKOLAH)) {
            return $this->pejabat()
                ->filter(fn ($u) => ! $u->is($pengaju)
                    && ($u->hasRole(Role::KETUA_YAYASAN) || $u->hasRole(Role::ADMIN)))
                ->values();
        }

        $kodeUnit = $pengaju->unit?->kode === 'KBTK' ? 'KBTK' : 'SD';

        return $this->pejabat()
            ->filter(fn ($u) => ! $u->is($pengaju)
                && $u->hasRole(Role::KEPALA_SEKOLAH)
                && $u->unit?->kode === $kodeUnit)
            ->values();
    }

    public function bolehMenyetujui(User $penyetuju, User $pengaju): bool
    {
        return $this->penyetujuUntuk($pengaju)->contains(fn ($u) => $u->is($penyetuju));
    }

    /** Teks tujuan pengajuan, misalnya untuk tombol "Kirim ke …". */
    public function labelPenyetuju(User $pengaju): string
    {
        $pengaju->loadMissing('roles');

        if ($pengaju->hasRole(Role::KEPALA_SEKOLAH)) {
            return 'Ketua Yayasan / Kabag Adm. Umum';
        }

        return $this->penyetujuUntuk($pengaju)->first()?->nama_lengkap ?? 'Kepala Sekolah';
    }
}