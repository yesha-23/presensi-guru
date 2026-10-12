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

    /** Status untuk ditampilkan: Menunggu dirinci per tahap; Disetujui yang sudah lewat = Selesai. */
    public function labelStatus(): string
    {
        if ($this->status === 'Menunggu') {
            $aktif = $this->persetujuan->where('status', 'Menunggu')->sortBy('urutan')->first();

            return $aktif?->urutan === 2 ? 'Menunggu Ketua Yayasan' : 'Menunggu Kepala Sekolah';
        }

        if ($this->status === 'Disetujui' && $this->tanggal_selesai->lt(today())) {
            return 'Selesai';
        }

        return $this->status;
    }

    public function rentangTanggal(): string
    {
        $mulai = $this->tanggal_mulai;
        $selesai = $this->tanggal_selesai;

        if ($mulai->equalTo($selesai)) {
            return $mulai->translatedFormat('j F Y');
        }

        return $mulai->isSameMonth($selesai)
            ? $mulai->translatedFormat('j').'–'.$selesai->translatedFormat('j F Y')
            : $mulai->translatedFormat('j M').' – '.$selesai->translatedFormat('j M Y');
    }
}