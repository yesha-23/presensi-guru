<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use App\Notifications\Concerns\DenganEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class HasilPengajuan extends Notification implements ShouldQueue
{
    use Queueable, DenganEmail;

    public function __construct(public PengajuanIzin $pengajuan)
    {
    }

    public function toArray(object $notifiable): array
    {
        $p = $this->pengajuan->loadMissing('penyetuju');
        $hasil = $p->status === 'Disetujui' ? 'disetujui' : 'ditolak';

        return [
            'judul'      => "Pengajuan {$p->jenis} ".PengajuanBaru::rentang($p)." {$hasil}",
            'keterangan' => $p->status === 'Ditolak'
                ? "Alasan: {$p->alasan_penolakan}"
                : 'Oleh '.($p->penyetuju?->nama_lengkap ?? 'kepala sekolah'),
            'url'        => route('pegawai.izin'),
            'jenis'      => $p->status === 'Disetujui' ? 'berhasil' : 'ditolak',
        ];
    }
}