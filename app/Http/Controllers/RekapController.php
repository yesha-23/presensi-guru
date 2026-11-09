<?php

namespace App\Http\Controllers;

use App\Models\Pengaturan;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Unit;
use App\Models\User;
use App\Services\PersetujuanService;
use App\Services\RekapService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RekapController extends Controller
{
    public function __construct(private RekapService $rekap)
    {
    }

    // ================= Rekap format yayasan =================

    public function rekap(Request $request)
    {
        return view('rekap.index', ['judul' => 'Rekap Presensi'] + $this->dataRekap($request));
    }

    public function rekapExcel(Request $request)
    {
        $d = $this->dataRekap($request);

        return $this->unduhExcel($this->bukuRekap($d), 'rekap_presensi_'.$this->namaPeriode($d).'.xlsx');
    }

    public function rekapPdf(Request $request)
    {
        $d = $this->dataRekap($request);

        return Pdf::loadView('pdf.rekap', $d)->setPaper('a4', 'landscape')
            ->download('rekap_presensi_'.$this->namaPeriode($d).'.pdf');
    }

    // ================= Laporan PKG =================

    public function pkg(Request $request)
    {
        return view('rekap.pkg', ['judul' => 'Laporan PKG'] + $this->dataPkg($request));
    }

    public function pkgExcel(Request $request)
    {
        $d = $this->dataPkg($request);

        return $this->unduhExcel($this->bukuPkg($d), 'laporan_pkg_'.$this->namaPeriode($d).'.xlsx');
    }

    public function pkgPdf(Request $request)
    {
        $d = $this->dataPkg($request);

        return Pdf::loadView('pdf.pkg', $d)->setPaper('a4', 'portrait')
            ->download('laporan_pkg_'.$this->namaPeriode($d).'.pdf');
    }

    // ================= Data =================

    private function dataRekap(Request $request): array
    {
        $f = $this->filter($request);
        $kelompok = in_array($request->query('kelompok'), ['Guru', 'Staf'], true) ? $request->query('kelompok') : null;

        $hasil = $this->rekap->hitung(
            $this->rekap->pegawai($f['mulai'], $f['selesai'], $f['unit']?->id, $kelompok),
            $f['mulai'],
            $f['selesai']
        );

        return $f + $hasil + [
            'kelompok'      => $kelompok,
            'penandatangan' => $this->penandatangan($f['unit']),
            'kota'          => Pengaturan::ambil('kota_surat', 'Sleman'),
        ];
    }

    private function dataPkg(Request $request): array
    {
        $f = $this->filter($request, 'semester');

        $hasil = $this->rekap->hitung(
            $this->rekap->pegawai($f['mulai'], $f['selesai'], $f['unit']?->id, 'Guru'),
            $f['mulai'],
            $f['selesai']
        );

        // Perbandingan antarunit (UR-27) bila semua unit ditampilkan
        $perUnit = $f['unit'] ? collect() : $hasil['baris']
            ->groupBy(fn ($r) => $r['user']->unit?->label() ?? '-')
            ->map(fn ($baris) => [
                'jumlah'      => $baris->count(),
                'persen'      => round($baris->avg('persen'), 1),
                'persen_alpa' => round($baris->avg('persen_alpa'), 1),
            ]);

        return $f + $hasil + [
            'perUnit'       => $perUnit,
            'penandatangan' => $this->penandatangan($f['unit']),
            'kota'          => Pengaturan::ambil('kota_surat', 'Sleman'),
        ];
    }

    /** Filter unit & periode. Kepala sekolah hanya bisa melihat unitnya sendiri. */
    private function filter(Request $request, ?string $paksaJenis = null): array
    {
        $user = $request->user();
        $hanyaUnitSendiri = $user->hasRole(Role::KEPALA_SEKOLAH)
            && ! $user->hasRole(Role::ADMIN)
            && ! $user->hasRole(Role::KETUA_YAYASAN);

        $units = $hanyaUnitSendiri ? Unit::whereKey($user->unit_id)->get() : Unit::orderBy('id')->get();
        $unit = $hanyaUnitSendiri ? $units->first() : $units->firstWhere('id', $request->integer('unit'));

        $unitSemester = $unit && $unit->kode !== 'YYS' ? $unit : Unit::where('kode', 'SD')->first();
        $semesters = Semester::where('unit_id', $unitSemester?->id)->orderByDesc('tanggal_mulai')->get();

        $tanggal = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? Carbon::parse($v)->startOfDay() : null;

        $jenis = $paksaJenis
            ?? (in_array($request->query('periode'), ['mingguan', 'bulanan', 'semester', 'rentang'], true) ? $request->query('periode') : 'bulanan');

        $semester = null;
        if ($jenis === 'semester') {
            $semester = $semesters->firstWhere('id', $request->integer('semester'))
                ?? $semesters->first(fn ($s) => today()->between($s->tanggal_mulai, $s->tanggal_selesai))
                ?? $semesters->first();
            if (! $semester) {
                $jenis = 'bulanan';
            }
        }

        if ($jenis === 'mingguan') {
            $mulai = ($tanggal($request->query('tanggal')) ?? today())->startOfWeek(Carbon::MONDAY);
            $selesai = $mulai->copy()->addDays(6);
        } elseif ($jenis === 'semester') {
            $mulai = $semester->tanggal_mulai->copy();
            $selesai = $semester->tanggal_selesai->copy();
        } elseif ($jenis === 'rentang') {
            $mulai = $tanggal($request->query('mulai')) ?? today()->startOfMonth();
            $selesai = $tanggal($request->query('selesai')) ?? today();
            if ($selesai->lt($mulai)) {
                [$mulai, $selesai] = [$selesai, $mulai];
            }
        } else {
            $bulan = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan')) ? $request->query('bulan') : today()->format('Y-m');
            $mulai = Carbon::createFromFormat('Y-m-d', $bulan.'-01')->startOfDay();
            $selesai = $mulai->copy()->endOfMonth()->startOfDay();
        }

        $label = match ($jenis) {
            'mingguan' => 'Minggu '.$mulai->translatedFormat('j M').' – '.$selesai->translatedFormat('j M Y'),
            'semester' => "Semester {$semester->jenis} {$semester->tahun_ajaran} (".$mulai->translatedFormat('j M Y').' – '.$selesai->translatedFormat('j M Y').')',
            'rentang'  => $mulai->translatedFormat('j M Y').' – '.$selesai->translatedFormat('j M Y'),
            default    => $mulai->translatedFormat('j').'–'.$selesai->translatedFormat('j F Y'),
        };

        return [
            'units'         => $units,
            'unit'          => $unit,
            'bisaPilihUnit' => ! $hanyaUnitSendiri,
            'semesters'     => $semesters,
            'semester'      => $semester,
            'jenis'         => $jenis,
            'mulai'         => $mulai,
            'selesai'       => $selesai,
            'label'         => $label,
        ];
    }

    /** Nama penanda tangan blok TTD rekap. */
    private function penandatangan(?Unit $unit): array
    {
        $nama = fn (string $peran, ?string $kodeUnit = null) => User::where('status', 'Aktif')
            ->whereHas('roles', fn ($q) => $q->where('nama', $peran))
            ->when($kodeUnit, fn ($q, $k) => $q->whereHas('unit', fn ($u) => $u->where('kode', $k)))
            ->value('nama_lengkap');

        return [
            'kepsek' => $nama(Role::KEPALA_SEKOLAH, $unit?->kode === 'KBTK' ? 'KBTK' : 'SD'),
            'kabag'  => PersetujuanService::kabag()?->nama_lengkap,
            'ketua'  => $nama(Role::KETUA_YAYASAN),
        ];
    }

    private function namaPeriode(array $d): string
    {
        return ($d['unit']?->kode ?? 'SEMUA').'_'.$d['mulai']->format('Ymd').'-'.$d['selesai']->format('Ymd');
    }

    // ================= Excel =================

    private function bukuRekap(array $d): Spreadsheet
    {
        $buku = new Spreadsheet();
        $s = $buku->getActiveSheet()->setTitle('Rekap');

        $s->setCellValue('A1', 'REKAP PRESENSI GURU DAN STAF');
        $s->setCellValue('A2', ($d['unit']?->nama ?? 'Semua unit').' · '.$d['label']);
        $s->setCellValue('A3', 'Data s.d. '.$d['akhir']->translatedFormat('j F Y'));
        foreach ([1, 2, 3] as $r) {
            $s->mergeCells("A{$r}:N{$r}");
        }
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $s->fromArray(['No', 'Nama', 'MSUK', 'DNAS', 'TLAT', 'LEMB', 'DGTI', 'PGTI', 'LJAM', 'Ekskul', 'Ekskul', 'KDLM', 'KKLR', 'KETERANGAN'], null, 'A5');
        $s->fromArray(['', '', 'hari', 'hari', 'jam', 'jam', 'jam', 'jam', 'JP', 'jaga', 'ajar ×', 'hari', 'hari', ''], null, 'A6');

        $baris = 7;
        foreach (['Guru' => 'A. GURU', 'Staf' => 'B. STAF'] as $kelompok => $judul) {
            $isi = $d['baris']->where('kelompok', $kelompok)->values();
            if ($isi->isEmpty()) {
                continue;
            }

            $s->setCellValue("A{$baris}", $judul);
            $s->mergeCells("A{$baris}:N{$baris}");
            $s->getStyle("A{$baris}")->getFont()->setBold(true);
            $baris++;

            foreach ($isi as $i => $r) {
                $s->fromArray([
                    $i + 1, $r['user']->nama_lengkap, $r['hadir'], $r['dnas'],
                    RekapService::jam($r['detik_terlambat']), RekapService::jamDariMenit($r['menit_lembur']),
                    null, null, null, null, null,
                    $r['kdlm'], $r['kklr'], $r['keterangan'] === '—' ? '-' : $r['keterangan'],
                ], null, "A{$baris}");
                $baris++;
            }
        }
        $akhirTabel = max(6, $baris - 1);

        // Gaya tabel
        $s->getStyle('A5:N6')->getFont()->setBold(true);
        $s->getStyle('A5:N6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $s->getStyle('A5:N6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFE8F8');
        $s->getStyle("G5:K{$akhirTabel}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9'); // kolom manual
        $s->getStyle("A5:N{$akhirTabel}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $s->getStyle("C7:M{$akhirTabel}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Blok tanda tangan
        $t = $akhirTabel + 2;
        $s->setCellValue("B{$t}", $d['kota'].', '.now()->translatedFormat('j F Y'));
        $s->setCellValue("L{$t}", 'Mengetahui,');
        $s->setCellValue('B'.($t + 1), 'Kepala Sekolah');
        $s->setCellValue('F'.($t + 1), 'Kabag Adm. Umum');
        $s->setCellValue('L'.($t + 1), 'Ketua Yayasan');
        $s->setCellValue('B'.($t + 5), $d['penandatangan']['kepsek'] ?? '');
        $s->setCellValue('F'.($t + 5), $d['penandatangan']['kabag'] ?? '');
        $s->setCellValue('L'.($t + 5), $d['penandatangan']['ketua'] ?? '');
        $s->getStyle('A'.($t + 5).':N'.($t + 5))->getFont()->setBold(true);

        $s->getColumnDimension('A')->setWidth(5);
        $s->getColumnDimension('B')->setWidth(36);
        foreach (range('C', 'M') as $kolom) {
            $s->getColumnDimension($kolom)->setWidth(8);
        }
        $s->getColumnDimension('N')->setWidth(24);

        return $buku;
    }

    private function bukuPkg(array $d): Spreadsheet
    {
        $buku = new Spreadsheet();
        $s = $buku->getActiveSheet()->setTitle('Laporan PKG');

        $s->setCellValue('A1', 'LAPORAN PRESENSI GURU UNTUK PENILAIAN KINERJA GURU (PKG)');
        $s->setCellValue('A2', ($d['unit']?->nama ?? 'Semua unit').' · '.$d['label']);
        $s->setCellValue('A3', 'Data s.d. '.$d['akhir']->translatedFormat('j F Y').' · bahan pendukung PKG, bukan nilai akhir PKG');
        foreach ([1, 2, 3] as $r) {
            $s->mergeCells("A{$r}:M{$r}");
        }
        $s->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $s->fromArray(['No', 'Guru', 'Unit', 'Hari efektif', 'Hadir', 'Tugas khusus', 'Izin', 'Sakit', 'Alpa',
            'Terlambat (kali)', 'Total terlambat', 'Kehadiran (%)', 'Alpa (%)'], null, 'A5');

        $baris = 6;
        foreach ($d['baris']->values() as $i => $r) {
            $s->fromArray([
                $i + 1, $r['user']->nama_lengkap, $r['user']->unit?->label(), $r['efektif'], $r['hadir'], $r['tugas'],
                $r['izin'], $r['sakit'], $r['alpa'], $r['terlambat'], Presensi::formatDetik($r['detik_terlambat']),
                $r['persen'], $r['persen_alpa'],
            ], null, "A{$baris}");
            $baris++;
        }
        $akhir = max(5, $baris - 1);

        $s->getStyle('A5:M5')->getFont()->setBold(true);
        $s->getStyle('A5:M5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFE8F8');
        $s->getStyle("A5:M{$akhir}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $s->setCellValue('A'.($akhir + 2), 'Kehadiran = (Hadir + Tugas Khusus) ÷ Hari Efektif × 100%. % Alpa = Alpa ÷ Hari Efektif × 100%.');

        $s->getColumnDimension('B')->setWidth(36);
        foreach (['A', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M'] as $kolom) {
            $s->getColumnDimension($kolom)->setAutoSize(true);
        }

        return $buku;
    }

    private function unduhExcel(Spreadsheet $buku, string $nama)
    {
        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            $nama,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}