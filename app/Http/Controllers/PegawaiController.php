<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Models\Presensi;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    public function beranda(Request $request)
    {
        $user = $request->user()->load('unit');

        $hariIni = Presensi::where('user_id', $user->id)
            ->whereDate('tanggal', today())
            ->first();

        $bulanIni = Presensi::where('user_id', $user->id)
            ->whereYear('tanggal', now()->year)
            ->whereMonth('tanggal', now()->month)
            ->get();

        $rekap = [
            'Hadir'     => $bulanIni->where('status_harian', 'Hadir')->count(),
            'Terlambat' => $bulanIni->where('status_datang', 'Terlambat')->count(),
            'Izin'      => $bulanIni->where('status_harian', 'Izin')->count(),
            'Sakit'     => $bulanIni->where('status_harian', 'Sakit')->count(),
        ];

        $pengajuanTerakhir = PengajuanIzin::where('user_id', $user->id)->latest()->first();

        $salam = match (true) {
            now()->hour < 11 => 'Selamat pagi',
            now()->hour < 15 => 'Selamat siang',
            now()->hour < 18 => 'Selamat sore',
            default          => 'Selamat malam',
        };

        return view('pegawai.beranda', [
            'judul'             => 'Beranda',
            'user'              => $user,
            'hariIni'           => $hariIni,
            'rekap'             => $rekap,
            'pengajuanTerakhir' => $pengajuanTerakhir,
            'jamKerja'          => $user->jamKerja(),
            'salam'             => $salam,
        ]);
    }
}