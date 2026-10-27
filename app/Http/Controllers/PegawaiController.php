<?php

namespace App\Http\Controllers;

use App\Models\KalenderEvent;
use App\Models\Lembur;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\User;
use App\Services\JadwalService;
use App\Services\KalenderService;
use App\Services\LemburService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PegawaiController extends Controller
{
    /** Status datang yang berarti pegawai hadir. */
    private const HADIR = ['Tepat Waktu', 'Terlambat', Presensi::LEWAT_BATAS];

    /** Status datang yang dihitung terlambat. */
    private const TERLAMBAT = ['Terlambat', Presensi::LEWAT_BATAS];

    public function beranda(Request $request, JadwalService $jadwal)
    {
        $user = $request->user()->load('unit');
        $sekarang = now();

        $hariIni = Presensi::where('user_id', $user->id)
            ->whereDate('tanggal', $sekarang)
            ->first();

        // Jadwal hari ini mengikuti kalender (termasuk Hari Kerja Tambahan / libur)
        $kondisi = $jadwal->kondisiKhusus($user, $sekarang);
        $jadwalHariIni = $kondisi ? null : $jadwal->jadwal($user, $sekarang);

        // Batas presensi datang hari ini (lewat dari ini berlabel "Lewat Batas")
        $batasDatang = $jadwalHariIni ? Presensi::batasDatang($jadwalHariIni) : null;

        $bulanIni = Presensi::where('user_id', $user->id)
            ->whereYear('tanggal', $sekarang->year)
            ->whereMonth('tanggal', $sekarang->month)
            ->get();

        $rekap = [
            'Hadir'     => $bulanIni->where('status_harian', 'Hadir')->count(),
            'Terlambat' => $bulanIni->whereIn('status_datang', self::TERLAMBAT)->count(),
            'Izin'      => $bulanIni->where('status_harian', 'Izin')->count(),
            'Sakit'     => $bulanIni->where('status_harian', 'Sakit')->count(),
        ];

        $salam = match (true) {
            $sekarang->hour < 11 => 'Selamat pagi',
            $sekarang->hour < 15 => 'Selamat siang',
            $sekarang->hour < 18 => 'Selamat sore',
            default              => 'Selamat malam',
        };

        return view('pegawai.beranda', [
            'judul'             => 'Beranda',
            'user'              => $user,
            'hariIni'           => $hariIni,
            'kondisi'           => $kondisi,
            'batasDatang'       => $batasDatang,
            'jadwalHariIni'     => $jadwalHariIni,
            'rekap'             => $rekap,
            'pengajuanTerakhir' => PengajuanIzin::where('user_id', $user->id)->latest()->first(),
            'salam'             => $salam,
        ]);
    }

    /** Alamat lama: surat tugas kini menjadi tab di halaman Riwayat. */
    public function suratTugas()
    {
        return redirect()->route('pegawai.riwayat', ['tab' => 'surat-tugas']);
    }

    /** SR-F16: riwayat presensi per bulan & surat tugas pribadi. */
    public function riwayat(Request $request, KalenderService $kalender)
    {
        // Tautan lama (?tab=pengajuan) diarahkan ke menu Pengajuan
        if ($request->query('tab') === 'pengajuan') {
            return redirect()->route('pegawai.pengajuan');
        }

        $user = $request->user()->load('unit');
        $tab = $request->query('tab') === 'surat-tugas' ? 'surat-tugas' : 'presensi';

        $bulan = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan'))
            ? Carbon::createFromFormat('Y-m-d', $request->query('bulan').'-01')->startOfDay()
            : today()->startOfMonth();
        if ($bulan->gt(today())) {
            $bulan = today()->startOfMonth();
        }

        $data = [
            'judul' => 'Riwayat',
            'tab'   => $tab,
            'bulan' => $bulan,
        ];

        // Hanya data tab yang sedang dibuka yang diambil
        return view('pegawai.riwayat', $tab === 'presensi'
            ? $data + $this->dataPresensi($user, $bulan, $kalender)
            : $data + $this->dataSuratTugas($user, $bulan) + ['sorot' => $request->query('sorot')]);
    }

    /** Menu Pengajuan: ajukan, pantau status, edit & batalkan (izin, sakit, lembur). */
    public function pengajuan(Request $request)
    {
        $semua = $this->daftarPengajuan($request->user());

        $status = in_array($request->query('status'), ['Menunggu', 'Disetujui', 'Ditolak'], true)
            ? $request->query('status')
            : null;

        return view('pegawai.pengajuan', [
            'judul'     => 'Pengajuan',
            'pengajuan' => $status ? $semua->where('status', $status)->values() : $semua,
            'status'    => $status,
            'sorot'     => session('sorot'),
            'ringkas'   => [
                'Menunggu'  => $semua->where('status', 'Menunggu')->count(),
                'Disetujui' => $semua->where('status', 'Disetujui')->count(),
                'Ditolak'   => $semua->where('status', 'Ditolak')->count(),
            ],
        ]);
    }

    /** UR-02: profil pegawai (akun & kata sandi dikelola administrator). */
    public function profil(Request $request)
    {
        $user = $request->user()->load(['unit', 'roles'])->loadCount('faceDescriptors');

        return view('pegawai.profil', [
            'judul'         => 'Profil',
            'user'          => $user,
            'jamKerja'      => $user->jamKerja(),
            'wajahTerakhir' => $user->faceDescriptors()->latest()->value('created_at'),
        ]);
    }

    // ================= Helper =================

    /** Tab Presensi: satu baris per hari kerja (dan hari libur yang punya catatan), terbaru di atas. */
    private function dataPresensi(User $user, Carbon $bulan, KalenderService $kalender): array
    {
        $akhirBulan = $bulan->copy()->endOfMonth()->startOfDay();
        $sampai = $akhirBulan->copy()->min(today());

        $presensi = Presensi::where('user_id', $user->id)
            ->whereBetween('tanggal', [$bulan->toDateString(), $akhirBulan->toDateString()])
            ->get()->keyBy(fn ($p) => $p->tanggal->toDateString());
        $entri = $kalender->entri($user->unit_id, $bulan, $akhirBulan);

        $hari = collect();
        if ($sampai->gte($bulan)) {
            foreach (CarbonPeriod::create($bulan, $sampai) as $t) {
                $t = $t->copy();
                $p = $presensi->get($t->toDateString());
                $jenis = $kalender->jenisHari($t, $entri);

                if (! $p && in_array($jenis, ['nonkerja', 'libur'], true)) {
                    continue;
                }

                $hkt = $jenis === 'hkt'
                    ? $entri->first(fn ($e) => $e->kategori === KalenderEvent::HARI_KERJA_TAMBAHAN
                        && $t->between($e->tanggal_mulai, $e->tanggal_selesai))
                    : null;

                $hari->push([
                    'tanggal'  => $t,
                    'presensi' => $p,
                    'hkt'      => $hkt?->nama,
                    // Status Tugas Khusus merujuk ke surat_tugas (referensi polimorfik)
                    'surat_id' => $p?->status_harian === 'Tugas Khusus' ? $p->referensi_id : null,
                    'status'   => match (true) {
                        (bool) $p?->jam_datang    => $p->status_datang,
                        (bool) $p?->status_harian => $p->status_harian,
                        $t->isToday()             => 'Belum Presensi',
                        default                   => 'Alpa',
                    },
                ]);
            }
        }
        $hari = $hari->reverse()->values();

        return [
            'hari'    => $hari,
            'ringkas' => [
                'Hadir'      => $hari->whereIn('status', self::HADIR)->count(),
                'Terlambat'  => $hari->whereIn('status', self::TERLAMBAT)->count(),
                'Izin/Sakit' => $hari->whereIn('status', ['Izin', 'Sakit'])->count(),
                'Alpa'       => $hari->where('status', 'Alpa')->count(),
            ],
        ];
    }

    /**
     * Tab Surat Tugas:
     * - aktif : berlangsung & akan datang (tidak ikut filter bulan), terdekat dulu
     * - arsip : sudah selesai, per bulan, terbaru dulu
     */
    private function dataSuratTugas(User $user, Carbon $bulan): array
    {
        $hariIni = today();
        $akhirBulan = $bulan->copy()->endOfMonth()->startOfDay();

        $dasar = fn () => $user->suratTugas()
            ->with('persetujuan')
            ->whereIn('surat_tugas.status', ['Menunggu', 'Disetujui']);

        // Belum selesai: yang sedang berlangsung otomatis di atas karena mulainya lebih awal
        $aktif = $dasar()
            ->whereDate('surat_tugas.tanggal_selesai', '>=', $hariIni)
            ->orderBy('surat_tugas.tanggal_mulai')
            ->get()
            ->each(fn ($s) => $s->setAttribute(
                'tahap',
                $s->tanggal_mulai->lte($hariIni) ? 'Berlangsung' : 'Akan datang'
            ));

        // Sudah selesai dan rentang tanggalnya bersinggungan dengan bulan yang dipilih
        $arsip = $dasar()
            ->whereDate('surat_tugas.tanggal_selesai', '<', $hariIni)
            ->whereDate('surat_tugas.tanggal_mulai', '<=', $akhirBulan)
            ->whereDate('surat_tugas.tanggal_selesai', '>=', $bulan)
            ->orderByDesc('surat_tugas.tanggal_mulai')
            ->get();

        return [
            'aktif' => $aktif,
            'arsip' => $arsip,
        ];
    }

    /** Gabungan izin, sakit, dan lembur (masing-masing 20 terbaru), urut terbaru di atas. */
    private function daftarPengajuan(User $user): Collection
    {
        // "Diubah" jika updated_at > 1 menit setelah dibuat dan masih Menunggu
        $diubah = fn ($m) => $m->status === 'Menunggu' && $m->updated_at?->gt($m->created_at->copy()->addMinute())
            ? $m->updated_at
            : null;

        $izin = PengajuanIzin::where('user_id', $user->id)->latest()->limit(20)->get()
            ->map(fn ($p) => [
                'kunci'   => 'izin-'.$p->id,
                'jenis'   => $p->jenis,
                'judul'   => $p->tanggal_mulai->equalTo($p->tanggal_selesai)
                    ? $p->tanggal_mulai->translatedFormat('j F Y')
                    : $p->tanggal_mulai->translatedFormat('j M').' – '.$p->tanggal_selesai->translatedFormat('j M Y'),
                'uraian'  => $p->alasan,
                'status'  => $p->status,
                'waktu'   => $p->created_at,
                'diubah'  => $diubah($p),
                'catatan' => $p->alasan_penolakan,
                'bukti'   => $p->bukti_path ? route('pengajuan.bukti', $p) : null,
                'edit'    => $p->status === 'Menunggu' ? route('pegawai.izin.edit', $p) : null,
                'batal'   => $p->status === 'Menunggu' ? route('pegawai.izin.batal', $p) : null,
            ]);

        $lembur = Lembur::where('user_id', $user->id)->latest()->limit(20)->get()
            ->map(fn ($l) => [
                'kunci'   => 'lembur-'.$l->id,
                'jenis'   => 'Lembur',
                'judul'   => $l->tanggal->translatedFormat('j F Y').' · '.substr($l->jam_mulai, 0, 5).'–'.substr($l->jam_selesai, 0, 5)
                    .($l->status === 'Disetujui' && $l->durasi_disetujui_menit !== null ? ' · diakui '.LemburService::formatMenit($l->durasi_disetujui_menit) : ''),
                'uraian'  => $l->uraian,
                'status'  => $l->status,
                'waktu'   => $l->created_at,
                'diubah'  => $diubah($l),
                'catatan' => $l->alasan_penolakan,
                'bukti'   => null,
                'edit'    => $l->status === 'Menunggu' ? route('pegawai.lembur.edit', $l) : null,
                'batal'   => $l->status === 'Menunggu' ? route('pegawai.lembur.batal', $l) : null,
            ]);

        return $izin->concat($lembur)->sortByDesc('waktu')->values();
    }
}