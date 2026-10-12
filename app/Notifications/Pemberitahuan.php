<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class Pemberitahuan extends Notification
{
    use Queueable;

    public function __construct(
        public string $judul,
        public string $keterangan,
        public string $url,
        public string $jenis = 'info',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
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