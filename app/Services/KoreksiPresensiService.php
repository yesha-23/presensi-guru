<?php

namespace App\Services;

use App\Models\LogKoreksi;
use App\Models\Presensi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KoreksiPresensiService
{
    public const STATUS = ['Hadir', 'Izin', 'Sakit', 'Tugas Khusus', 'Alpa', 'Libur'];

    private const LABEL = ['status_harian' => 'Status', 'jam_datang' => 'Datang', 'jam_pulang' => 'Pulang'];

    public function __construct(
        private JadwalService $jadwal,
        private LemburService $lembur,
        private PersetujuanService $persetujuan,
    ) {
    }

    /** Nilai yang bisa dikoreksi (dicatat di log sebagai nilai lama/baru). */
    public static function nilai(?Presensi $p): array
    {
        return [
            'status_harian' => $p?->status_harian,
            'jam_datang'    => $p?->jam_datang,
            'jam_pulang'    => $p?->jam_pulang,
        ];
    }

    /** "Status: Alpa → Hadir; Datang: — → 06:50:00" (juga untuk log "Bukan saya"). */
    public static function ringkasan(?array $lama, ?array $baru): string
    {
        $bagian = [];

        foreach (self::LABEL as $kunci => $label) {
            if (! array_key_exists($kunci, (array) $baru)) {
                continue;
            }
            $a = $lama[$kunci] ?? null;
            $b = $baru[$kunci] ?? null;
            if ($a !== $b) {
                $bagian[] = "{$label}: ".($a ?? '—').' → '.($b ?? '—');
            }
        }

        return $bagian ? implode('; ', $bagian) : 'Tidak ada perubahan nilai';
    }

    /**
     * Simpan koreksi dari administrator. Koreksi presensi milik administrator sendiri
     * tidak langsung diterapkan, tetapi menunggu persetujuan kepala sekolah (SR-F34).
     */
    public function ajukan(User $pengubah, User $pegawai, Carbon $tanggal, array $baru, string $alasan): LogKoreksi
    {
        return DB::transaction(function () use ($pengubah, $pegawai, $tanggal, $baru, $alasan) {
            $presensi = Presensi::firstOrCreate(['user_id' => $pegawai->id, 'tanggal' => $tanggal->toDateString()]);
            $milikSendiri = $pegawai->is($pengubah);

            $log = LogKoreksi::create([
                'user_id'            => $pengubah->id,
                'target_type'        => Presensi::class,
                'target_id'          => $presensi->id,
                'nilai_lama'         => self::nilai($presensi),
                'nilai_baru'         => $baru,
                'alasan'             => $alasan,
                'status_persetujuan' => $milikSendiri ? 'Menunggu' : 'Tidak Perlu',
            ]);

            if (! $milikSendiri) {
                $this->terapkan($presensi, $baru);
            }

            return $log;
        });
    }

    /** Terapkan nilai baru dan hitung ulang status datang/pulang dari jadwal hari itu. */
    public function terapkan(Presensi $presensi, array $baru): void
    {
        $data = [
            'status_harian'  => $baru['status_harian'],
            'referensi_type' => null, // koreksi manual
            'referensi_id'   => null,
        ];

        if ($baru['status_harian'] !== 'Hadir') {
            $data += [
                'jam_datang' => null, 'status_datang' => null, 'detik_terlambat' => 0,
                'jam_pulang' => null, 'pulang_cepat' => false, 'detik_pulang_cepat' => 0,
            ];
        } else {
            $tgl = $presensi->tanggal->toDateString();
            $j = $this->jadwal->jadwal($presensi->user, $presensi->tanggal->copy());

            $datang = Carbon::parse("{$tgl} {$baru['jam_datang']}");

            // Aturan sama dengan presensi scan: Tepat Waktu / Terlambat / Lewat Batas
            $data += ['jam_datang' => $baru['jam_datang']]
                + ($j ? Presensi::hitungDatang($datang, $j) : ['status_datang' => 'Tepat Waktu', 'detik_terlambat' => 0]);

            if ($baru['jam_pulang']) {
                $pulang = Carbon::parse("{$tgl} {$baru['jam_pulang']}");
                $cepat = $j && $pulang->lt($j['pulang']);

                $data += [
                    'jam_pulang'         => $baru['jam_pulang'],
                    'pulang_cepat'       => $cepat,
                    'detik_pulang_cepat' => $cepat ? $j['pulang']->timestamp - $pulang->timestamp : 0,
                ];
            } else {
                $data += ['jam_pulang' => null, 'pulang_cepat' => false, 'detik_pulang_cepat' => 0];
            }
        }

        $presensi->update($data);

        // Durasi lembur yang disetujui dibatasi jam pulang (SR-F22)
        $this->lembur->perbaruiUntuk($presensi->user_id, $presensi->tanggal->copy());
    }

    public function bolehMemproses(User $penyetuju, LogKoreksi $log): bool
    {
        $log->loadMissing('target.user');

        return $log->status_persetujuan === 'Menunggu'
            && $log->target instanceof Presensi
            && $log->target->user
            && $this->persetujuan->bolehMenyetujui($penyetuju, $log->target->user);
    }

    public function setujui(LogKoreksi $log, User $penyetuju): void
    {
        DB::transaction(function () use ($log, $penyetuju) {
            $this->terapkan($log->target, $log->nilai_baru);
            $log->update(['status_persetujuan' => 'Disetujui', 'penyetuju_id' => $penyetuju->id, 'diproses_at' => now()]);
        });
    }

    public function tolak(LogKoreksi $log, User $penyetuju, string $catatan): void
    {
        $log->update([
            'status_persetujuan' => 'Ditolak',
            'penyetuju_id'       => $penyetuju->id,
            'catatan_penyetuju'  => $catatan,
            'diproses_at'        => now(),
        ]);
    }
}