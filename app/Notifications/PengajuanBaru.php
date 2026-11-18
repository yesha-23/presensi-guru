<?php

namespace App\Notifications;

use App\Models\PengajuanIzin;
use App\Notifications\Concerns\DenganEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PengajuanBaru extends Notification implements ShouldQueue
{
    use Queueable, DenganEmail;

    public function __construct(public PengajuanIzin $pengajuan)
    {
    }

    public function toArray(object $notifiable): array
    {
        $p = $this->pengajuan->loadMissing('user');

        return [
            'judul'      => "Pengajuan {$p->jenis} baru: {$p->user->nama_lengkap}",
            'keterangan' => self::rentang($p),
            'url'        => route('persetujuan', ['id' => $p->id]),
            'jenis'      => 'pengajuan',
        ];
    }

    public static function rentang(PengajuanIzin $p): string
    {
        return $p->tanggal_mulai->equalTo($p->tanggal_selesai)
            ? $p->tanggal_mulai->translatedFormat('j F Y')
            : $p->tanggal_mulai->translatedFormat('j F').' – '.$p->tanggal_selesai->translatedFormat('j F Y');
    }
}