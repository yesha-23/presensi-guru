<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Unit;
use App\Services\PegawaiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImporPegawaiController extends Controller
{
    private const KOLOM = [
        'nip', 'nama_lengkap', 'nama_panggilan', 'email', 'unit',
        'kategori', 'jabatan', 'peran', 'kata_sandi',
    ];

    public function index()
    {
        return view('admin.pegawai.impor', [
            'judul'    => 'Impor Data Pegawai',
            'hasil'    => session('impor_pegawai'),
            'akunBaru' => session('akun_baru'),
        ]);
    }

    /** Langkah 1: unduh template Excel dengan kolom yang sudah ditentukan. */
    public function template()
    {
        $buku = new Spreadsheet();

        $lembar = $buku->getActiveSheet()->setTitle('Pegawai');
        $lembar->fromArray(self::KOLOM, null, 'A1');
        $lembar->fromArray(['', 'Ria Irawari, S.Pd.', 'Bu Ria', 'ria@contoh.sch.id', 'SD', 'Guru', 'Guru Kelas', 'Pegawai', ''], null, 'A2');
        $lembar->setCellValueExplicit('A2', '198702152010012001', DataType::TYPE_STRING);
        $lembar->getStyle('A1:I1')->getFont()->setBold(true);
        $lembar->getStyle('A1:A500')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach (range('A', 'I') as $kolom) {
            $lembar->getColumnDimension($kolom)->setAutoSize(true);
        }

        $petunjuk = $buku->createSheet()->setTitle('Petunjuk');
        $petunjuk->fromArray([
            ['Kolom', 'Isi'],
            ['nip', 'Wajib dan unik. Kolom sudah berformat teks agar NIP panjang tidak berubah.'],
            ['nama_lengkap', 'Wajib, lengkap dengan gelar (dipakai di laporan).'],
            ['nama_panggilan', 'Opsional, misalnya Bu Ria (dipakai di tampilan & notifikasi).'],
            ['email', 'Opsional, unik. Dipakai untuk login dan email pengingat.'],
            ['unit', 'SD, KBTK, atau YYS.'],
            ['kategori', 'Guru, Staf, Karyawan, atau Tendik. Wajib jika peran memuat Pegawai.'],
            ['jabatan', 'Opsional, misalnya Wali Kelas.'],
            ['peran', 'Pisahkan dengan koma: Pegawai, Administrator, Kepala Sekolah, Ketua Yayasan. Kosong = Pegawai.'],
            ['kata_sandi', 'Opsional, minimal 8 karakter. Kosong = dibuat otomatis oleh sistem.'],
        ]);
        $petunjuk->getStyle('A1:B1')->getFont()->setBold(true);
        $petunjuk->getColumnDimension('A')->setAutoSize(true);
        $petunjuk->getColumnDimension('B')->setAutoSize(true);

        $buku->setActiveSheetIndex(0);

        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            'template_data_pegawai.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /** Langkah 2: unggah file, validasi per baris, simpan hasil di sesi untuk pratinjau. */
    public function unggah(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ], [
            'file.required' => 'Pilih file Excel terlebih dahulu.',
            'file.mimes'    => 'File harus berformat .xlsx, .xls, atau .csv.',
            'file.max'      => 'Ukuran file maksimal 2 MB.',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs('impor', 'pegawai_'.now()->format('YmdHis').'.'.$file->getClientOriginalExtension());

        try {
            $baris = IOFactory::load(Storage::path($path))->getSheet(0)->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'File tidak dapat dibaca. Pastikan memakai template yang disediakan.']);
        } finally {
            Storage::delete($path);
        }

        $header = array_map(fn ($h) => Str::snake(trim((string) $h)), array_shift($baris) ?? []);

        foreach (['nip', 'nama_lengkap', 'unit'] as $wajib) {
            if (! in_array($wajib, $header, true)) {
                return back()->withErrors(['file' => "Kolom \"{$wajib}\" tidak ditemukan. Gunakan template yang disediakan."]);
            }
        }

        $unit = Unit::pluck('id', 'kode');
        $nipDiFile = [];
        $emailDiFile = [];
        $hasil = [];

        foreach ($baris as $i => $isi) {
            // Lewati baris kosong
            if (collect($isi)->filter(fn ($v) => trim((string) $v) !== '')->isEmpty()) {
                continue;
            }

            $mentah = [];
            foreach (self::KOLOM as $kolom) {
                $indeks = array_search($kolom, $header, true);
                $mentah[$kolom] = $indeks === false ? null : $this->teks($isi[$indeks] ?? null);
            }

            $kodeUnit = strtoupper(str_replace([' ', '&', '-'], '', (string) $mentah['unit']));
            $peran = $mentah['peran']
                ? array_values(array_filter(array_map('trim', explode(',', $mentah['peran']))))
                : [Role::PEGAWAI];

            $data = [
                'nip'            => $mentah['nip'],
                'nama_lengkap'   => $mentah['nama_lengkap'],
                'nama_panggilan' => $mentah['nama_panggilan'],
                'email'          => $mentah['email'] ? strtolower($mentah['email']) : null,
                'unit_id'        => $unit[$kodeUnit] ?? null,
                'kategori'       => $mentah['kategori'] ? ucfirst(strtolower($mentah['kategori'])) : null,
                'jabatan'        => $mentah['jabatan'],
                'peran'          => $peran,
                'kata_sandi'     => $mentah['kata_sandi'],
            ];

            $validator = Validator::make(
                $data,
                PegawaiService::aturan($data) + ['kata_sandi' => ['nullable', 'string', 'min:8']],
                PegawaiService::pesan(),
                PegawaiService::atribut()
            );

            $galat = $validator->errors()->all();

            if (! $validator->errors()->hasAny(['unit_id', 'kategori']) && $pesan = PegawaiService::cekJamKerja($data)) {
                $galat[] = $pesan;
            }
            if ($data['nip'] && in_array($data['nip'], $nipDiFile, true)) {
                $galat[] = 'NIP ganda di dalam file.';
            }
            if ($data['email'] && in_array($data['email'], $emailDiFile, true)) {
                $galat[] = 'Email ganda di dalam file.';
            }

            $nipDiFile[] = $data['nip'];
            if ($data['email']) {
                $emailDiFile[] = $data['email'];
            }

            $hasil[] = [
                'baris' => $i + 2, // nomor baris di Excel (baris 1 = judul kolom)
                'unit'  => $kodeUnit ?: '-',
                'data'  => $data,
                'galat' => $galat,
            ];
        }

        if (empty($hasil)) {
            return back()->withErrors(['file' => 'File tidak berisi data pegawai.']);
        }

        session(['impor_pegawai' => [
            'nama_file' => $file->getClientOriginalName(),
            'baris'     => $hasil,
        ]]);

        return redirect()->route('admin.pegawai.impor');
    }

    /** Langkah 3: simpan baris yang valid saja. */
    public function simpan()
    {
        $impor = session('impor_pegawai');

        if (! $impor) {
            return redirect()->route('admin.pegawai.impor');
        }

        $akunBaru = [];

        DB::transaction(function () use ($impor, &$akunBaru) {
            foreach ($impor['baris'] as $baris) {
                if ($baris['galat']) {
                    continue;
                }

                $data = $baris['data'];
                $kataSandi = $data['kata_sandi'] ?: PegawaiService::buatKataSandi();
                unset($data['kata_sandi']);

                $user = PegawaiService::simpan($data + ['password' => $kataSandi, 'status' => 'Aktif']);

                $akunBaru[] = ['nip' => $user->nip, 'nama' => $user->nama_lengkap, 'kata_sandi' => $kataSandi];
            }
        });

        session()->forget('impor_pegawai');

        return redirect()->route('admin.pegawai.impor')
            ->with('akun_baru', $akunBaru)
            ->with('sukses', count($akunBaru).' pegawai berhasil diimpor.');
    }

    public function batal()
    {
        session()->forget('impor_pegawai');

        return redirect()->route('admin.pegawai.impor');
    }

    /** Ubah isi sel menjadi teks; angka panjang (NIP) tidak berubah jadi notasi ilmiah. */
    private function teks(mixed $nilai): ?string
    {
        if ($nilai === null) {
            return null;
        }
        if (is_float($nilai) && floor($nilai) == $nilai) {
            $nilai = sprintf('%.0f', $nilai);
        }

        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }
}