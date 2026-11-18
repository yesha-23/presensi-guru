<?php

namespace App\Notifications;

use App\Models\Lembur;
use App\Notifications\Concerns\DenganEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PengajuanLemburBaru extends Notification implements ShouldQueue
{
    use Queueable, DenganEmail;

    public function __construct(public Lembur $lembur)
    {
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