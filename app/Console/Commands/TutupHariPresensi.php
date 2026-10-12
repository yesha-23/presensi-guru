<?php

namespace App\Console\Commands;

use App\Models\Presensi;
use App\Models\Role;
use App\Models\User;
use App\Services\StatusHarianService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TutupHariPresensi extends Command
{
    protected $signature = 'presensi:tutup-hari {--tanggal= : Tanggal YYYY-MM-DD (bawaan: hari ini)}';

    protected $description = 'Tetapkan status harian semua pegawai di akhir hari: Hadir, Izin, Sakit, Tugas Khusus, Libur, atau Alpa (SR-F15)';

    public function handle(StatusHarianService $statusHarian): int
    {
        $tanggal = $this->option('tanggal')
            ? Carbon::parse($this->option('tanggal'))->startOfDay()
            : today();

        if ($tanggal->isFuture()) {
            $this->error('Tanggal belum terjadi.');

            return self::FAILURE;
        }

        $pegawai = User::where('status', 'Aktif')
            ->whereNotNull('kategori')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::PEGAWAI))
            ->get();

        $jumlah = [];
        foreach ($pegawai as $user) {
            $status = $statusHarian->tetapkan($user, $tanggal->copy());
            $jumlah[$status] = ($jumlah[$status] ?? 0) + 1;
        }

        // Datang tetapi tidak presensi pulang sampai akhir hari (jam_pulang tetap kosong)
        $tidakPulang = Presensi::whereDate('tanggal', $tanggal)
            ->whereNotNull('jam_datang')
            ->whereNull('jam_pulang')
            ->count();

        $this->info('Tutup hari '.$tanggal->translatedFormat('l, j F Y').' untuk '.$pegawai->count().' pegawai:');
        $this->table(['Status', 'Jumlah'], collect($jumlah)->map(fn ($n, $s) => [$s, $n])->values()->all());
        $this->line("Tidak presensi pulang: {$tidakPulang}");

        return self::SUCCESS;
    }
}