<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Menambahkan saluran email pada notifikasi lonceng.
 *
 * - Lonceng (database) dikirim langsung (sync), sehingga tetap muncul seketika.
 * - Email dikirim lewat antrean, sehingga halaman tidak menunggu dan
 *   kegagalan SMTP tidak menggagalkan proses utama (pengajuan tetap tersimpan).
 * - Isi email diambil dari toArray(): judul, keterangan, dan url.
 * - Alamat uji/palsu (.test, example.com, dll.) tidak dikirimi email.
 */
trait DenganEmail
{
    public function via(object $notifiable): array
    {
        return $this->bolehEmail($notifiable) ? ['database', 'mail'] : ['database'];
    }

    /** Lonceng langsung, email lewat antrean. */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'mail'     => config('queue.default'),
        ];
    }

    protected function bolehEmail(object $notifiable): bool
    {
        $email = strtolower((string) ($notifiable->email ?? ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        foreach (['.test', '.example', '.invalid', '.local', '@example.com'] as $akhiran) {
            if (str_ends_with($email, $akhiran)) {
                return false;
            }
        }

        return true;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($data['judul'])
            ->greeting('Halo, '.($notifiable->nama_panggilan ?? $notifiable->nama_lengkap).'!')
            ->line($data['judul'])
            ->line($data['keterangan'])
            ->action('Buka di Sistem Presensi', $data['url'])
            ->line('Pesan ini dikirim otomatis oleh sistem, mohon tidak dibalas.')
            ->salutation('Salam, Sistem Presensi YPK Teruna Bangsa');
    }
}