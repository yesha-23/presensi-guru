<?php

namespace App\Console\Commands;

use App\Models\KalenderEvent;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\JadwalService;
use App\Services\StatusHarianService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Uji lapangan (Bab IV 4.6): memasukkan ketukan mesin fingerprint ke database pembanding
 * agar rekap sistem dapat dibandingkan dengan rekap resmi sekolah.
 *
 * CSV (tidak di-commit, folder database/seeders/data/ di-gitignore):
 *   nama_mesin,tanggal,jam,jenis
 *   RIA IRAWATI,2026-09-26,07:53:49,Masuk
 *
 * Aturan: ketukan Masuk paling awal = jam datang, ketukan Keluar paling akhir = jam pulang.
 * Status datang dihitung dengan aturan sistem (Presensi::hitungDatang), bukan aturan mesin.
 * Hari yang bukan hari kerja di sistem (Sabtu piket, Minggu) tidak diimpor dan dilaporkan.
 */
class ImporFingerprint extends Command
{
    protected $signature = 'presensi:impor-fingerprint
        {file : Path CSV ketukan mesin (nama_mesin,tanggal,jam,jenis)}
        {--mulai=2026-09-26 : Awal periode}
        {--selesai=2026-10-25 : Akhir periode}
        {--unit=SD : Kode unit pegawai}
        {--hkt=2026-09-26,2026-10-17 : Tanggal Sabtu rapat (Hari Kerja Tambahan), pisahkan dengan koma}
        {--hkt-masuk=08:00 : Jam masuk Hari Kerja Tambahan}
        {--hkt-pulang=12:00 : Jam pulang Hari Kerja Tambahan}
        {--peta=* : Pemetaan manual nama mesin ke NIP, contoh --peta="CAROLINA VS=19800101"}
        {--paksa : Izinkan berjalan di database selain presensi_banding}';

    protected $description = 'Impor ketukan mesin fingerprint ke database pembanding untuk uji lapangan';

