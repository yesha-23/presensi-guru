<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\PersetujuanSuratTugas;
use App\Models\Role;
use App\Models\SuratTugas;
use App\Models\User;
use App\Notifications\Pemberitahuan;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class SuratTugasService
{
    public function __construct(private StatusHarianService $statusHarian)
    {
    }

    // ================= Penanda tangan =================

    /** Semua pegawai KB & TK -> Kepala KB & TK; selain itu -> Kepala Sekolah SD. */
    public function kepalaSekolahUntuk(Collection $pegawai): ?User
    {
        $pegawai->loadMissing('unit');
        $semuaKbtk = $pegawai->isNotEmpty() && $pegawai->every(fn ($p) => $p->unit?->kode === 'KBTK');

        return User::where('status', 'Aktif')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::KEPALA_SEKOLAH))
            ->whereHas('unit', fn ($q) => $q->where('kode', $semuaKbtk ? 'KBTK' : 'SD'))
            ->first();
    }

    public function ketuaYayasan(): ?User
    {
        return User::where('status', 'Aktif')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::KETUA_YAYASAN))
            ->first();
    }

    // ================= Alur persetujuan =================

    /** Kirim untuk persetujuan. Mengembalikan pesan galat, atau null jika berhasil. */
    public function kirim(SuratTugas $surat): ?string
    {
        $surat->load('pegawai.unit');

        $kepsek = $this->kepalaSekolahUntuk($surat->pegawai);
        if (! $kepsek) {
            return 'Kepala sekolah penanda tangan belum terdaftar.';
        }

        $ketua = $surat->kasus_khusus ? $this->ketuaYayasan() : null;
        if ($surat->kasus_khusus && ! $ketua) {
            return 'Ketua Yayasan belum terdaftar.';
        }

        DB::transaction(function () use ($surat, $kepsek, $ketua) {
            $surat->persetujuan()->delete(); // kirim ulang setelah ditolak: mulai dari awal
            $surat->persetujuan()->create(['penyetuju_id' => $kepsek->id, 'urutan' => 1, 'status' => 'Menunggu']);
            if ($ketua) {
                $surat->persetujuan()->create(['penyetuju_id' => $ketua->id, 'urutan' => 2, 'status' => 'Menunggu']);
            }
            $surat->update(['status' => 'Menunggu', 'nomor_surat' => null, 'pdf_path' => null]);
        });

        $kepsek->notify(new Pemberitahuan(
            "Surat tugas menunggu persetujuan: {$surat->perihal}",
            $surat->rentangTanggal().' · '.$surat->pegawai->count().' pegawai',
            route('persetujuan', ['tab' => 'surat', 'id' => $surat->id]),
            'pengajuan'
        ));

        return null;
    }

    /** Tahap yang sedang menunggu (urutan paling kecil yang berstatus Menunggu). */
    public function tahapAktif(SuratTugas $surat): ?PersetujuanSuratTugas
    {
        return $surat->persetujuan->where('status', 'Menunggu')->sortBy('urutan')->first();
    }

    public function bolehMemproses(User $user, SuratTugas $surat): bool
    {
        $surat->loadMissing('persetujuan');
        $tahap = $this->tahapAktif($surat);

        return $surat->status === 'Menunggu' && $tahap && $tahap->penyetuju_id === $user->id;
    }

    public function setujui(SuratTugas $surat, User $penyetuju): void
    {
        $this->tahapAktif($surat)->update(['status' => 'Disetujui', 'ditandatangani_at' => now()]);
        $surat->load('persetujuan.penyetuju');

        // Masih ada tahap berikutnya (kasus khusus) -> teruskan ke Ketua Yayasan
        if ($berikut = $this->tahapAktif($surat)) {
            $berikut->penyetuju?->notify(new Pemberitahuan(
                "Surat tugas kasus khusus menunggu tanda tangan Anda: {$surat->perihal}",
                'Sudah ditandatangani '.$penyetuju->nama_lengkap,
                route('persetujuan', ['tab' => 'surat', 'id' => $surat->id]),
                'pengajuan'
            ));

            return;
        }

        $this->finalisasi($surat);
    }

    public function tolak(SuratTugas $surat, User $penyetuju, string $catatan): void
    {
        $this->tahapAktif($surat)->update(['status' => 'Ditolak', 'catatan' => $catatan]);
        $surat->update(['status' => 'Ditolak']);

        $surat->pembuat?->notify(new Pemberitahuan(
            "Surat tugas ditolak: {$surat->perihal}",
            "Oleh {$penyetuju->nama_lengkap}. Catatan: {$catatan}",
            route('admin.surat-tugas.show', $surat),
            'ditolak'
        ));
    }

    /** Semua tahap disetujui: nomor, PDF, status Tugas Khusus, notifikasi (SR-F24). */
    private function finalisasi(SuratTugas $surat): void
    {
        $nomor = DB::transaction(function () use ($surat) {
            $nomor = $this->nomorBaru($surat);
            $surat->update(['status' => 'Disetujui', 'nomor_surat' => $nomor]);

            return $nomor;
        });

        $surat->refresh()->load(['pegawai.unit', 'persetujuan.penyetuju.unit', 'pembuat']);
        $this->buatPdf($surat);

        // Tanggal yang sudah lewat & hari ini langsung menjadi Tugas Khusus
        $akhir = $surat->tanggal_selesai->copy()->min(today());
        if ($surat->tanggal_mulai->lte($akhir)) {
            foreach ($surat->pegawai as $pegawai) {
                foreach (CarbonPeriod::create($surat->tanggal_mulai, $akhir) as $tanggal) {
                    $this->statusHarian->tetapkan($pegawai, $tanggal->copy());
                }
            }
        }

        Notification::send($surat->pegawai, new Pemberitahuan(
            "Surat tugas baru: {$surat->perihal}",
            $surat->rentangTanggal(),
            route('pegawai.surat-tugas'),
            'berhasil'
        ));

        $surat->pembuat?->notify(new Pemberitahuan(
            "Surat tugas disetujui: {$surat->perihal}",
            "Nomor {$nomor}",
            route('admin.surat-tugas.show', $surat),
            'berhasil'
        ));
    }

    /**
     * Format mengikuti pola surat yayasan: 001/ST/SD-TB/X/2026
     * (urut per tahun / ST = Surat Tugas / kode unit / bulan romawi / tahun).
     */
    public function nomorBaru(SuratTugas $surat): string
    {
        $tahun = now()->year;
        $urut = SuratTugas::where('nomor_surat', 'like', "%/{$tahun}")->lockForUpdate()->count() + 1;
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][now()->month - 1];

        $kodeUnit = $surat->persetujuan->firstWhere('urutan', 1)?->penyetuju?->unit?->kode === 'KBTK' ? 'KBTK-TB' : 'SD-TB';

        return sprintf('%03d/ST/%s/%s/%d', $urut, $kodeUnit, $romawi, $tahun);
    }

    /** Buat (atau buat ulang) file PDF surat tugas yang sudah disetujui. */
    public function buatPdf(SuratTugas $surat): void
    {
        $surat->loadMissing(['pegawai.unit', 'persetujuan.penyetuju.unit']);

        if ($surat->pdf_path) {
            Storage::delete($surat->pdf_path);
        }

        $path = 'surat-tugas/'.str_replace('/', '-', $surat->nomor_surat).'.pdf';
        Storage::put($path, Pdf::loadView('pdf.surat-tugas', $this->dataSurat($surat, true))->setPaper('a4')->output());
        $surat->update(['pdf_path' => $path]);
    }

    // ================= Isi surat (pratinjau & PDF) =================
    public function dataSurat(SuratTugas $surat, bool $untukPdf = false): array
    {
        $surat->loadMissing(['pegawai.unit', 'persetujuan.penyetuju.unit']);

        $gambar = fn (?string $path) => $path && Storage::exists($path)
            ? 'data:image/png;base64,'.base64_encode(Storage::get($path))
            : null;

        if ($surat->persetujuan->isNotEmpty()) {
            $penandatangan = $surat->persetujuan->sortBy('urutan')->map(fn ($p) => [
                'jabatan' => $p->urutan === 1 ? 'Kepala Sekolah' : 'Ketua Yayasan',
                'nama'    => $p->penyetuju?->nama_lengkap,
                'ttd'     => $p->status === 'Disetujui' ? $gambar($p->penyetuju?->ttd_path) : null,
                'tanggal' => $p->ditandatangani_at,
            ])->values();
            $kepsek = $surat->persetujuan->firstWhere('urutan', 1)?->penyetuju;
        } else {
            // Draf: tampilkan perkiraan penanda tangan
            $kepsek = $this->kepalaSekolahUntuk($surat->pegawai);
            $penandatangan = collect([['jabatan' => 'Kepala Sekolah', 'nama' => $kepsek?->nama_lengkap, 'ttd' => null, 'tanggal' => null]]);
            if ($surat->kasus_khusus) {
                $penandatangan->push(['jabatan' => 'Ketua Yayasan', 'nama' => $this->ketuaYayasan()?->nama_lengkap, 'ttd' => null, 'tanggal' => null]);
            }
        }

        // Nama unit di kop mengikuti kepala sekolah penanda tangan
        $kbtk = $kepsek?->unit?->kode === 'KBTK';

        $logoPath = public_path('images/logo.png');

        return [
            'surat'         => $surat,
            'penandatangan' => $penandatangan,
            'modePdf'       => $untukPdf,
            'logo'          => $untukPdf && is_file($logoPath)
                ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
                : asset('images/logo.png'),
            'namaUnit'      => $kbtk ? 'KELOMPOK BERMAIN & TAMAN KANAK-KANAK TERUNA BANGSA' : 'SEKOLAH DASAR TERUNA BANGSA',
            'namaUnitBiasa' => $kbtk ? 'KB & TK Teruna Bangsa' : 'Sekolah Dasar Teruna Bangsa',
            'alamat'        => Pengaturan::ambil('alamat_yayasan', '[alamat yayasan]'),
            'kota'          => Pengaturan::ambil('kota_surat', 'Sleman'),
            'kontak'        => [
                'telepon' => Pengaturan::ambil('telepon_surat', ''),
                'web'     => Pengaturan::ambil('web_surat', ''),
            ],
        ];
    }
}