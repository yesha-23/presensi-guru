<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data uji usabilitas untuk sesi Kepala Sekolah SD (KS001) dan Guru SD (GR001).
 *
 * Aman dijalankan berulang: setiap kali dijalankan, data uji lama dihapus
 * lalu dibuat ulang dengan tanggal hari ini.
 *
 *   Siapkan / reset : php artisan db:seed --class=UsabilityTestSeeder
 *   Hapus total     : $env:UJI_HAPUS="1"; php artisan db:seed --class=UsabilityTestSeeder; Remove-Item Env:UJI_HAPUS
 *   Ubah jam kerja  : $env:UJI_MASUK="14:00"; $env:UJI_PULANG="17:00"  (bawaan 13:00 - 17:00)
 */
class UsabilityTestSeeder extends Seeder
{
    private const NIP_DUMMY = 'UJI01';
    private const NIP_GURU_BARU = 'GR099';           // dibuat responden admin pada tugas A1
    private const KET_JKK = 'UJI USABILITAS';
    private const TGL_KOREKSI = '2026-11-16';        // presensi tanpa jam pulang (K9 dan A7)

    private const PERIHAL = [
        'Rapat Koordinasi Kurikulum Sekolah Dasar',   // K4
        'Workshop Penilaian Pembelajaran',            // K5
        'Pendampingan Lomba FLS2N Tingkat Kabupaten', // P6
        'Pelatihan Implementasi Kurikulum Merdeka',   // P8 / A5
        'Rapat Kerja Dinas Pendidikan',               // A6
    ];

    private const ALASAN_IZIN_GR = 'Mengantar anak berobat ke dokter.';         // P3
    private const URAIAN_LEMBUR_GR = 'Menyiapkan soal Penilaian Akhir Semester.'; // P5

    public function run(): void
    {
        $gr  = DB::table('users')->where('nip', 'GR001')->first();
        $ks  = DB::table('users')->where('nip', 'KS001')->first();
        $adm = DB::table('users')->where('nip', 'ADM001')->first();

        if (! $gr || ! $ks || ! $adm) {
            $this->command->error('Akun GR001, KS001, atau ADM001 tidak ditemukan. Seeder dihentikan.');
            return;
        }

        $hariIni = Carbon::today();

        DB::transaction(fn () => $this->bersihkan($gr, $ks, $adm, $hariIni));

        if (getenv('UJI_HAPUS')) {
            $this->pulihkanJamKerjaGuru($gr);
            Cache::flush();
            $this->command->info('Data uji usabilitas sudah dihapus. Jam kerja GR001 dikembalikan.');
            return;
        }

        $buktiLama = DB::transaction(fn () => $this->buat($gr, $ks, $adm, $hariIni));

        Cache::flush();
        $this->laporan($gr, $ks, $adm, $hariIni, $buktiLama);
    }

