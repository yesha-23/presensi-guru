<?php

namespace Database\Seeders;

use App\Models\JamKerja;
use App\Models\Lokasi;
use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Unit
        $sd   = Unit::updateOrCreate(['kode' => 'SD'],   ['nama' => 'Sekolah Dasar']);
        $kbtk = Unit::updateOrCreate(['kode' => 'KBTK'], ['nama' => 'KB & TK']);
        $yys  = Unit::updateOrCreate(['kode' => 'YYS'],  ['nama' => 'Yayasan']);

        // Peran
        foreach ([Role::ADMIN, Role::PEGAWAI, Role::KEPALA_SEKOLAH, Role::KETUA_YAYASAN] as $nama) {
            Role::firstOrCreate(['nama' => $nama]);
        }

        // Jam kerja (Bagian 2 requirement + baris Yayasan)
        $jadwal = [
            [$sd,   ['Guru', 'Staf', 'Karyawan', 'Tendik'], '15:00:00'],
            [$kbtk, ['Guru'],                               '14:00:00'],
            [$kbtk, ['Staf', 'Karyawan', 'Tendik'],         '15:00:00'],
            [$yys,  ['Staf', 'Karyawan'],                   '15:00:00'],
        ];

        foreach ($jadwal as [$unit, $daftarKategori, $jamPulang]) {
            foreach ($daftarKategori as $kategori) {
                JamKerja::updateOrCreate(
                    ['unit_id' => $unit->id, 'kategori' => $kategori],
                    ['jam_masuk' => '07:00:00', 'jam_pulang' => $jamPulang, 'toleransi_menit' => 15]
                );
            }
        }

        // Lokasi: GANTI koordinat dengan titik tengah kompleks (Google Maps > klik kanan > salin angka)
        Lokasi::updateOrCreate(
            ['nama' => 'Kompleks YPK Teruna Bangsa'],
            ['latitude' => -7.739157, 'longitude' => 110.409601, 'radius_meter' => 150]
        );

        // Pengaturan yang bisa diubah administrator
        $pengaturan = [
            'ambang_wajah'         => '0.5',   // jarak Euclidean maks; dikalibrasi saat uji lapangan 17 Okt
            'akurasi_gps_maks'     => '150',   // meter (SR-NF06)
            'jam_pengingat_datang' => '06:45',
            'pengingat_pulang'     => 'jam_pulang',
            'hari_kerja'           => '1,2,3,4,5', // Senin–Jumat
            'sesi_manual_menit'    => '30',
            'sesi_otomatis_menit'  => '5',
        ];

        foreach ($pengaturan as $kunci => $nilai) {
            Pengaturan::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
        }

        // Semester 2026/2027 (dari kaldik KB-TK; sesuaikan tanggal SD bila berbeda)
        foreach ([$sd, $kbtk] as $unit) {
            Semester::updateOrCreate(
                ['unit_id' => $unit->id, 'tahun_ajaran' => '2026/2027', 'jenis' => 'Ganjil'],
                ['tanggal_mulai' => '2026-07-13', 'tanggal_selesai' => '2026-12-18']
            );
            Semester::updateOrCreate(
                ['unit_id' => $unit->id, 'tahun_ajaran' => '2026/2027', 'jenis' => 'Genap'],
                ['tanggal_mulai' => '2027-01-06', 'tanggal_selesai' => '2027-06-25']
            );
        }
    }
}