<?php

namespace App\Services;

use App\Models\KalenderEvent;
use App\Models\PengajuanIzin;
use App\Models\Pengaturan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class JadwalService
{
    /** Entri kalender untuk guru & staf di unit pegawai pada tanggal tertentu. */
    public function entriKalender(User $user, Carbon $tanggal): Collection
    {
        return KalenderEvent::where('berlaku_untuk', 'Guru dan Staf')
            ->where(fn ($q) => $q->whereNull('unit_id')->orWhere('unit_id', $user->unit_id))
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->get();
    }

    /**
     * Jadwal kerja pegawai pada tanggal itu, atau null jika bukan hari kerja.
     * Hari kerja = Senin–Jumat (pengaturan), dikurangi libur, ditambah Hari Kerja Tambahan (SR-F26).
     */
    public function jadwal(User $user, Carbon $tanggal): ?array
    {
        // Sudah memperhitungkan jam kerja khusus pegawai (bila ada)
        $jamKerja = $user->jamKerja();
        if (! $jamKerja) {
            return null;
        }

        $entri = $this->entriKalender($user, $tanggal);
        $hkt = $entri->firstWhere('kategori', KalenderEvent::HARI_KERJA_TAMBAHAN);

        if ($hkt) {
            // Sabtu rapat: jam masuk/pulang khusus dari kalender
            $masuk = $hkt->jam_masuk ?? $jamKerja->jam_masuk;
            $pulang = $hkt->jam_pulang ?? $jamKerja->jam_pulang;
        } else {
            $hariKerja = array_map('trim', explode(',', (string) Pengaturan::ambil('hari_kerja', '1,2,3,4,5')));

            if (! in_array((string) $tanggal->dayOfWeekIso, $hariKerja, true)) {
                return null;
            }
            if ($entri->whereIn('kategori', KalenderEvent::KATEGORI_LIBUR)->isNotEmpty()) {
                return null;
            }

            $masuk = $jamKerja->jam_masuk;
            $pulang = $jamKerja->jam_pulang;
        }

        $tgl = $tanggal->toDateString();

        return [
            'masuk'     => Carbon::parse("{$tgl} {$masuk}"),
            'pulang'    => Carbon::parse("{$tgl} {$pulang}"),
            'toleransi' => (int) $jamKerja->toleransi_menit,
            'hkt'       => (bool) $hkt,
            'khusus'    => $user->jamKerjaKhusus !== null,
        ];
    }

    /** Kondisi yang membuat pegawai tidak perlu presensi: izin/sakit, tugas khusus, libur. */
    public function kondisiKhusus(User $user, Carbon $tanggal): ?array
    {
        $izin = PengajuanIzin::where('user_id', $user->id)
            ->where('status', 'Disetujui')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        if ($izin) {
            return ['status' => $izin->jenis, 'keterangan' => "Pengajuan {$izin->jenis} Anda untuk hari ini sudah disetujui.", 'referensi' => $izin];
        }

        $tugas = $user->suratTugas()
            ->where('surat_tugas.status', 'Disetujui')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        if ($tugas) {
            return ['status' => 'Tugas Khusus', 'keterangan' => "Anda sedang menjalankan tugas: {$tugas->perihal}.", 'referensi' => $tugas];
        }

        if (! $this->jadwal($user, $tanggal)) {
            $libur = $this->entriKalender($user, $tanggal)->whereIn('kategori', KalenderEvent::KATEGORI_LIBUR)->first();

            return [
                'status'     => 'Libur',
                'keterangan' => $libur ? "Hari ini libur: {$libur->nama}." : 'Hari ini bukan hari kerja.',
                'referensi'  => $libur,
            ];
        }

        return null;
    }
}