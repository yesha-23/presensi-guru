<?php

namespace App\Notifications;

use App\Models\Lembur;
use App\Notifications\Concerns\DenganEmail;
use App\Services\LemburService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class HasilPengajuanLembur extends Notification implements ShouldQueue
{
    use Queueable, DenganEmail;

    public function __construct(public Lembur $lembur)
    {
    }

    public function toArray(object $notifiable): array
    {
        $l = $this->lembur->loadMissing('penyetuju');
        $hasil = $l->status === 'Disetujui' ? 'disetujui' : 'ditolak';

        $keterangan = match (true) {
            $l->status === 'Ditolak'              => "Alasan: {$l->alasan_penolakan}",
            $l->durasi_disetujui_menit !== null   => 'Durasi diakui '.LemburService::formatMenit($l->durasi_disetujui_menit),
            default                               => 'Durasi dihitung setelah presensi pulang tercatat',
        };

        return [
            'judul'      => 'Pengajuan lembur '.$l->tanggal->translatedFormat('j F')." {$hasil}",
            'keterangan' => $keterangan,
            'url'        => route('pegawai.lembur'),
            'jenis'      => $l->status === 'Disetujui' ? 'berhasil' : 'ditolak',
        ];
    }
}