    public function handle(JadwalService $jadwal, StatusHarianService $statusHarian): int
    {
        // 0) Pengaman: jangan sampai menimpa database uji usabilitas (presensi_uji)
        $db = DB::connection()->getDatabaseName();
        if ($db !== 'presensi_banding' && ! $this->option('paksa')) {
            $this->error("Database aktif: {$db}. Perintah ini hanya untuk database presensi_banding.");
            $this->line('Ubah DB_DATABASE di .env lalu jalankan: php artisan config:clear');

            return self::FAILURE;
        }

        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $mulai = Carbon::parse($this->option('mulai'))->startOfDay();
        $selesai = Carbon::parse($this->option('selesai'))->startOfDay();
        $unit = Unit::where('kode', $this->option('unit'))->first();
        if (! $unit) {
            $this->error('Unit '.$this->option('unit').' tidak ada.');

            return self::FAILURE;
        }

        // 1) Baca CSV dan kelompokkan per nama mesin per tanggal
        $ketukan = [];
        $baris = array_map('str_getcsv', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        array_shift($baris); // header
        foreach ($baris as $i => $b) {
            if (count($b) < 4) {
                $this->warn('Baris '.($i + 2).' dilewati: kolom kurang.');

                continue;
            }
            [$nama, $tgl, $jam, $jenis] = array_map('trim', $b);
            $ketukan[$nama][$tgl][] = ['jam' => $jam, 'jenis' => $jenis];
        }

        // 2) Cocokkan nama mesin dengan akun pegawai
        $peta = [];
        foreach ($this->option('peta') as $p) {
            [$n, $nip] = array_map('trim', explode('=', $p, 2) + [1 => '']);
            $peta[$n] = $nip;
        }

        $pegawai = [];
        foreach (array_keys($ketukan) as $nama) {
            $user = $this->cariPegawai($nama, $unit->id, $peta[$nama] ?? null);
            if (! $user) {
                return self::FAILURE;
            }
            $pegawai[$nama] = $user;
            $this->line("Cocok: {$nama}  ->  {$user->nama_lengkap} (NIP {$user->nip})");
        }

        // 3) Peringatan data lain di periode yang dapat memengaruhi rekap
        $this->periksaDataLain($unit->id, $mulai, $selesai, collect($pegawai)->pluck('id')->all());

        if (! $this->confirm('Lanjutkan impor? Presensi pegawai di atas pada periode ini akan dihapus lalu diganti data mesin.', true)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($ketukan, $pegawai, $unit, $mulai, $selesai, $jadwal, $statusHarian) {
            // 4) Hari Kerja Tambahan (Sabtu rapat)
            $admin = User::whereHas('roles', fn ($q) => $q->where('nama', Role::ADMIN))->first();
            foreach (array_filter(array_map('trim', explode(',', (string) $this->option('hkt')))) as $tgl) {
                KalenderEvent::updateOrCreate(
                    ['unit_id' => $unit->id, 'kategori' => KalenderEvent::HARI_KERJA_TAMBAHAN, 'tanggal_mulai' => $tgl],
                    [
                        'nama'            => 'Rapat Sabtu',
                        'berlaku_untuk'   => 'Guru dan Staf',
                        'tanggal_selesai' => $tgl,
                        'jam_masuk'       => $this->option('hkt-masuk'),
                        'jam_pulang'      => $this->option('hkt-pulang'),
                        'created_by'      => $admin?->id,
                    ]
                );
                $this->line("Hari Kerja Tambahan {$tgl} ({$this->option('hkt-masuk')}–{$this->option('hkt-pulang')}) disiapkan.");
            }

            $ringkas = [];
            foreach ($pegawai as $nama => $user) {
                // 5) Bersihkan presensi lama pegawai ini pada periode
                Presensi::where('user_id', $user->id)
                    ->whereDate('tanggal', '>=', $mulai)
                    ->whereDate('tanggal', '<=', $selesai)
                    ->delete();

                $diimpor = 0;
                $dilewati = [];
                foreach ($ketukan[$nama] as $tgl => $daftar) {
                    $tanggal = Carbon::parse($tgl)->startOfDay();
                    if ($tanggal->lt($mulai) || $tanggal->gt($selesai)) {
                        continue;
                    }

                    $j = $jadwal->jadwal($user, $tanggal->copy());
                    if (! $j) {
                        $dilewati[] = $tanggal->translatedFormat('D d/m').' (bukan hari kerja di sistem)';

                        continue;
                    }

                    $masuk = collect($daftar)->where('jenis', 'Masuk')->pluck('jam')->sort()->first();
                    $keluar = collect($daftar)->where('jenis', 'Keluar')->pluck('jam')->sort()->last();
                    if (! $masuk) {
                        $dilewati[] = $tanggal->translatedFormat('D d/m').' (hanya ketukan Keluar)';

                        continue;
                    }

                    $datang = Carbon::parse("{$tgl} {$masuk}");
                    $data = ['status_harian' => 'Hadir', 'jam_datang' => $masuk] + Presensi::hitungDatang($datang, $j);

                    if ($keluar && $keluar > $masuk) {
                        $pulang = Carbon::parse("{$tgl} {$keluar}");
                        $cepat = $pulang->lt($j['pulang']);
                        $data += [
                            'jam_pulang'         => $keluar,
                            'pulang_cepat'       => $cepat,
                            'detik_pulang_cepat' => $cepat ? $j['pulang']->timestamp - $pulang->timestamp : 0,
                        ];
                    }

                    Presensi::create(['user_id' => $user->id, 'tanggal' => $tgl] + $data);
                    $diimpor++;
                }

                // 6) Tetapkan status hari tanpa ketukan (Alpa/Izin/Sakit/Tugas Khusus/Libur) seperti tutup hari
                foreach (CarbonPeriod::create($mulai, $selesai) as $tanggal) {
                    $statusHarian->tetapkan($user, $tanggal->copy());
                }

                $ringkas[] = [$nama, $diimpor, implode('; ', $dilewati) ?: '-'];
            }

            $this->table(['Nama mesin', 'Hari diimpor', 'Ketukan tidak diimpor'], $ringkas);
        });

        $this->info('Selesai. Buka Rekap Presensi unit '.$unit->kode.' periode yang sesuai untuk membandingkan.');

        return self::SUCCESS;
    }

    private function cariPegawai(string $nama, int $unitId, ?string $nip): ?User
    {
        $query = User::where('unit_id', $unitId)->whereNotNull('kategori');

        if ($nip) {
            $hasil = (clone $query)->where('nip', $nip)->get();
        } else {
            // Cocokkan semua kata nama mesin yang panjangnya > 2 huruf (inisial seperti "VS" diabaikan)
            $kata = array_filter(explode(' ', $nama), fn ($k) => mb_strlen($k) > 2);
            $hasil = (clone $query)->where(function ($q) use ($kata) {
                foreach ($kata as $k) {
                    $q->where('nama_lengkap', 'like', "%{$k}%");
                }
            })->get();
        }

        if ($hasil->count() === 1) {
            return $hasil->first();
        }

        $this->error("Nama mesin \"{$nama}\" cocok dengan {$hasil->count()} akun. Gunakan --peta=\"{$nama}=NIP\".");
        foreach ($hasil as $u) {
            $this->line("  - {$u->nama_lengkap} (NIP {$u->nip})");
        }

        return null;
    }

    private function periksaDataLain(int $unitId, Carbon $mulai, Carbon $selesai, array $userIds): void
    {
        $kalender = KalenderEvent::where(fn ($q) => $q->whereNull('unit_id')->orWhere('unit_id', $unitId))
            ->whereDate('tanggal_mulai', '<=', $selesai)
            ->whereDate('tanggal_selesai', '>=', $mulai)
            ->get();

        foreach ($kalender as $k) {
            $this->warn("Kalender: {$k->tanggal_mulai->format('d/m/Y')} – {$k->tanggal_selesai->format('d/m/Y')}  {$k->kategori}  \"{$k->nama}\" (id {$k->id})");
        }

        $izin = DB::table('pengajuan_izin')->whereIn('user_id', $userIds)->where('status', 'Disetujui')
            ->whereDate('tanggal_mulai', '<=', $selesai)->whereDate('tanggal_selesai', '>=', $mulai)->count();
        $khusus = DB::table('jam_kerja_khusus')->whereIn('user_id', $userIds)->count();
        $tugas = DB::table('surat_tugas')
            ->join('surat_tugas_pegawai', 'surat_tugas_pegawai.surat_tugas_id', '=', 'surat_tugas.id')
            ->whereIn('surat_tugas_pegawai.user_id', $userIds)->where('surat_tugas.status', 'Disetujui')
            ->whereDate('tanggal_mulai', '<=', $selesai)->whereDate('tanggal_selesai', '>=', $mulai)->count();

        if ($tugas) {
            $this->warn("Ada {$tugas} surat tugas disetujui untuk pegawai ini di periode ini (data uji). Hapus dulu bila bukan data asli.");
        }
        if ($izin) {
            $this->warn("Ada {$izin} pengajuan izin/sakit disetujui di periode ini (data uji). Hapus dulu bila bukan data asli.");
        }
        if ($khusus) {
            $this->warn("Ada {$khusus} jam kerja khusus untuk pegawai ini. Hapus dulu bila bukan data asli.");
        }
        if ($kalender->isEmpty() && ! $izin && ! $khusus && ! $tugas) {
            $this->line('Tidak ada entri kalender, izin, atau jam kerja khusus lain di periode ini.');
        }
    }
}