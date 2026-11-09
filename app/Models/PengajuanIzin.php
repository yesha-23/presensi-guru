<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanIzin extends Model
{
    protected $table = 'pengajuan_izin';

    protected $fillable = [
        'user_id', 'jenis', 'tanggal_mulai', 'tanggal_selesai', 'alasan', 'bukti_path',
        'status', 'alasan_penolakan', 'disetujui_oleh', 'diproses_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'diproses_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /**
     * Penanda waktu pengajuan untuk penyetuju:
     * " · Mendadak" = izin untuk hari yang sama dengan tanggal diajukan,
     * " · Susulan"  = sakit untuk tanggal sebelum tanggal diajukan.
     */
    public function labelWaktu(): string
    {
        if (! $this->created_at || ! $this->tanggal_mulai) {
            return '';
        }

        $hariAju = $this->created_at->copy()->startOfDay();

        return match (true) {
            $this->jenis === 'Izin' && $this->tanggal_mulai->lte($hariAju) => ' · Mendadak',
            $this->jenis === 'Sakit' && $this->tanggal_mulai->lt($hariAju) => ' · Susulan',
            default                                                        => '',
        };
    }
}