    /* ------------------------------------------------------------------ */
    /*  HAPUS DATA UJI LAMA                                                */
    /* ------------------------------------------------------------------ */
    private function bersihkan(object $gr, object $ks, object $adm, Carbon $hariIni): void
    {
        // 1. Surat tugas uji (beserta penyetuju, pegawai, dan presensi Tugas Khusus hasil persetujuan)
        $suratIds = DB::table('surat_tugas')->whereIn('perihal', self::PERIHAL)->pluck('id');
        if ($suratIds->isNotEmpty()) {
            DB::table('presensi')->where('referensi_type', 'like', '%SuratTugas%')
                ->whereIn('referensi_id', $suratIds)->delete();
            DB::table('persetujuan_surat_tugas')->whereIn('surat_tugas_id', $suratIds)->delete();
            DB::table('surat_tugas_pegawai')->whereIn('surat_tugas_id', $suratIds)->delete();
            DB::table('surat_tugas')->whereIn('id', $suratIds)->delete();
        }

        // 2. Pengajuan izin dan lembur yang dibuat GR001 saat tugas P3, P4, P5
        $izinGr = DB::table('pengajuan_izin')->where('user_id', $gr->id)
            ->where('alasan', self::ALASAN_IZIN_GR)->pluck('id');
        if ($izinGr->isNotEmpty()) {
            DB::table('presensi')->where('referensi_type', 'like', '%PengajuanIzin%')
                ->whereIn('referensi_id', $izinGr)->delete();
            DB::table('pengajuan_izin')->whereIn('id', $izinGr)->delete();
        }
        DB::table('lembur')->where('user_id', $gr->id)
            ->where('uraian', self::URAIAN_LEMBUR_GR)->delete();

        // 3. Presensi GR001 hari ini (agar P1 dan P9 dapat diulang)
        DB::table('presensi')->where('user_id', $gr->id)
            ->whereDate('tanggal', $hariIni)->whereNull('referensi_type')->delete();

        // 4. Presensi admin tanggal koreksi beserta log koreksinya (K9)
        $presAdm = DB::table('presensi')->where('user_id', $adm->id)
            ->whereDate('tanggal', self::TGL_KOREKSI)->pluck('id');
        if ($presAdm->isNotEmpty()) {
            DB::table('log_koreksi')->where('target_type', 'like', '%Presensi%')
                ->whereIn('target_id', $presAdm)->delete();
            DB::table('presensi')->whereIn('id', $presAdm)->delete();
        }

        // 5. Pegawai uji (Andreas Wibowo) dan guru baru dari tugas A1 (Maria Kristina)
        $ujiIds = DB::table('users')->whereIn('nip', [self::NIP_DUMMY, self::NIP_GURU_BARU])->pluck('id');
        if ($ujiIds->isNotEmpty()) {
            $presUji = DB::table('presensi')->whereIn('user_id', $ujiIds)->pluck('id');
            DB::table('log_koreksi')->where('target_type', 'like', '%Presensi%')
                ->whereIn('target_id', $presUji)->delete();
            DB::table('log_koreksi')->whereIn('user_id', $ujiIds)->delete();
            DB::table('presensi')->whereIn('user_id', $ujiIds)->delete();
            DB::table('pengajuan_izin')->whereIn('user_id', $ujiIds)->delete();
            DB::table('lembur')->whereIn('user_id', $ujiIds)->delete();
            DB::table('surat_tugas_pegawai')->whereIn('user_id', $ujiIds)->delete();
            DB::table('jam_kerja_khusus')->whereIn('user_id', $ujiIds)->delete();
            DB::table('face_descriptors')->whereIn('user_id', $ujiIds)->delete();
            DB::table('role_user')->whereIn('user_id', $ujiIds)->delete();
            DB::table('notifications')->where('notifiable_type', 'like', '%User')
                ->whereIn('notifiable_id', $ujiIds)->delete();
            DB::table('users')->whereIn('id', $ujiIds)->delete();
        }

        // 6. Entri kalender dari tugas A3
        DB::table('kalender_events')->where('nama', 'Rapat Yayasan')
            ->whereDate('tanggal_mulai', '2026-12-04')->delete();

        // 7. Notifikasi hari ini untuk akun uji, agar lonceng notifikasi bersih di awal sesi
        DB::table('notifications')->where('notifiable_type', 'like', '%User')
            ->whereIn('notifiable_id', [$gr->id, $ks->id, $adm->id])
            ->where('created_at', '>=', $hariIni)->delete();

        // 8. Jam kerja khusus uji milik GR001
        DB::table('jam_kerja_khusus')->where('user_id', $gr->id)
            ->where('keterangan', self::KET_JKK)->delete();
    }

