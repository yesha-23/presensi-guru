<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PengingatPresensi extends Notification
{
    use Queueable;

    /**
     * @param string $jenis 'datang' atau 'pulang'
     * @param string $jam   jam masuk/pulang, misalnya "07.00"
     */
    public function __construct(public string $jenis, public string $jam)
    {
    }

    /** Email hanya jika pegawai punya alamat email; lonceng selalu. */
    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $datang = $this->jenis === 'datang';

        return (new MailMessage)
            ->subject($datang ? 'Pengingat presensi datang' : 'Pengingat presensi pulang')
            ->greeting('Halo, '.($notifiable->nama_panggilan ?? $notifiable->nama_lengkap).'!')
            ->line($datang
                ? "Anda belum melakukan presensi datang hari ini. Jam masuk pukul {$this->jam}."
                : "Jadwal pulang hari ini pukul {$this->jam}. Jangan lupa melakukan presensi pulang.")
            ->action('Buka halaman presensi', route('presensi.scan'))
            ->line('Abaikan pesan ini jika Anda sudah melakukan presensi.')
            ->salutation('Salam, Sistem Presensi YPK Teruna Bangsa');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul'      => $this->jenis === 'datang'
                ? "Jangan lupa presensi datang, jam masuk {$this->jam}"
                : "Jangan lupa presensi pulang pukul {$this->jam}",
            'keterangan' => 'Pengingat',
            'url'        => route('presensi.scan'),
            'jenis'      => 'pengingat',
        ];
    }
}