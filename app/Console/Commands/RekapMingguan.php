<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Notifications\Pemberitahuan;
use App\Services\RekapService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class RekapMingguan extends Command
{
    protected $signature = 'presensi:rekap-mingguan';

    protected $description = 'Beri tahu administrator & kepala sekolah bahwa rekap minggu yang baru selesai siap diunduh (SR-F27)';

    public function handle(RekapService $rekap): int
    {
        // Dijalankan Minggu pagi: minggu Senin–Sabtu yang baru selesai
        $senin = today()->subDay()->startOfWeek(Carbon::MONDAY);
        $sabtu = $senin->copy()->addDays(5);

        $hasil = $rekap->hitung($rekap->pegawai($senin, $sabtu), $senin, $sabtu);
        $rata = $hasil['baris']->where('efektif', '>', 0)->avg('persen');

        $penerima = User::where('status', 'Aktif')
            ->whereHas('roles', fn ($q) => $q->whereIn('nama', [Role::ADMIN, Role::KEPALA_SEKOLAH]))
            ->get();

        Notification::send($penerima, new Pemberitahuan(
            'Rekap mingguan '.$senin->translatedFormat('j M').' – '.$senin->copy()->addDays(4)->translatedFormat('j M').' siap diunduh',
            $rata === null ? 'Belum ada hari efektif' : 'Rata-rata kehadiran '.number_format($rata, 1, ',', '.').'%',
            route('rekap', ['periode' => 'mingguan', 'tanggal' => $senin->toDateString()]),
            'info'
        ));

        $this->info("Notifikasi rekap mingguan dikirim ke {$penerima->count()} orang.");

        return self::SUCCESS;
    }
}