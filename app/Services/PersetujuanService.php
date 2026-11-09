<?php

namespace App\Services;

use App\Models\Pengaturan;
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
     * Id administrator yang ditunjuk menyetujui pengajuan kepala sekolah (Pengaturan "penyetuju_pengajuan_ks"),
     * misalnya Kabag Adm. Umum. null = belum diatur (semua administrator, perilaku lama).
     */
    public static function idPenyetujuKs(): ?array
    {
        $nilai = Pengaturan::ambil('penyetuju_pengajuan_ks');
        if ($nilai === null) {
            return null;
        }

        return array_values(array_filter(array_map('intval', explode(',', (string) $nilai))));
    }

    /**
     * Orang yang berwenang menyetujui izin/sakit/lembur pegawai ini:
     * - Kepala sekolah                 -> Ketua Yayasan atau administrator yang ditunjuk (mis. Kabag Adm. Umum), salah satu
     * - Pegawai KB & TK                -> Kepala KB & TK
     * - Pegawai SD dan unit Yayasan    -> Kepala Sekolah SD
     */
    public function penyetujuUntuk(User $pengaju): Collection
    {
        $pengaju->loadMissing('unit', 'roles');

        if ($pengaju->hasRole(Role::KEPALA_SEKOLAH)) {
            $ditunjuk = self::idPenyetujuKs();

            return $this->pejabat()
                ->filter(fn ($u) => ! $u->is($pengaju)
                    && ($u->hasRole(Role::KETUA_YAYASAN)
                        || ($u->hasRole(Role::ADMIN) && ($ditunjuk === null || in_array($u->id, $ditunjuk, true)))))
                ->values();
        }

        $kodeUnit = $pengaju->unit?->kode === 'KBTK' ? 'KBTK' : 'SD';

        return $this->pejabat()
            ->filter(fn ($u) => ! $u->is($pengaju)
                && $u->hasRole(Role::KEPALA_SEKOLAH)
                && $u->unit?->kode === $kodeUnit)
            ->values();
    }

    /**
     * Kabag Adm. Umum untuk blok tanda tangan rekap: administrator pertama yang ditunjuk
     * menyetujui pengajuan kepala sekolah; bila belum diatur, administrator unit Yayasan.
     */
    public static function kabag(): ?User
    {
        $admin = User::where('status', 'Aktif')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::ADMIN))
            ->with('unit')->orderBy('id')->get();

        $ditunjuk = self::idPenyetujuKs() ?? [];

        return $admin->first(fn ($u) => in_array($u->id, $ditunjuk, true))
            ?? $admin->first(fn ($u) => $u->unit?->kode === 'YYS')
            ?? $admin->first();
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
            return $this->penyetujuUntuk($pengaju)
                ->map(fn ($u) => $u->hasRole(Role::KETUA_YAYASAN) ? 'Ketua Yayasan' : $u->nama_lengkap)
                ->unique()->join(' / ') ?: 'Ketua Yayasan';
        }

        return $this->penyetujuUntuk($pengaju)->first()?->nama_lengkap ?? 'Kepala Sekolah';
    }
}