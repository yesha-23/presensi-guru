<?php

namespace App\Services;

use App\Models\Lembur;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\SuratTugas;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RekapService
{
    public function __construct(private KalenderService $kalender)
    {
    }

    /**
     * Pegawai yang masuk rekap: pegawai aktif wajib presensi, ditambah arsip
     * (keluar/nonaktif) yang punya presensi pada periode tersebut.
     */
    public function pegawai(Carbon $mulai, Carbon $selesai, ?int $unitId = null, ?string $kelompok = null): Collection
    {
        return User::withTrashed()
            ->with('unit')
            ->whereNotNull('kategori')
            ->where(fn ($q) => $q
                ->where(fn ($a) => $a->where('status', 'Aktif')->whereNull('deleted_at')
                    ->whereHas('roles', fn ($r) => $r->where('nama', Role::PEGAWAI)))
                ->orWhereHas('presensi', fn ($p) => $p->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])))
            ->when($unitId, fn ($q, $id) => $q->where('unit_id', $id))
            ->when($kelompok === 'Guru', fn ($q) => $q->where('kategori', 'Guru'))
            ->when($kelompok === 'Staf', fn ($q) => $q->where('kategori', '!=', 'Guru'))
            ->orderBy('unit_id')
            ->orderBy('nama_lengkap')
            ->get();
    }

    /**
     * Hitung rekap per pegawai. Data dihitung sampai kemarin (hari ini belum ditutup).
     * Lewat Batas dihitung Hadir dan Terlambat; jumlahnya juga ditampilkan di Keterangan.
     *
     * @return array{baris: Collection, akhir: Carbon, efektifUnit: array<int,int>}
     */
    public function hitung(Collection $pegawai, Carbon $mulai, Carbon $selesai): array
    {
        $akhir = $selesai->copy()->min(today()->subDay());
        $kosong = $akhir->lt($mulai);
        $rentang = [$mulai->toDateString(), $akhir->toDateString()];
        $ids = $pegawai->pluck('id');

        $presensi = $kosong ? collect() : Presensi::with('referensi')
            ->whereIn('user_id', $ids)->whereBetween('tanggal', $rentang)->get()->groupBy('user_id');

        $lembur = $kosong ? collect() : Lembur::whereIn('user_id', $ids)
            ->where('status', 'Disetujui')->whereBetween('tanggal', $rentang)->get()->groupBy('user_id');

        $efektifUnit = [];

        $baris = $pegawai->map(function (User $u) use ($presensi, $lembur, $mulai, $akhir, $kosong, &$efektifUnit) {
            $efektif = $kosong ? 0 : ($efektifUnit[$u->unit_id] ??= $this->kalender->hariEfektif($u->unit_id, $mulai, $akhir)['total']);

            $p = $presensi->get($u->id, collect());
            $hadir = $p->where('status_harian', 'Hadir');
            $tugas = $p->where('status_harian', 'Tugas Khusus');
            $izin = $p->where('status_harian', 'Izin')->count();
            $sakit = $p->where('status_harian', 'Sakit')->count();
            $alpa = max(0, $efektif - $hadir->count() - $tugas->count() - $izin - $sakit);
            $lewatBatas = $hadir->where('status_datang', Presensi::LEWAT_BATAS)->count();

            // Rincian tugas khusus dari surat tugas yang menjadi referensinya
            $jumlahTugas = fn (callable $syarat) => $tugas
                ->filter(fn ($r) => $r->referensi instanceof SuratTugas && $syarat($r->referensi))
                ->count();

            $persen = $efektif ? min(100, round(($hadir->count() + $tugas->count()) / $efektif * 100, 1)) : 0;

            return [
                'user'            => $u,
                'kelompok'        => $u->kategori === 'Guru' ? 'Guru' : 'Staf',
                'efektif'         => $efektif,
                'hadir'           => $hadir->count(),
                'tepat'           => $hadir->where('status_datang', 'Tepat Waktu')->count(),
                // Lewat Batas tetap terhitung terlambat (dan Hadir)
                'terlambat'       => $hadir->whereIn('status_datang', ['Terlambat', Presensi::LEWAT_BATAS])->count(),
                'lewat_batas'     => $lewatBatas,
                'detik_terlambat' => (int) $hadir->sum('detik_terlambat'),
                'pulang_cepat'    => $hadir->where('pulang_cepat', true)->count(),
                'tidak_pulang'    => $hadir->whereNull('jam_pulang')->count(),
                'izin'            => $izin,
                'sakit'           => $sakit,
                'tugas'           => $tugas->count(),
                'alpa'            => $alpa,
                'dnas'            => $jumlahTugas(fn ($s) => $s->pemberi_tugas === 'Dinas'),
                'kdlm'            => $jumlahTugas(fn ($s) => $s->jenis_kegiatan === 'Dalam'),
                'kklr'            => $jumlahTugas(fn ($s) => $s->jenis_kegiatan === 'Luar'),
                'menit_lembur'    => (int) $lembur->get($u->id, collect())->sum('durasi_disetujui_menit'),
                'persen'          => $persen,
                'persen_alpa'     => $efektif ? round($alpa / $efektif * 100, 1) : 0,
                'keterangan'      => collect([
                    'Lewat batas' => $lewatBatas,
                    'Izin'        => $izin,
                    'Sakit'       => $sakit,
                    'Alpa'        => $alpa,
                ])->filter()->map(fn ($n, $k) => "{$k} {$n}")->join(', ') ?: '—',
            ];
        });

        return ['baris' => $baris, 'akhir' => $akhir, 'efektifUnit' => $efektifUnit];
    }

    /** Detik -> jam, 1 desimal (TLAT). */
    public static function jam(int $detik): float
    {
        return round($detik / 3600, 1);
    }

    /** Menit -> jam, 1 desimal (LEMB). */
    public static function jamDariMenit(int $menit): float
    {
        return round($menit / 60, 1);
    }

    /** 0.4 -> "0,4"; 2.0 -> "2" (seperti format rekap yayasan). */
    public static function angka(float $nilai): string
    {
        return floor($nilai) == $nilai ? (string) (int) $nilai : str_replace('.', ',', (string) $nilai);
    }
}