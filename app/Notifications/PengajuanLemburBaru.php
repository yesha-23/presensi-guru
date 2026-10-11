<?php

namespace App\Notifications;

use App\Models\Lembur;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PengajuanLemburBaru extends Notification
{
    use Queueable;

    public function __construct(public Lembur $lembur)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $l = $this->lembur->loadMissing('user');

        return [
            'judul'      => "Pengajuan lembur baru: {$l->user->nama_lengkap}",
            'keterangan' => $l->tanggal->translatedFormat('l, j F Y').' · '
                .str_replace(':', '.', substr($l->jam_mulai, 0, 5)).'–'.str_replace(':', '.', substr($l->jam_selesai, 0, 5)),
            'url'        => route('persetujuan', ['tab' => 'lembur', 'id' => $l->id]),
            'jenis'      => 'pengajuan',
        ];
    }
}