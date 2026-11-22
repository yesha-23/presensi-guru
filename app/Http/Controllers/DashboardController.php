<?php

namespace App\Http\Controllers;

use App\Models\KalenderEvent;
use App\Models\Lembur;
use App\Models\LogKoreksi;
use App\Models\PengajuanIzin;
use App\Models\Pengaturan;
use App\Models\Presensi;
use App\Models\SuratTugas;
use App\Models\Unit;
use App\Services\KalenderService;
use App\Services\KehadiranService;
use App\Services\RekapService;
use App\Services\SuratTugasService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private KehadiranService $kehadiran)
    {
    }

    /** Administrator: Kehadiran hari ini (semua unit atau satu unit). */
    public function admin(Request $request)
    {
        $units = Unit::orderBy('id')->get();
        $unit = $units->firstWhere('id', $request->integer('unit'));
        $daftar = $this->kehadiran->hariIni($unit?->id);

        return view('dashboard.admin', [
            'judul'        => 'Kehadiran hari ini',
            'units'        => $units,
            'unit'         => $unit,
            'ringkas'      => KehadiranService::ringkas($daftar),
            'daftar'       => $daftar->where('status', '!=', 'Libur')->take(8),
            'menunggu'     => $this->menungguPersetujuan(),
            'jamPengingat' => Pengaturan::ambil('jam_pengingat_datang', '06:45'),
        ]);
    }

    /** Kepala sekolah: pantau kehadiran unitnya secara real-time (UR-23). */
    public function pantau(Request $request)
    {
        $kepsek = $request->user()->load('unit');
        $daftar = $this->kehadiran->hariIni($kepsek->unit_id)->where('status', '!=', 'Libur');

        $filter = $request->query('status');
        $saring = [
            'hadir'      => fn ($r) => in_array($r['status'], ['Tepat Waktu', 'Terlambat', 'Lewat Batas'], true),
            'terlambat'  => fn ($r) => in_array($r['status'], ['Terlambat', 'Lewat Batas'], true),
            'belum'      => fn ($r) => $r['status'] === 'Belum Presensi',
            'izin_sakit' => fn ($r) => in_array($r['status'], ['Izin', 'Sakit'], true),
            'tugas'      => fn ($r) => $r['status'] === 'Tugas Khusus',
        ];

        return view('dashboard.pantau', [
            'judul'        => 'Pantau Kehadiran',
            'unit'         => $kepsek->unit,
            'ringkas'      => KehadiranService::ringkas($daftar),
            'filter'       => isset($saring[$filter]) ? $filter : null,
            'daftar'       => isset($saring[$filter]) ? $daftar->filter($saring[$filter]) : $daftar,
            'jamPengingat' => Pengaturan::ambil('jam_pengingat_datang', '06:45'),
        ]);
    }

    /** Ketua yayasan: ringkasan seluruh unit (UR-26, UR-27). */
    public function yayasan(Request $request, RekapService $rekap, SuratTugasService $layananSurat, KalenderService $kalender)
    {
        $units = Unit::orderBy('id')->get();
        $semua = $this->kehadiran->hariIni();
        $hariIni = today();

        // C3: unit yang hari ini libur / bukan hari kerja ditandai, bukan ditampilkan "0 / 0 hadir"
        $perUnit = $units->map(function ($u) use ($semua, $kalender, $hariIni) {
            $daftarUnit = $semua->filter(fn ($r) => $r['user']->unit_id === $u->id);
            $entri = $kalender->entri($u->id, $hariIni, $hariIni);
            $jenis = $kalender->jenisHari($hariIni, $entri);
            $libur = $entri->where('berlaku_untuk', 'Guru dan Staf')->whereIn('kategori', KalenderEvent::KATEGORI_LIBUR)->first();

            return [
                'unit'          => $u,
                'ringkas'       => KehadiranService::ringkas($daftarUnit),
                'jumlahPegawai' => $daftarUnit->count(),
                'hariKerja'     => in_array($jenis, ['kerja', 'hkt'], true),
                'keterangan'    => match ($jenis) {
                    'libur'    => 'Libur: '.($libur?->nama ?? 'kalender akademik'),
                    'nonkerja' => 'Hari '.$hariIni->translatedFormat('l').' bukan hari kerja',
                    default    => null,
                },
            ];
        })->filter(fn ($x) => $x['jumlahPegawai'] > 0)->values();

        // Kehadiran bulan ini per unit (bulan lalu bila bulan ini belum ada hari efektif)
        $mulai = today()->startOfMonth();
        $hasil = $rekap->hitung($rekap->pegawai($mulai, $mulai->copy()->endOfMonth()), $mulai, $mulai->copy()->endOfMonth());
        if ($hasil['akhir']->lt($mulai)) {
            $mulai = today()->subMonthNoOverflow()->startOfMonth();
            $hasil = $rekap->hitung($rekap->pegawai($mulai, $mulai->copy()->endOfMonth()), $mulai, $mulai->copy()->endOfMonth());
        }

        $bulanan = $units->map(function ($u) use ($hasil) {
            $baris = $hasil['baris']->filter(fn ($r) => $r['user']->unit_id === $u->id && $r['efektif'] > 0);

            return [
                'unit'   => $u,
                'persen' => $baris->isEmpty() ? null : round($baris->avg('persen'), 1),
                'alpa'   => $baris->isEmpty() ? null : round($baris->avg('persen_alpa'), 1),
            ];
        })->filter(fn ($x) => $x['persen'] !== null)->values();

        $suratMenunggu = SuratTugas::with(['persetujuan', 'pegawai.unit'])
            ->where('status', 'Menunggu')->orderBy('tanggal_mulai')->oldest()->get()
            ->filter(fn ($s) => $layananSurat->bolehMemproses($request->user(), $s))->values();

        return view('dashboard.yayasan', [
            'judul'         => 'Ringkasan Yayasan',
            'perUnit'       => $perUnit,
            'bulanan'       => $bulanan,
            'bulanLabel'    => $mulai->translatedFormat('F Y'),
            'akhirBulanan'  => $hasil['akhir'],
            'suratMenunggu' => $suratMenunggu,
        ]);
    }

    /** Semua pengajuan yang masih menunggu, untuk panel admin. */
    private function menungguPersetujuan(): array
    {
        $item = collect();

        PengajuanIzin::with('user.unit')->where('status', 'Menunggu')->get()->each(fn ($p) => $item->push([
            'jenis' => strtoupper($p->jenis), 'warna' => 'text-cyan-800',
            'judul' => $p->user?->nama_lengkap,
            'ket'   => $p->tanggal_mulai->translatedFormat('j F Y').' · '.$p->user?->unit?->label(),
            'waktu' => $p->created_at,
        ]));

        Lembur::with('user.unit')->where('status', 'Menunggu')->get()->each(fn ($l) => $item->push([
            'jenis' => 'LEMBUR', 'warna' => 'text-orange-800',
            'judul' => $l->user?->nama_lengkap,
            'ket'   => $l->tanggal->translatedFormat('j F Y').' · '.substr($l->jam_mulai, 0, 5).'–'.substr($l->jam_selesai, 0, 5),
            'waktu' => $l->created_at,
        ]));

        SuratTugas::with('persetujuan')->where('status', 'Menunggu')->get()->each(fn ($s) => $item->push([
            'jenis' => 'SURAT TUGAS'.($s->kasus_khusus ? ' · LUAR KOTA / KASUS KHUSUS' : ''), 'warna' => 'text-emerald-800',
            'judul' => $s->perihal,
            'ket'   => $s->labelStatus(),
            'waktu' => $s->updated_at,
        ]));

        LogKoreksi::with('target.user')->where('target_type', Presensi::class)
            ->where('status_persetujuan', 'Menunggu')->get()->each(fn ($l) => $item->push([
                'jenis' => 'KOREKSI PRESENSI', 'warna' => 'text-navy',
                'judul' => $l->target?->user?->nama_lengkap,
                'ket'   => 'Menunggu kepala sekolah',
                'waktu' => $l->created_at,
            ]));

        return ['jumlah' => $item->count(), 'item' => $item->sortByDesc('waktu')->take(4)->values()];
    }
}