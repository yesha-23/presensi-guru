<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class AkunUjiSeeder extends Seeder
{
    public function run(): void
    {
        $unit = Unit::pluck('id', 'kode');

        $akun = [
            [
                'data' => ['nip' => 'ADM001', 'nama_lengkap' => 'Petrus Suhardi, S.Pd', 'nama_panggilan' => 'Pak Petrus',
                           'email' => 'admin@presensi.test', 'unit_id' => $unit['YYS'], 'kategori' => 'Staf',
                           'jabatan' => 'Kabag Adm. Umum'],
                'peran' => [Role::PEGAWAI, Role::ADMIN],
            ],
            [
                'data' => ['nip' => 'KS001', 'nama_lengkap' => 'Setiyaningdiyah S, S.Si., M.Pd.', 'nama_panggilan' => 'Bu Tuti',
                           'email' => 'kepsek.sd@presensi.test', 'unit_id' => $unit['SD'], 'kategori' => 'Guru',
                           'jabatan' => 'Kepala Sekolah SD'],
                'peran' => [Role::PEGAWAI, Role::KEPALA_SEKOLAH],
            ],
            [
                'data' => ['nip' => 'KS002', 'nama_lengkap' => 'Lely Yusinta, S.E', 'nama_panggilan' => 'Bu Lely',
                           'email' => 'kepsek.kbtk@presensi.test', 'unit_id' => $unit['KBTK'], 'kategori' => 'Guru',
                           'jabatan' => 'Kepala KB & TK'],
                'peran' => [Role::PEGAWAI, Role::KEPALA_SEKOLAH],
            ],
            [
                'data' => ['nip' => 'KY001', 'nama_lengkap' => 'Kristono Nugroho A, SE., M.M', 'nama_panggilan' => 'Pak Kristono',
                           'email' => 'ketua@presensi.test', 'unit_id' => $unit['YYS'], 'kategori' => null,
                           'jabatan' => 'Ketua Yayasan'],
                'peran' => [Role::KETUA_YAYASAN],
            ],
            [
                'data' => ['nip' => 'GR001', 'nama_lengkap' => 'Ria Irawari, S.Pd.', 'nama_panggilan' => 'Bu Ria',
                           'email' => 'guru.sd@presensi.test', 'unit_id' => $unit['SD'], 'kategori' => 'Guru',
                           'jabatan' => 'Guru Kelas'],
                'peran' => [Role::PEGAWAI],
            ],
            [
                'data' => ['nip' => 'TD001', 'nama_lengkap' => 'Venny Melanie Hale, S.T', 'nama_panggilan' => 'Enny',
                           'email' => 'tendik.kbtk@presensi.test', 'unit_id' => $unit['KBTK'], 'kategori' => 'Tendik',
                           'jabatan' => 'Tenaga Kependidikan'],
                'peran' => [Role::PEGAWAI],
            ],
        ];

        foreach ($akun as $item) {
            $user = User::updateOrCreate(
                ['nip' => $item['data']['nip']],
                $item['data'] + ['password' => 'password123', 'status' => 'Aktif']
            );

            $user->roles()->sync(Role::whereIn('nama', $item['peran'])->pluck('id'));
        }
    }
}