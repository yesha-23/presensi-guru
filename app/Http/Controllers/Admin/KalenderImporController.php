<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KalenderEvent;
use App\Models\Unit;
use App\Services\KalenderService;
use App\Services\StatusHarianService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class KalenderImporController extends Controller
{
    private const KOLOM = [
        'tanggal_mulai', 'tanggal_selesai', 'nama', 'kategori',
        'berlaku_untuk', 'unit', 'jam_masuk', 'jam_pulang',
    ];

    public function __construct(private StatusHarianService $statusHarian)
    {
    }

    public function index(Request $request)
    {
        $units = Unit::whereIn('kode', ['SD', 'KBTK'])->orderBy('id')->get();
        $hasil = session('impor_kalender');
        $unit = $units->firstWhere('id', $hasil['unit_id'] ?? $request->integer('unit')) ?? $units->first();

        return view('admin.kalender.impor', [
            'judul'     => 'Impor Kalender Akademik',
            'units'     => $units,
            'unit'      => $unit,
            'hasil'     => $hasil,
            'labelUnit' => Unit::all()->mapWithKeys(fn ($u) => [$u->id => $u->label()]),
        ]);
    }

    public function template()
    {
        $buku = new Spreadsheet();

        $lembar = $buku->getActiveSheet()->setTitle('Kalender');
        $lembar->getStyle('A1:B500')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $lembar->getStyle('G1:H500')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $lembar->fromArray([
            self::KOLOM,
            ['2026-10-10', '2026-10-10', 'Rapat Sabtu', 'Hari Kerja Tambahan', 'Guru dan Staf', 'KBTK', '07:00', '12:00'],
            ['2026-10-12', '2026-10-12', 'Ibadah Awal Bulan', 'Kegiatan Sekolah', 'Guru dan Staf', 'KBTK', '', ''],
            ['2026-12-21', '2026-12-31', 'Libur Akhir Semester 1', 'Libur Internal', 'Siswa', 'KBTK', '', ''],
            ['2026-12-25', '2026-12-25', 'Hari Raya Natal', 'Libur Nasional', 'Guru dan Staf', 'Semua', '', ''],
        ], null, 'A1', true);
        $lembar->getStyle('A1:H1')->getFont()->setBold(true);
        foreach (range('A', 'H') as $kolom) {
            $lembar->getColumnDimension($kolom)->setAutoSize(true);
        }

        $petunjuk = $buku->createSheet()->setTitle('Petunjuk');
        $petunjuk->fromArray([
            ['Kolom', 'Isi'],
            ['tanggal_mulai, tanggal_selesai', 'Wajib, format YYYY-MM-DD (contoh 2026-12-25). Untuk satu hari, isi keduanya sama.'],
            ['nama', 'Wajib, misalnya Libur Akhir Semester 1.'],
            ['kategori', 'Libur Nasional, Cuti Bersama, Libur Internal, Kegiatan Sekolah, atau Hari Kerja Tambahan.'],
            ['berlaku_untuk', 'Guru dan Staf, atau Siswa. Libur siswa dan libur guru-staf dicatat sebagai baris terpisah.'],
            ['unit', 'Semua, SD, atau KBTK. Kosong = unit yang dipilih saat mengunggah.'],
            ['jam_masuk, jam_pulang', 'Hanya untuk Hari Kerja Tambahan, format HH:MM (contoh 07:00).'],
        ]);
        $petunjuk->getStyle('A1:B1')->getFont()->setBold(true);
        $petunjuk->getColumnDimension('A')->setAutoSize(true);
        $petunjuk->getColumnDimension('B')->setAutoSize(true);

        $buku->setActiveSheetIndex(0);

        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            'template_kalender_akademik.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function unggah(Request $request)
    {
        $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'file'    => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ], [
            'file.required' => 'Pilih file Excel terlebih dahulu.',
            'file.mimes'    => 'File harus berformat .xlsx, .xls, atau .csv.',
            'file.max'      => 'Ukuran file maksimal 2 MB.',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs('impor', 'kalender_'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension());

        try {
            $baris = IOFactory::load(Storage::path($path))->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'File tidak dapat dibaca. Pastikan memakai template yang disediakan.']);
        } finally {
            Storage::delete($path);
        }

        $header = array_map(fn ($h) => Str::snake(trim((string) $h)), array_shift($baris) ?? []);
        foreach (['tanggal_mulai', 'tanggal_selesai', 'nama', 'kategori'] as $wajib) {
            if (! in_array($wajib, $header, true)) {
                return back()->withErrors(['file' => "Kolom \"{$wajib}\" tidak ditemukan. Gunakan template yang disediakan."]);
            }
        }

        $unitHalaman = (int) $request->input('unit_id');
        $kodeUnit = Unit::pluck('id', 'kode');
        $hasil = [];

        foreach ($baris as $i => $isi) {
            if (collect($isi)->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
                continue;
            }

            $mentah = [];
            foreach (self::KOLOM as $kolom) {
                $indeks = array_search($kolom, $header, true);
                $mentah[$kolom] = $indeks === false ? null : ($isi[$indeks] ?? null);
            }

            $teksUnit = strtoupper(str_replace([' ', '&', '-'], '', (string) $mentah['unit']));
            $berlaku = Str::lower(trim((string) $mentah['berlaku_untuk']));

            $data = [
                'tanggal_mulai'   => $this->tanggal($mentah['tanggal_mulai']),
                'tanggal_selesai' => $this->tanggal($mentah['tanggal_selesai']),
                'nama'            => trim((string) $mentah['nama']) ?: null,
                'kategori'        => collect(KalenderService::KATEGORI)
                    ->first(fn ($k) => strcasecmp($k, trim((string) $mentah['kategori'])) === 0) ?? trim((string) $mentah['kategori']),
                'berlaku_untuk'   => match (true) {
                    $berlaku === '' || str_contains($berlaku, 'guru') => 'Guru dan Staf',
                    str_contains($berlaku, 'siswa') => 'Siswa',
                    default => $mentah['berlaku_untuk'],
                },
                'unit_id'         => match (true) {
                    $teksUnit === '' => $unitHalaman,
                    $teksUnit === 'SEMUA' => null,
                    default => $kodeUnit[$teksUnit] ?? 0,
                },
                'jam_masuk'       => $this->jam($mentah['jam_masuk']),
                'jam_pulang'      => $this->jam($mentah['jam_pulang']),
            ];

            $validator = Validator::make($data, KalenderService::aturan(), KalenderService::pesan(), KalenderService::atribut());
            $validator->after(fn ($v) => KalenderService::cekTambahan($v, $data));
            $validator->fails();

            $hasil[] = [
                'baris' => $i + 2,
                'data'  => $data,
                'galat' => array_values(array_unique($validator->errors()->all())),
            ];
        }

        if (empty($hasil)) {
            return back()->withErrors(['file' => 'File tidak berisi entri kalender.']);
        }

        session(['impor_kalender' => [
            'nama_file' => $file->getClientOriginalName(),
            'unit_id'   => $unitHalaman,
            'baris'     => $hasil,
        ]]);

        return redirect()->route('admin.kalender.impor');
    }

    public function simpan(Request $request)
    {
        $impor = session('impor_kalender');
        if (! $impor) {
            return redirect()->route('admin.kalender.impor');
        }

        $tersimpan = collect();

        DB::transaction(function () use ($impor, $request, $tersimpan) {
            foreach ($impor['baris'] as $baris) {
                if ($baris['galat']) {
                    continue;
                }

                $data = $baris['data'];
                if ($data['kategori'] !== KalenderEvent::HARI_KERJA_TAMBAHAN) {
                    $data['jam_masuk'] = null;
                    $data['jam_pulang'] = null;
                }

                $tersimpan->push(KalenderEvent::create($data + ['created_by' => $request->user()->id]));
            }
        });

        session()->forget('impor_kalender');

        // Hitung ulang tanggal lewat yang tercakup (misalnya Alpa -> Libur)
        $jumlah = $tersimpan->isEmpty() ? 0 : $this->statusHarian->hitungUlang(
            Carbon::parse($tersimpan->min('tanggal_mulai')),
            Carbon::parse($tersimpan->max('tanggal_selesai'))
        );

        $pesan = $tersimpan->count().' entri kalender berhasil diimpor.';
        if ($jumlah > 0) {
            $pesan .= " Status presensi {$jumlah} tanggal yang sudah lewat dihitung ulang.";
        }

        return redirect()->route('admin.kalender', ['unit' => $impor['unit_id']])->with('sukses', $pesan);
    }

    public function batal()
    {
        $unitId = session('impor_kalender.unit_id');
        session()->forget('impor_kalender');

        return redirect()->route('admin.kalender.impor', ['unit' => $unitId]);
    }

    /** Terima tanggal dari Excel (angka seri) atau teks YYYY-MM-DD / DD/MM/YYYY. */
    private function tanggal(mixed $nilai): ?string
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return null;
        }

        if (is_numeric($nilai)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $nilai)->format('Y-m-d');
            } catch (\Throwable) {
                return 'tidak-valid';
            }
        }

        $nilai = trim((string) $nilai);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'j/n/Y'] as $format) {
            $hasil = \DateTime::createFromFormat('!'.$format, $nilai);
            if ($hasil && $hasil->format($format) === $nilai) {
                return $hasil->format('Y-m-d');
            }
        }

        return 'tidak-valid';
    }

    /** Terima jam dari Excel (pecahan hari) atau teks 07:00 / 07.00 / 07:00:00. */
    private function jam(mixed $nilai): ?string
    {
        if ($nilai === null || trim((string) $nilai) === '') {
            return null;
        }

        if (is_numeric($nilai) && (float) $nilai < 1) {
            $detik = (int) round((float) $nilai * 86400);

            return sprintf('%02d:%02d', intdiv($detik, 3600), intdiv($detik % 3600, 60));
        }

        $nilai = str_replace('.', ':', trim((string) $nilai));

        return preg_match('/^(\d{1,2}):(\d{2})/', $nilai, $m) ? sprintf('%02d:%s', $m[1], $m[2]) : $nilai;
    }
}