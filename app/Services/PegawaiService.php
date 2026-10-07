<?php

namespace App\Services;

use App\Models\JamKerja;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PegawaiService
{
    public const PERAN = [Role::PEGAWAI, Role::ADMIN, Role::KEPALA_SEKOLAH, Role::KETUA_YAYASAN];
    public const KATEGORI = ['Guru', 'Staf', 'Karyawan', 'Tendik'];
    public const STATUS = ['Aktif', 'Nonaktif', 'Keluar'];

    /** Aturan validasi data pegawai (SR-F04). */
    public static function aturan(array $data, ?User $user = null): array
    {
        $wajibPresensi = in_array(Role::PEGAWAI, (array) ($data['peran'] ?? []), true);

        return [
            'nip'            => ['required', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($user?->id)],
            'nama_lengkap'   => ['required', 'string', 'max:150'],
            'nama_panggilan' => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user?->id)],
            'unit_id'        => ['required', 'exists:units,id'],
            'kategori'       => [Rule::requiredIf($wajibPresensi), 'nullable', Rule::in(self::KATEGORI)],
            'jabatan'        => ['nullable', 'string', 'max:100'],
            'peran'          => ['required', 'array', 'min:1'],
            'peran.*'        => [Rule::in(self::PERAN)],
        ];
    }

    public static function pesan(): array
    {
        return [
            'required'          => ':attribute wajib diisi.',
            'unique'            => ':attribute sudah dipakai pegawai lain.',
            'email'             => 'Format :attribute tidak valid.',
            'max'               => ':attribute maksimal :max karakter.',
            'min'               => ':attribute minimal :min karakter.',
            'in'                => ':attribute tidak valid.',
            'exists'            => ':attribute tidak valid.',
            'unit_id.required'  => 'Unit wajib diisi (SD, KBTK, atau YYS).',
            'kategori.required' => 'Kategori wajib diisi untuk pegawai yang melakukan presensi.',
            'peran.required'    => 'Pilih minimal satu peran akses.',
            'peran.min'         => 'Pilih minimal satu peran akses.',
            'peran.*.in'        => 'Peran ":input" tidak dikenal.',
        ];
    }

    public static function atribut(): array
    {
        return [
            'nip'            => 'NIP',
            'nama_lengkap'   => 'Nama lengkap',
            'nama_panggilan' => 'Nama panggilan',
            'email'          => 'Email',
            'unit_id'        => 'Unit',
            'kategori'       => 'Kategori',
            'jabatan'        => 'Jabatan',
            'peran'          => 'Peran akses',
            'status'         => 'Status',
            'kata_sandi'     => 'Kata sandi awal',
        ];
    }

    /** Pegawai yang wajib presensi harus punya jadwal di tabel jam_kerja. */
    public static function cekJamKerja(array $data): ?string
    {
        if (! in_array(Role::PEGAWAI, (array) ($data['peran'] ?? []), true)) {
            return null;
        }

        $ada = JamKerja::where('unit_id', $data['unit_id'] ?? null)
            ->where('kategori', $data['kategori'] ?? null)
            ->exists();

        return $ada ? null : 'Jam kerja untuk unit dan kategori ini belum diatur di menu Pengaturan.';
    }

    /** Simpan data pegawai baru atau perubahan, sekaligus peran aksesnya. */
    public static function simpan(array $data, ?User $user = null): User
    {
        $peran = $data['peran'];
        unset($data['peran']);

        // Ketua Yayasan (tanpa peran Pegawai) tidak punya kategori & tidak presensi
        if (! in_array(Role::PEGAWAI, $peran, true)) {
            $data['kategori'] = null;
        }

        if ($user) {
            $user->update($data);
        } else {
            $user = User::create($data);
        }

        $user->roles()->sync(Role::whereIn('nama', $peran)->pluck('id'));

        return $user;
    }

    public static function buatKataSandi(): string
    {
        return Str::password(10, symbols: false);
    }
}