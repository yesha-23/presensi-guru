<?php

namespace App\Notifications;

use App\Notifications\Concerns\DenganEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class Pemberitahuan extends Notification implements ShouldQueue
{
    use Queueable, DenganEmail;

    public function __construct(
        public string $judul,
        public string $keterangan,
        public string $url,
        public string $jenis = 'info',
    ) {
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul'      => $this->judul,
            'keterangan' => $this->keterangan,
            'url'        => $this->url,
            'jenis'      => $this->jenis,
        ];
    }
}