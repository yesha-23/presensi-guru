<?php

namespace App\Console\Commands;

use App\Models\Pengaturan;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\User;
use App\Services\StatusHarianService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class TutupHariPresensi extends Command
{
    /** Kunci pengaturan: tanggal terakhir yang sudah ditutup (Y-m-d). */
    public const KUNCI_TERAKHIR = 'tutup_hari_terakhir';

    /** Batas mundur penutupan susulan (hari). */
    public const MAKS_SUSULAN = 7;

    protected $signature = 'presensi:tutup-hari
        {--tanggal= : Tutup satu tanggal tertentu (YYYY-MM-DD)}
        {--susulan : Hanya menutup hari-hari sebelumnya yang terlewat, tanpa hari ini}';

    protected $description = 'Tetapkan status harian semua pegawai di akhir hari: Hadir, Izin, Sakit, Tugas Khusus, Libur, atau Alpa (SR-F15), '
        .'termasuk hari yang terlewat karena penjadwal tidak berjalan (maksimal 7 hari)';

    public function handle(StatusHarianService $statusHarian): int
    {
        // 1) Tanggal tertentu (manual), seperti sebelumnya
        if ($this->option('tanggal')) {
            $tanggal = Carbon::parse($this->option('tanggal'))->startOfDay();
            if ($tanggal->isFuture()) {
                $this->error('Tanggal belum terjadi.');

                return self::FAILURE;
            }

            $this->tutup($statusHarian, $tanggal);

            return self::SUCCESS;
        }

        // 2) Otomatis: hari yang belum ditutup sejak penutupan terakhir (maks 7 hari ke belakang)
        $daftar = $this->tanggalBelumDitutup((bool) $this->option('susulan'));

        if ($daftar === []) {
            $this->line('Tidak ada hari yang perlu ditutup.');

            return self::SUCCESS;
        }

        foreach ($daftar as $tanggal) {
            // Hari yang sudah lewat: hanya isi pegawai yang belum berstatus (koreksi admin tidak ditimpa)
            $this->tutup($statusHarian, $tanggal, hanyaYangKosong: $tanggal->lt(today()));
        }

        return self::SUCCESS;
    }

    /** @return Carbon[] tanggal yang belum ditutup, urut dari yang terlama */
    private function tanggalBelumDitutup(bool $susulan): array
    {
        $akhir = $susulan ? today()->subDay() : today();
        $terakhir = Pengaturan::ambil(self::KUNCI_TERAKHIR);

        // Belum pernah ditutup otomatis: mulai dari hari ini saja (data lama tidak diubah)
        if (! $terakhir) {
            return $susulan ? [] : [today()];
        }

        $mulai = Carbon::parse($terakhir)->startOfDay()->addDay()
            ->max(today()->subDays(self::MAKS_SUSULAN));

        if ($mulai->gt($akhir)) {
            return [];
        }

        return collect(CarbonPeriod::create($mulai, $akhir))->map(fn ($t) => $t->copy())->all();
    }

    private function tutup(StatusHarianService $statusHarian, Carbon $tanggal, bool $hanyaYangKosong = false): void
    {
        $pegawai = User::where('status', 'Aktif')
            ->whereNotNull('kategori')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::PEGAWAI))
            ->get();

        $sudahBerstatus = $hanyaYangKosong
            ? array_map('intval', Presensi::whereDate('tanggal', $tanggal)->whereNotNull('status_harian')->pluck('user_id')->all())
            : [];

        $jumlah = [];
        foreach ($pegawai as $user) {
            if (in_array($user->id, $sudahBerstatus, true)) {
                $jumlah['(sudah berstatus, tidak diubah)'] = ($jumlah['(sudah berstatus, tidak diubah)'] ?? 0) + 1;

                continue;
            }

            $status = $statusHarian->tetapkan($user, $tanggal->copy());
            $jumlah[$status] = ($jumlah[$status] ?? 0) + 1;
        }

        // Datang tetapi tidak presensi pulang sampai akhir hari (jam_pulang tetap kosong)
        $tidakPulang = Presensi::whereDate('tanggal', $tanggal)
            ->whereNotNull('jam_datang')
            ->whereNull('jam_pulang')
            ->count();

        // Catat tanggal terakhir yang ditutup (tidak mundur bila menutup tanggal lama secara manual)
        $terakhir = Pengaturan::ambil(self::KUNCI_TERAKHIR);
        if (! $terakhir || $tanggal->gt(Carbon::parse($terakhir))) {
            Pengaturan::updateOrCreate(['kunci' => self::KUNCI_TERAKHIR], ['nilai' => $tanggal->toDateString()]);
        }

        $this->info(($hanyaYangKosong ? 'Tutup hari susulan ' : 'Tutup hari ').$tanggal->translatedFormat('l, j F Y').' untuk '.$pegawai->count().' pegawai:');
        $this->table(['Status', 'Jumlah'], collect($jumlah)->map(fn ($n, $s) => [$s, $n])->values()->all());
        $this->line("Tidak presensi pulang: {$tidakPulang}");
    }
}