    /* ------------------------------------------------------------------ */
    /*  BUAT DATA UJI BARU                                                 */
    /* ------------------------------------------------------------------ */
    private function buat(object $gr, object $ks, object $adm, Carbon $hariIni): ?string
    {
        $now = now();

        // A. Pegawai uji: Andreas Wibowo, S.Pd. (unit dan peran disalin dari GR001)
        $andreasId = DB::table('users')->insertGetId([
            'unit_id'        => $gr->unit_id,
            'nip'            => self::NIP_DUMMY,
            'nama_lengkap'   => 'Andreas Wibowo, S.Pd.',
            'nama_panggilan' => 'Pak Andreas',
            'email'          => 'andreas.uji@presensi.test',
            'password'       => Hash::make('password123'),
            'kategori'       => 'Guru',
            'jabatan'        => 'Guru Kelas',
            'status'         => 'Aktif',
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        foreach (DB::table('role_user')->where('user_id', $gr->id)->pluck('role_id') as $roleId) {
            DB::table('role_user')->insert([
                'user_id' => $andreasId, 'role_id' => $roleId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // B. Presensi Andreas hari ini: 07.12 Terlambat (jawaban K1)
        $this->presensi($andreasId, $hariIni->toDateString(), '07:12:00', 'Terlambat', 12 * 60, null);

        // C. Presensi tanpa jam pulang: Andreas (A7) dan admin (bahan koreksi K9)
        $this->presensi($andreasId, self::TGL_KOREKSI, '06:55:00', 'Tepat Waktu', 0, null);
        $this->presensi($adm->id, self::TGL_KOREKSI, '06:58:00', 'Tepat Waktu', 0, null);

        // D. Izin Andreas menunggu persetujuan (K2). Bukti memakai file bukti yang sudah ada di storage;
        //    bila belum ada, izin diajukan lewat aplikasi (lihat Langkah Persiapan nomor 2).
        $buktiLama = DB::table('pengajuan_izin')->whereNotNull('bukti_path')
            ->orderByDesc('id')->value('bukti_path');
        if ($buktiLama) DB::table('pengajuan_izin')->insert([
            'user_id'         => $andreasId,
            'jenis'           => 'Izin',
            'tanggal_mulai'   => '2026-11-24',
            'tanggal_selesai' => '2026-11-24',
            'alasan'          => 'Menghadiri pernikahan saudara kandung di Klaten.',
            'bukti_path'      => $buktiLama,
            'status'          => 'Menunggu',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        // E. Lembur Andreas untuk kegiatan pribadi, menunggu persetujuan (K3)
        DB::table('lembur')->insert([
            'user_id'     => $andreasId,
            'tanggal'     => $hariIni->copy()->subDay()->toDateString(),
            'jam_mulai'   => '15:30:00',
            'jam_selesai' => '18:00:00',
            'uraian'      => 'Mengikuti kursus bahasa Inggris pribadi di luar sekolah.',
            'status'      => 'Menunggu',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        // F. Surat tugas menunggu tanda tangan KS001
        $this->suratTugas($adm->id, $ks->id, [$andreasId], [   // K4
            'perihal' => 'Rapat Koordinasi Kurikulum Sekolah Dasar', 'pemberi_tugas' => 'Yayasan',
            'lokasi' => 'Aula YPK Teruna Bangsa', 'jenis_kegiatan' => 'Dalam',
            'tanggal' => '2026-11-26', 'jam_mulai' => '09:00:00', 'jam_selesai' => '12:00:00',
            'keterangan' => 'Rapat koordinasi penyusunan program semester genap.',
        ]);
        $this->suratTugas($adm->id, $ks->id, [$andreasId], [   // K5 (tanggal sengaja salah)
            'perihal' => 'Workshop Penilaian Pembelajaran', 'pemberi_tugas' => 'Dinas',
            'lokasi' => 'Dinas Pendidikan Kabupaten Sleman', 'jenis_kegiatan' => 'Luar',
            'tanggal' => '2026-12-27', 'jam_mulai' => '08:00:00', 'jam_selesai' => '14:00:00',
            'keterangan' => 'Workshop penyusunan instrumen penilaian.',
        ]);
        $this->suratTugas($adm->id, $ks->id, [$gr->id], [      // P6 (disetujui peneliti saat persiapan)
            'perihal' => 'Pendampingan Lomba FLS2N Tingkat Kabupaten', 'pemberi_tugas' => 'Dinas',
            'lokasi' => 'Gedung Dinas Pendidikan Kabupaten Sleman', 'jenis_kegiatan' => 'Luar',
            'tanggal' => '2026-11-12', 'jam_mulai' => '07:30:00', 'jam_selesai' => '14:00:00',
            'keterangan' => 'Mendampingi siswa lomba FLS2N.',
        ]);

        // G. Jam kerja khusus GR001 selama pengujian (sore hari)
        $masuk  = (getenv('UJI_MASUK') ?: '13:00') . ':00';
        $pulang = (getenv('UJI_PULANG') ?: '17:00') . ':00';
        $lama = DB::table('jam_kerja_khusus')->where('user_id', $gr->id)->first();
        $fileBackup = storage_path('app/uji_jkk_backup.json');
        if ($lama && ! file_exists($fileBackup)) {
            file_put_contents($fileBackup, json_encode($lama));   // disimpan agar bisa dipulihkan
        }
        DB::table('jam_kerja_khusus')->updateOrInsert(
            ['user_id' => $gr->id],
            ['jam_masuk' => $masuk, 'jam_pulang' => $pulang, 'keterangan' => self::KET_JKK,
             'created_at' => $now, 'updated_at' => $now]
        );

        return $buktiLama;
    }

    private function presensi(int $userId, string $tgl, string $datang, string $status, int $detik, ?string $pulang): void
    {
        DB::table('presensi')->insert([
            'user_id'         => $userId,
            'tanggal'         => $tgl,
            'status_harian'   => 'Hadir',
            'jam_datang'      => $datang,
            'status_datang'   => $status,
            'detik_terlambat' => $detik,
            'jam_pulang'      => $pulang,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    private function suratTugas(int $admId, int $ksId, array $pegawai, array $d): void
    {
        $now = now();
        $id = DB::table('surat_tugas')->insertGetId([
            'perihal'        => $d['perihal'],
            'pemberi_tugas'  => $d['pemberi_tugas'],
            'lokasi'         => $d['lokasi'],
            'keterangan'     => $d['keterangan'],
            'jenis_kegiatan' => $d['jenis_kegiatan'],
            'sifat'          => 'Terencana',
            'tanggal_mulai'  => $d['tanggal'],
            'tanggal_selesai'=> $d['tanggal'],
            'jam_mulai'      => $d['jam_mulai'],
            'jam_selesai'    => $d['jam_selesai'],
            'kasus_khusus'   => 0,
            'status'         => 'Menunggu',
            'dibuat_oleh'    => $admId,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        foreach ($pegawai as $uid) {
            DB::table('surat_tugas_pegawai')->insert([
                'surat_tugas_id' => $id, 'user_id' => $uid, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        DB::table('persetujuan_surat_tugas')->insert([
            'surat_tugas_id' => $id, 'penyetuju_id' => $ksId, 'urutan' => 1,
            'status' => 'Menunggu', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function pulihkanJamKerjaGuru(object $gr): void
    {
        $fileBackup = storage_path('app/uji_jkk_backup.json');
        if (file_exists($fileBackup)) {
            $lama = (array) json_decode(file_get_contents($fileBackup), true);
            unset($lama['id']);
            DB::table('jam_kerja_khusus')->updateOrInsert(['user_id' => $gr->id], $lama);
            unlink($fileBackup);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  LAPORAN DAN KUNCI JAWABAN                                          */
    /* ------------------------------------------------------------------ */
    private function laporan(object $gr, object $ks, object $adm, Carbon $hariIni, ?string $buktiLama): void
    {
        $c = $this->command;
        $c->newLine();
        $c->info('=== DATA UJI USABILITAS SIAP (' . $hariIni->translatedFormat('l, d F Y') . ') ===');

        $jkk = DB::table('jam_kerja_khusus')->where('user_id', $gr->id)->first();
        $c->line("Jam kerja khusus GR001 : {$jkk->jam_masuk} s.d. {$jkk->jam_pulang}");
        $c->line('Akun pegawai uji       : andreas.uji@presensi.test / password123 (Andreas Wibowo)');

        $c->newLine();
        $c->info('--- KUNCI JAWABAN ---');
        $c->line('K1 : 07.12, Terlambat');

        $telat = DB::table('presensi')->where('user_id', $gr->id)
            ->whereBetween('tanggal', ['2026-10-01', '2026-10-31'])
            ->whereIn('status_datang', ['Terlambat', 'Lewat Batas'])
            ->whereNull('dibatalkan_at')->orderBy('tanggal')->get(['tanggal', 'jam_datang', 'status_datang']);
        $c->line('P7 : Ria Irawari terlambat ' . $telat->count() . ' kali pada Oktober 2026'
            . ($telat->isNotEmpty() ? ' (' . $telat->map(fn ($p) => Carbon::parse($p->tanggal)->format('d') . ' ' . substr($p->jam_datang, 0, 5))->implode(', ') . ')' : ''));
        $c->line('K6 : baca TLAT Ria Irawari di Rekap Bulanan SD Oktober 2026, catat di lembar observasi');
        $c->line('K8 : baca persentase kehadiran Ria Irawari di Laporan PKG Semester 1 Tahun Ajaran 2026/2027');

        $c->newLine();
        $c->info('--- PERIKSA ---');
        $wajah = DB::table('face_descriptors')->where('user_id', $gr->id)->count();
        $wajah > 0 ? $c->line("OK  wajah GR001 terdaftar ({$wajah} foto)")
                   : $c->warn('!!  wajah GR001 belum terdaftar -> daftarkan dulu (P1, P9)');
        $ks->ttd_path ? $c->line('OK  tanda tangan KS001 sudah diunggah')
                      : $c->warn('!!  tanda tangan KS001 belum diunggah -> unggah dulu (K4)');
        $buktiLama ? $c->line("OK  bukti izin Andreas memakai file: {$buktiLama}")
                   : $c->warn('!!  belum ada file bukti di sistem -> lihat Langkah Persiapan nomor 2');
        $okt = DB::table('presensi')->where('user_id', $gr->id)
            ->whereBetween('tanggal', ['2026-10-01', '2026-10-31'])->count();
        $okt > 0 ? $c->line("OK  presensi GR001 Oktober 2026 ada ({$okt} hari)")
                 : $c->warn('!!  presensi GR001 Oktober 2026 kosong -> P7, K6, K8 tidak bisa diuji');
        foreach (DB::table('pengaturan')->where('kunci', 'like', '%batas%')->orWhere('kunci', 'like', '%lewat%')->get() as $p) {
            $c->line("    pengaturan {$p->kunci} = {$p->nilai}");
        }

        $c->newLine();
        $c->info('--- LANGKAH PERSIAPAN DI APLIKASI (setiap selesai seeder) ---');
        $c->line('1. Login KS001 -> setujui surat tugas "Pendampingan Lomba FLS2N Tingkat Kabupaten" (bahan P6). Logout.');
        $c->line('2. ' . ($buktiLama ? 'Lewati.' : 'Login andreas.uji@presensi.test -> ajukan Izin Selasa 24 Nov 2026, alasan "Menghadiri pernikahan saudara kandung di Klaten.", bukti bukti_izin.jpg (bahan K2). Logout.'));
        $c->line('3. Login ADM001 -> koreksi presensi milik sendiri tanggal 16 Nov 2026, jam pulang 15.30, alasan "Lupa presensi pulang." (bahan K9). Logout.');
    }
}