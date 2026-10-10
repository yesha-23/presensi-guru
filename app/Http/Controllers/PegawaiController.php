<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Services\JadwalService;
use Illuminate\Http\Request;

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
            'jadwalHariIni'     => $jadwalHariIni,
            'rekap'             => $rekap,
            'pengajuanTerakhir' => PengajuanIzin::where('user_id', $user->id)->latest()->first(),
            'salam'             => $salam,
        ]);
    }
}