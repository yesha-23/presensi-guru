<?php

namespace App\Console\Commands;

use App\Models\Pengaturan;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Pemberitahuan;
use App\Notifications\PengingatPresensi;
use App\Services\JadwalService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class KirimPengingatPresensi extends Command
{
    protected $signature = 'presensi:pengingat {--paksa : Abaikan jam pengiriman (untuk pengujian)}';

    protected $description = 'Kirim pengingat presensi datang/pulang (SR-F32) dan ringkasan pegawai belum presensi untuk administrator';

    public function handle(JadwalService $jadwal): int
    {
        $sekarang = now();
        $paksa = (bool) $this->option('paksa');
        $jamPengingatDatang = (string) Pengaturan::ambil('jam_pengingat_datang', '06:45');

        $pegawai = User::where('status', 'Aktif')
            ->whereNotNull('kategori')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::PEGAWAI))
            ->get();

        $terkirim = ['datang' => 0, 'pulang' => 0];
        $belumHadir = 0;
        $batasTerakhir = null;

        foreach ($pegawai as $user) {
            // Libur, izin, sakit, tugas khusus: tidak diingatkan
            if ($jadwal->kondisiKhusus($user, $sekarang)) {
                continue;
            }

            $j = $jadwal->jadwal($user, $sekarang);
            if (! $j) {
                continue;
            }

            $presensi = Presensi::where('user_id', $user->id)->whereDate('tanggal', $sekarang)->first();
            $batasTepat = $j['masuk']->copy()->addMinutes($j['toleransi']);
            $batasTerakhir = $batasTerakhir ? $batasTerakhir->max($batasTepat) : $batasTepat->copy();

            // Pengingat datang: dari jam pengingat (06.45, atau 15 menit sebelum masuk pada Hari Kerja Tambahan) sampai batas toleransi
            $mulaiDatang = $j['hkt']
                ? $j['masuk']->copy()->subMinutes(15)
                : Carbon::parse($sekarang->toDateString().' '.$jamPengingatDatang);

            if (! $presensi?->jam_datang
                && ($paksa || $sekarang->between($mulaiDatang, $batasTepat))
                && $this->sekaliSehari($user, 'datang')) {
                $user->notify(new PengingatPresensi('datang', $j['masuk']->format('H.i')));
                $terkirim['datang']++;
            }

            // Pengingat pulang: sejak jam pulang, bagi yang sudah datang tetapi belum pulang
            if ($presensi?->jam_datang && ! $presensi->jam_pulang
                && ($paksa || $sekarang->gte($j['pulang']))
                && $this->sekaliSehari($user, 'pulang')) {
                $user->notify(new PengingatPresensi('pulang', $j['pulang']->format('H.i')));
                $terkirim['pulang']++;
            }

            if (! $presensi?->jam_datang && $sekarang->gt($batasTepat)) {
                $belumHadir++;
            }
        }

        // Ringkasan untuk administrator: sekali sehari setelah batas toleransi terakhir lewat
        if ($batasTerakhir && $belumHadir > 0 && $sekarang->gt($batasTerakhir)
            && Cache::add('ringkasan-belum-presensi:'.$sekarang->toDateString(), true, now()->endOfDay())) {
            $admin = User::where('status', 'Aktif')
                ->whereHas('roles', fn ($q) => $q->where('nama', Role::ADMIN))
                ->get();

            Notification::send($admin, new Pemberitahuan(
                "{$belumHadir} pegawai belum presensi hari ini",
                'Hari ini · '.$sekarang->format('H:i:s'),
                route('admin.dashboard'),
                'pengingat'
            ));
            $this->line("Ringkasan dikirim ke administrator: {$belumHadir} pegawai belum presensi.");
        }

        $this->info("Pengingat datang: {$terkirim['datang']}, pengingat pulang: {$terkirim['pulang']}.");

        return self::SUCCESS;
    }

    /** true jika pengingat jenis ini belum dikirim hari ini (dicatat di cache sampai akhir hari). */
    private function sekaliSehari(User $user, string $jenis): bool
    {
        return Cache::add("pengingat:{$jenis}:{$user->id}:".today()->toDateString(), true, now()->endOfDay());
    }
}