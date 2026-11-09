<?php

namespace App\Services;

use App\Models\Lembur;
use App\Models\Pengaturan;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\SuratTugas;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RekapService
{
    /** Pilihan pembulatan jam TLAT/LEMB (Pengaturan "pembulatan_jam_rekap"). */
    public const BULAT_SETENGAH = 'setengah';           // ke 0,5 terdekat (format rekap KB-TK: 2,0; 3,5)
    public const BULAT_SETENGAH_ATAS = 'setengah_atas'; // selalu ke atas ke kelipatan 0,5
    public const BULAT_DESIMAL = 'desimal';             // 1 angka desimal biasa

    public function __construct(private KalenderService $kalender)
    {
    }

    // ================= Pengaturan rekap =================

    /** Tanggal awal periode bulanan unit (1 = kalender; 26 = periode 26 bulan lalu s.d. 25). */
    public static function awalPeriode(?Unit $unit): int
    {
        if (! $unit || ! in_array($unit->kode, ['SD', 'KBTK'], true)) {
            return 1;
        }

        $bawaan = $unit->kode === 'KBTK' ? 26 : 1;

        return max(1, min(28, (int) Pengaturan::ambil('awal_periode_rekap_'.$unit->kode, $bawaan)));
    }

    /**
     * Pegawai unit Yayasan yang ikut ditampilkan di rekap SD / KB-TK beserta kelompoknya.
     * Bentuk: [user_id => ['SD' => 'Staf'|'Guru'|'', 'KBTK' => ...]]
     */
    public static function tampilanYayasan(): array
    {
        $data = json_decode((string) Pengaturan::ambil('rekap_pegawai_yayasan', '{}'), true);

        return is_array($data) ? $data : [];
    }

    /** Format kolom rekap untuk unit (config/rekap.php). */
    public static function format(?Unit $unit): array
    {
        $kode = $unit?->kode === 'KBTK' ? 'KBTK' : 'SD';

        return config("rekap.format.{$kode}");
    }

    // ================= Data =================

    /**
     * Pegawai yang masuk rekap: pegawai aktif wajib presensi, ditambah arsip
     * (keluar/nonaktif) yang punya presensi pada periode tersebut.
     * Rekap SD / KB-TK juga memuat pegawai Yayasan yang diatur di Pengaturan > Rekap
     * (mis. Kabag Adm. Umum), dengan kelompok sesuai pengaturan.
     * Setiap pegawai diberi atribut sementara kelompok_rekap dan unit_rekap_id (tidak disimpan).
     */
    public function pegawai(Carbon $mulai, Carbon $selesai, ?int $unitId = null, ?string $kelompok = null, bool $ikutYayasan = true): Collection
    {
        $dasar = fn () => User::withTrashed()
            ->with('unit')
            ->whereNotNull('kategori')
            ->where(fn ($q) => $q
                ->where(fn ($a) => $a->where('status', 'Aktif')->whereNull('deleted_at')
                    ->whereHas('roles', fn ($r) => $r->where('nama', Role::PEGAWAI)))
                ->orWhereHas('presensi', fn ($p) => $p->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])));

        $pegawai = $dasar()
            ->when($unitId, fn ($q, $id) => $q->where('unit_id', $id))
            ->get()
            ->each(fn (User $u) => $u->setAttribute('kelompok_rekap', $u->kategori === 'Guru' ? 'Guru' : 'Staf')
                ->setAttribute('unit_rekap_id', $u->unit_id));

        $unit = $unitId ? Unit::find($unitId) : null;
        if ($ikutYayasan && in_array($unit?->kode, ['SD', 'KBTK'], true)) {
            $atur = collect(self::tampilanYayasan())
                ->map(fn ($a) => $a[$unit->kode] ?? '')
                ->filter(fn ($k) => in_array($k, ['Guru', 'Staf'], true));

            if ($atur->isNotEmpty()) {
                $tambahan = $dasar()
                    ->whereIn('id', $atur->keys())
                    ->where('unit_id', '!=', $unit->id)
                    ->get()
                    ->each(fn (User $u) => $u->setAttribute('kelompok_rekap', $atur[$u->id])
                        ->setAttribute('unit_rekap_id', $unit->id));

                $pegawai = $pegawai->concat($tambahan);
            }
        }

        return $pegawai
            ->when($kelompok, fn ($c, $k) => $c->where('kelompok_rekap', $k))
            ->sortBy([['unit_rekap_id', 'asc'], ['nama_lengkap', 'asc']])
            ->values();
    }

    /**
     * Hitung rekap per pegawai. Data dihitung sampai kemarin (hari ini belum ditutup).
     * Lewat Batas dihitung Hadir dan Terlambat; jumlahnya juga ditampilkan di Keterangan.
     * Tugas Khusus di luar hari efektif (mis. pelayanan hari Minggu) tetap dihitung di KDLM/KKLR,
     * tetapi tidak mengurangi Alpa dan tidak menambah persentase kehadiran.
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
        $entriUnit = [];

        $baris = $pegawai->map(function (User $u) use ($presensi, $lembur, $mulai, $akhir, $kosong, &$efektifUnit, &$entriUnit) {
            $unitRekap = $u->getAttribute('unit_rekap_id') ?? $u->unit_id;
            $efektif = $kosong ? 0 : ($efektifUnit[$unitRekap] ??= $this->kalender->hariEfektif($unitRekap, $mulai, $akhir)['total']);

            $p = $presensi->get($u->id, collect());
            $hadir = $p->where('status_harian', 'Hadir');
            $tugas = $p->where('status_harian', 'Tugas Khusus');
            $izin = $p->where('status_harian', 'Izin')->count();
            $sakit = $p->where('status_harian', 'Sakit')->count();
            $lewatBatas = $hadir->where('status_datang', Presensi::LEWAT_BATAS)->count();

            // Tugas di hari efektif vs di luar hari efektif (Sabtu/Minggu/libur)
            $entri = $kosong ? collect() : ($entriUnit[$unitRekap] ??= $this->kalender->entri($unitRekap, $mulai, $akhir));
            $tugasEfektif = $tugas->filter(fn ($r) => in_array($this->kalender->jenisHari($r->tanggal->copy(), $entri), ['kerja', 'hkt'], true))->count();
            $tugasLibur = $tugas->count() - $tugasEfektif;

            $alpa = max(0, $efektif - $hadir->count() - $tugasEfektif - $izin - $sakit);

            // Rincian tugas khusus dari surat tugas yang menjadi referensinya
            $jumlahTugas = fn (callable $syarat) => $tugas
                ->filter(fn ($r) => $r->referensi instanceof SuratTugas && $syarat($r->referensi))
                ->count();

            $persen = $efektif ? min(100, round(($hadir->count() + $tugasEfektif) / $efektif * 100, 1)) : 0;

            return [
                'user'            => $u,
                'kelompok'        => $u->getAttribute('kelompok_rekap') ?? ($u->kategori === 'Guru' ? 'Guru' : 'Staf'),
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
                'tugas_libur'     => $tugasLibur,
                'alpa'            => $alpa,
                'dnas'            => $jumlahTugas(fn ($s) => $s->pemberi_tugas === 'Dinas'),
                'kdlm'            => $jumlahTugas(fn ($s) => $s->jenis_kegiatan === 'Dalam'),
                'kklr'            => $jumlahTugas(fn ($s) => $s->jenis_kegiatan === 'Luar'),
                'menit_lembur'    => (int) $lembur->get($u->id, collect())->sum('durasi_disetujui_menit'),
                'persen'          => $persen,
                'persen_alpa'     => $efektif ? round($alpa / $efektif * 100, 1) : 0,
                'keterangan'      => collect([
                    'Lewat batas'       => $lewatBatas,
                    'Izin'              => $izin,
                    'Sakit'             => $sakit,
                    'Alpa'              => $alpa,
                    'Tugas hari libur'  => $tugasLibur,
                ])->filter()->map(fn ($n, $k) => "{$k} {$n}")->join(', ') ?: '—',
            ];
        });

        return ['baris' => $baris, 'akhir' => $akhir, 'efektifUnit' => $efektifUnit];
    }

    // ================= Format angka =================

    /** Nilai satu kolom rekap (isi dari config/rekap.php) dalam bentuk teks. Kolom manual = ''. */
    public static function nilaiKolom(array $r, ?string $isi): string
    {
        return match ($isi) {
            'hadir' => (string) $r['hadir'],
            'dnas'  => (string) $r['dnas'],
            'tlat'  => self::angka(self::jam($r['detik_terlambat'])),
            'lemb'  => self::angka(self::jamDariMenit($r['menit_lembur'])),
            'kdlm'  => (string) $r['kdlm'],
            'kklr'  => (string) $r['kklr'],
            default => '',
        };
    }

    /** Nilai numerik untuk sel Excel (null = kolom manual). */
    public static function nilaiAngka(array $r, ?string $isi): int|float|null
    {
        return match ($isi) {
            'hadir' => $r['hadir'],
            'dnas'  => $r['dnas'],
            'tlat'  => self::jam($r['detik_terlambat']),
            'lemb'  => self::jamDariMenit($r['menit_lembur']),
            'kdlm'  => $r['kdlm'],
            'kklr'  => $r['kklr'],
            default => null,
        };
    }

    /** Pembulatan jam sesuai Pengaturan > Rekap. */
    public static function bulatkan(float $jam): float
    {
        return match (Pengaturan::ambil('pembulatan_jam_rekap', self::BULAT_SETENGAH)) {
            self::BULAT_DESIMAL       => round($jam, 1),
            self::BULAT_SETENGAH_ATAS => ceil(round($jam * 2, 6)) / 2,
            default                   => round($jam * 2) / 2,
        };
    }

    /** Detik -> jam (TLAT/TLBT). */
    public static function jam(int $detik): float
    {
        return self::bulatkan($detik / 3600);
    }

    /** Menit -> jam (LEMB/LMBR). */
    public static function jamDariMenit(int $menit): float
    {
        return self::bulatkan($menit / 60);
    }

    /** 0.5 -> "0,5"; 2.0 -> "2" (seperti format rekap yayasan). */
    public static function angka(float $nilai): string
    {
        return floor($nilai) == $nilai ? (string) (int) $nilai : str_replace('.', ',', (string) $nilai);
    }
}
