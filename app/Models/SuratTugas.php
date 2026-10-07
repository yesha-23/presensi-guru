<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuratTugas extends Model
{
    protected $table = 'surat_tugas';

    protected $fillable = [
        'nomor_surat', 'perihal', 'pemberi_tugas', 'lokasi', 'jenis_kegiatan', 'sifat',
        'tanggal_mulai', 'tanggal_selesai', 'kasus_khusus', 'lampiran_path', 'pdf_path',
        'status', 'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'kasus_khusus' => 'boolean',
        ];
    }

    public function pegawai(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'surat_tugas_pegawai')->withTimestamps();
    }

    public function persetujuan(): HasMany
    {
        return $this->hasMany(PersetujuanSuratTugas::class)->orderBy('urutan');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}