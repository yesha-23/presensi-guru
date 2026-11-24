<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KalenderEvent;
use App\Models\Semester;
use App\Models\Unit;
use App\Services\KalenderService;
use App\Services\StatusHarianService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class KalenderController extends Controller
{
    public function __construct(
        private KalenderService $kalender,
        private StatusHarianService $statusHarian,
    ) {
    }

    public function index(Request $request)
    {
        $units = Unit::whereIn('kode', ['SD', 'KBTK'])->orderBy('id')->get();
        $unit = $units->firstWhere('id', $request->integer('unit')) ?? $units->first();

        $bulan = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan'))
            ? Carbon::createFromFormat('Y-m-d', $request->query('bulan').'-01')->startOfDay()
            : now()->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth()->startOfDay();

        $entri = $this->kalender->entri($unit->id, $bulan, $akhir);

        // Kotak kalender: Senin sebelum tanggal 1 sampai Minggu setelah akhir bulan
        $hari = [];
        $periode = CarbonPeriod::create(
            $bulan->copy()->startOfWeek(Carbon::MONDAY),
            $akhir->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay()
        );

        foreach ($periode as $tanggal) {
            $tanggal = $tanggal->copy();
            $hari[] = [
                'tanggal'  => $tanggal,
                'bulanIni' => $tanggal->month === $bulan->month,
                'jenis'    => $this->kalender->jenisHari($tanggal, $entri),
                'entri'    => $entri->filter(fn ($e) => $tanggal->between($e->tanggal_mulai, $e->tanggal_selesai)),
            ];
        }

        $semesters = Semester::where('unit_id', $unit->id)->orderBy('tanggal_mulai')->get();

        return view('admin.kalender.index', [
            'judul'          => 'Kalender Akademik',
            'units'          => $units,
            'unit'           => $unit,
            'bulan'          => $bulan,
            'hari'           => $hari,
            'entri'          => $entri,
            'efektif'        => $this->kalender->hariEfektif($unit->id, $bulan, $akhir),
            'labelHariKerja' => $this->kalender->labelHariKerja(),
            'semesters'      => $semesters,
            'semesterAktif'  => $semesters->first(fn ($s) => $bulan->between($s->tanggal_mulai->copy()->startOfMonth(), $s->tanggal_selesai)),
            'daftarKategori' => KalenderService::KATEGORI,
            'daftarBerlaku'  => KalenderService::BERLAKU,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request, 'tambah');
        $entri = KalenderEvent::create($data + ['created_by' => $request->user()->id]);

        $jumlah = $this->statusHarian->hitungUlang($entri->tanggal_mulai, $entri->tanggal_selesai, $entri->unit_id);

        return $this->kembali($request, $entri, "Entri \"{$entri->nama}\" ditambahkan.", $jumlah);
    }

    public function update(Request $request, KalenderEvent $entri)
    {
        $data = $this->validasi($request, 'edit');

        $mulaiLama = $entri->tanggal_mulai->copy();
        $selesaiLama = $entri->tanggal_selesai->copy();
        $unitLama = $entri->unit_id;

        $entri->update($data);

        // Hitung ulang gabungan rentang lama & baru
        $jumlah = $this->statusHarian->hitungUlang(
            $mulaiLama->min($entri->tanggal_mulai),
            $selesaiLama->max($entri->tanggal_selesai),
            $unitLama === $entri->unit_id ? $entri->unit_id : null
        );

        return $this->kembali($request, $entri, "Entri \"{$entri->nama}\" diperbarui.", $jumlah);
    }

    public function destroy(Request $request, KalenderEvent $entri)
    {
        $entri->delete();

        // Mis. libur dihapus: tanggal lewat kembali dihitung sebagai hari kerja
        $jumlah = $this->statusHarian->hitungUlang($entri->tanggal_mulai, $entri->tanggal_selesai, $entri->unit_id);

        return $this->kembali($request, $entri, "Entri \"{$entri->nama}\" dihapus.", $jumlah);
    }

    public function simpanSemester(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unit_id'         => ['required', 'exists:units,id'],
            'tahun_ajaran'    => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'jenis'           => ['required', Rule::in(['Ganjil', 'Genap'])],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after:tanggal_mulai'],
        ], [
            'required'           => ':attribute wajib diisi.',
            'tahun_ajaran.regex' => 'Tahun ajaran ditulis seperti 2026/2027.',
            'after'              => 'Tanggal selesai harus setelah tanggal mulai.',
        ], [
            'tahun_ajaran' => 'Tahun ajaran', 'jenis' => 'Semester',
            'tanggal_mulai' => 'Tanggal mulai', 'tanggal_selesai' => 'Tanggal selesai',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator, 'semester')->withInput()->with('modal', 'semester');
        }

        $data = $validator->validated();

        Semester::updateOrCreate(
            Arr::only($data, ['unit_id', 'tahun_ajaran', 'jenis']),
            Arr::only($data, ['tanggal_mulai', 'tanggal_selesai'])
        );

        $nomor = $data['jenis'] === 'Genap' ? 2 : 1;

        return back()->with('sukses', "Semester {$nomor} Tahun Ajaran {$data['tahun_ajaran']} disimpan.");
    }

    private function validasi(Request $request, string $mode): array
    {
        $validator = Validator::make(
            $request->all(),
            KalenderService::aturan(),
            KalenderService::pesan(),
            KalenderService::atribut()
        );
        $validator->after(fn ($v) => KalenderService::cekTambahan($v, $request->all()));

        if ($validator->fails()) {
            throw new HttpResponseException(
                back()->withErrors($validator)->withInput()->with('modal', $mode)
            );
        }

        $data = $validator->validated();

        // Jam khusus hanya untuk Hari Kerja Tambahan
        if ($data['kategori'] !== KalenderEvent::HARI_KERJA_TAMBAHAN) {
            $data['jam_masuk'] = null;
            $data['jam_pulang'] = null;
        }

        return $data;
    }

    private function kembali(Request $request, KalenderEvent $entri, string $pesan, int $jumlah)
    {
        if ($jumlah > 0) {
            $pesan .= " Status presensi {$jumlah} tanggal yang sudah lewat dihitung ulang.";
        }

        return redirect()
            ->route('admin.kalender', ['unit' => $request->input('_unit'), 'bulan' => $entri->tanggal_mulai->format('Y-m')])
            ->with('sukses', $pesan);
    }
}