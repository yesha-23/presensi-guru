<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SuratTugas extends Model
{
    protected $table = 'surat_tugas';

    /** Status yang masih dapat diubah dan dikirim oleh administrator. */
    public const BISA_DIUBAH = ['Draf', 'Diproses', 'Perlu Revisi'];

    /** Status yang dapat dihapus administrator. */
    public const BISA_DIHAPUS = ['Draf', 'Perlu Revisi', 'Ditolak'];

    /** Urutan status untuk filter. */
    public const DAFTAR_STATUS = ['Diajukan', 'Diproses', 'Draf', 'Menunggu', 'Perlu Revisi', 'Disetujui', 'Ditolak'];

    protected $fillable = [
        'nomor_surat', 'perihal', 'pemberi_tugas', 'lokasi', 'jenis_kegiatan', 'sifat',
        'tanggal_mulai', 'tanggal_selesai', 'kasus_khusus', 'lampiran_path', 'pdf_path',
        'status', 'dibuat_oleh', 'diajukan_oleh', 'catatan_usulan', 'jam_mulai', 'jam_selesai', 'keterangan',
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

    /** Pegawai yang mengusulkan (null = dibuat langsung oleh administrator). */
    public function pengusul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function bisaDiubah(): bool
    {
        return in_array($this->status, self::BISA_DIUBAH, true);
    }

    public function bisaDihapus(): bool
    {
        return in_array($this->status, self::BISA_DIHAPUS, true);
    }

    /** Catatan revisi terakhir dari penanda tangan (jika status Perlu Revisi). */
    public function catatanRevisi(): ?PersetujuanSuratTugas
    {
        return $this->persetujuan->firstWhere('status', 'Perlu Revisi');
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

        if ($this->status === 'Ditolak' && $this->catatan_usulan) {
            return 'Usulan Ditolak';
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

    /** "08.00 – 09.30 WIB", "08.00 WIB – selesai", atau "-" */
    public function rentangJam(): string
    {
        $jam = fn (?string $t) => $t ? str_replace(':', '.', substr($t, 0, 5)) : null;

        return match (true) {
            $this->jam_mulai && $this->jam_selesai => $jam($this->jam_mulai).' – '.$jam($this->jam_selesai).' WIB',
            (bool) $this->jam_mulai                => $jam($this->jam_mulai).' WIB – selesai',
            default                                => '-',
        };
    }

    /** Keterangan dipecah per baris, untuk ditampilkan sebagai poin. */
    public function poinKeterangan(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->keterangan))
            ->map(fn ($baris) => ltrim(trim($baris), '-• '))
            ->filter()
            ->values()
            ->all();
    }
}