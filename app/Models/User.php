<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'unit_id', 'nip', 'nama_lengkap', 'nama_panggilan', 'email', 'password',
        'kategori', 'jabatan', 'ttd_path', 'persetujuan_wajah_at', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'persetujuan_wajah_at' => 'datetime',
        ];
    }

    // ---------- Relasi ----------
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function faceDescriptors(): HasMany
    {
        return $this->hasMany(FaceDescriptor::class);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class);
    }

    public function pengajuanIzin(): HasMany
    {
        return $this->hasMany(PengajuanIzin::class);
    }

    public function lembur(): HasMany
    {
        return $this->hasMany(Lembur::class);
    }

    public function suratTugas(): BelongsToMany
    {
        return $this->belongsToMany(SuratTugas::class, 'surat_tugas_pegawai')->withTimestamps();
    }

    // ---------- Helper ----------
    public function hasRole(string ...$peran): bool
    {
        return $this->roles->pluck('nama')->intersect($peran)->isNotEmpty();
    }

    /** Peran utama untuk menentukan halaman awal (revisi diagram 5.7). */
    public function peranUtama(): ?string
    {
        $urutan = [Role::KETUA_YAYASAN, Role::ADMIN, Role::KEPALA_SEKOLAH, Role::PEGAWAI];
        $milik = $this->roles->pluck('nama');

        foreach ($urutan as $peran) {
            if ($milik->contains($peran)) {
                return $peran;
            }
        }

        return null;
    }

    /** Nama route halaman awal sesuai peran utama (revisi diagram 5.7). */
    public function rutePeran(): string
    {
        return match ($this->peranUtama()) {
            Role::KETUA_YAYASAN  => 'yayasan.ringkasan',
            Role::ADMIN          => 'admin.dashboard',
            Role::KEPALA_SEKOLAH => 'persetujuan',
            default              => 'pegawai.beranda',
        };
    }

    public function wajibPresensi(): bool
    {
        return $this->kategori !== null && $this->status === 'Aktif';
    }

    public function punyaDataWajah(): bool
    {
        return $this->faceDescriptors()->count() >= 3;
    }

    public function jamKerjaKhusus(): HasOne
    {
        return $this->hasOne(JamKerjaKhusus::class);
    }

    /**
     * Jam kerja pegawai: jam unit & kategori, atau jam kerja khusus bila ada.
     * Toleransi selalu mengikuti unit & kategori.
     * Hasilnya hanya untuk dibaca (salinan, bukan baris tabel jam_kerja), jadi jangan di-save().
     */
    public function jamKerja(): ?JamKerja
    {
        $jam = JamKerja::where('unit_id', $this->unit_id)
            ->where('kategori', $this->kategori)
            ->first();

        if ($jam && $this->jamKerjaKhusus) {
            $jam = $jam->replicate();
            $jam->jam_masuk = $this->jamKerjaKhusus->jam_masuk;
            $jam->jam_pulang = $this->jamKerjaKhusus->jam_pulang;
        }

        return $jam;
    }
}