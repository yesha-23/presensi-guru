<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Services\JadwalService;
use Illuminate\Http\Request;
use App\Models\KalenderEvent;
use App\Models\Lembur;
use App\Services\KalenderService;
use App\Services\LemburService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class PegawaiController extends Controller
{
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

        // Batas presensi datang hari ini (paling lambat jam pulang)
        $batasDatang = $jadwalHariIni
            ? \Carbon\Carbon::parse($sekarang->toDateString().' '.\App\Models\Pengaturan::ambil('batas_presensi_datang', '12:00'))
                ->min($jadwalHariIni['pulang'])
            : null;

        $bulanIni = Presensi::where('user_id', $user->id)
            ->whereYear('tanggal', $sekarang->year)
            ->whereMonth('tanggal', $sekarang->month)
            ->get();

        $rekap = [
            'Hadir'     => $bulanIni->where('status_harian', 'Hadir')->count(),
            'Terlambat' => $bulanIni->where('status_datang', 'Terlambat')->count(),
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

    public function suratTugas(Request $request)
    {
        $surat = $request->user()->suratTugas()
            ->with('persetujuan')
            ->whereIn('surat_tugas.status', ['Menunggu', 'Disetujui'])
            ->orderByDesc('tanggal_mulai')
            ->get();

        return view('pegawai.surat-tugas', [
            'judul' => 'Surat Tugas Saya',
            'surat' => $surat,
        ]);
    }

    /** SR-F16: riwayat presensi per bulan & status pengajuan. */
    public function riwayat(Request $request, KalenderService $kalender)
    {
        $user = $request->user()->load('unit');

        $bulan = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan'))
            ? Carbon::createFromFormat('Y-m-d', $request->query('bulan').'-01')->startOfDay()
            : today()->startOfMonth();
        if ($bulan->gt(today())) {
            $bulan = today()->startOfMonth();
        }
        $akhirBulan = $bulan->copy()->endOfMonth()->startOfDay();
        $sampai = $akhirBulan->copy()->min(today());

        $presensi = Presensi::where('user_id', $user->id)
            ->whereBetween('tanggal', [$bulan->toDateString(), $akhirBulan->toDateString()])
            ->get()->keyBy(fn ($p) => $p->tanggal->toDateString());
        $entri = $kalender->entri($user->unit_id, $bulan, $akhirBulan);

        // Satu baris per hari kerja (dan hari libur yang punya catatan), terbaru di atas
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

        // Status semua pengajuan (izin, sakit, lembur), terbaru di atas
        $pengajuan = PengajuanIzin::where('user_id', $user->id)->latest()->limit(20)->get()
            ->map(fn ($p) => [
                'jenis'   => $p->jenis,
                'judul'   => $p->tanggal_mulai->equalTo($p->tanggal_selesai)
                    ? $p->tanggal_mulai->translatedFormat('j F Y')
                    : $p->tanggal_mulai->translatedFormat('j M').' – '.$p->tanggal_selesai->translatedFormat('j M Y'),
                'status'  => $p->status,
                'waktu'   => $p->created_at,
                'catatan' => $p->alasan_penolakan,
                'url'     => route('pegawai.izin'),
            ])
            ->concat(Lembur::where('user_id', $user->id)->latest()->limit(20)->get()->map(fn ($l) => [
                'jenis'   => 'Lembur',
                'judul'   => $l->tanggal->translatedFormat('j F Y').' · '.substr($l->jam_mulai, 0, 5).'–'.substr($l->jam_selesai, 0, 5)
                    .($l->status === 'Disetujui' && $l->durasi_disetujui_menit !== null ? ' · diakui '.LemburService::formatMenit($l->durasi_disetujui_menit) : ''),
                'status'  => $l->status,
                'waktu'   => $l->created_at,
                'catatan' => $l->alasan_penolakan,
                'url'     => route('pegawai.lembur'),
            ]))
            ->sortByDesc('waktu')->values();

        return view('pegawai.riwayat', [
            'judul'     => 'Riwayat',
            'tab'       => $request->query('tab') === 'pengajuan' ? 'pengajuan' : 'presensi',
            'bulan'     => $bulan,
            'hari'      => $hari,
            'pengajuan' => $pengajuan,
            'ringkas'   => [
                'Hadir'      => $hari->whereIn('status', ['Tepat Waktu', 'Terlambat'])->count(),
                'Terlambat'  => $hari->where('status', 'Terlambat')->count(),
                'Izin/Sakit' => $hari->whereIn('status', ['Izin', 'Sakit'])->count(),
                'Alpa'       => $hari->where('status', 'Alpa')->count(),
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
}