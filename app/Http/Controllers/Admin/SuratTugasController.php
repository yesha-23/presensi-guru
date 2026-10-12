<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\SuratTugas;
use App\Models\User;
use App\Services\SuratTugasService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SuratTugasController extends Controller
{
    public function __construct(private SuratTugasService $layanan)
    {
    }

    public function index(Request $request)
    {
        $filter = $request->only(['cari', 'status', 'jenis']);

        $surat = SuratTugas::with(['pegawai', 'persetujuan'])
            ->when($filter['cari'] ?? null, function ($q, $cari) {
                $q->where(fn ($q) => $q
                    ->where('perihal', 'like', "%{$cari}%")
                    ->orWhereHas('pegawai', fn ($p) => $p->where('nama_lengkap', 'like', "%{$cari}%")));
            })
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filter['jenis'] ?? null, fn ($q, $jenis) => $q->where('jenis_kegiatan', $jenis))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $pilihanPegawai = User::with('unit')
            ->where('status', 'Aktif')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::PEGAWAI))
            ->orderBy('nama_lengkap')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'nama' => $u->nama_lengkap, 'unit' => $u->unit->label()])
            ->values();

        return view('admin.surat-tugas.index', [
            'judul'          => 'Surat Tugas',
            'surat'          => $surat,
            'filter'         => $filter,
            'pilihanPegawai' => $pilihanPegawai,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request, 'tambah');

        $surat = DB::transaction(function () use ($data, $request) {
            $surat = SuratTugas::create(Arr::except($data, ['pegawai', 'lampiran']) + [
                'status'        => 'Draf',
                'dibuat_oleh'   => $request->user()->id,
                'lampiran_path' => $request->file('lampiran')?->store('lampiran-surat'),
            ]);
            $surat->pegawai()->sync($data['pegawai']);

            return $surat;
        });

        return $this->setelahSimpan($request, $surat);
    }

    public function show(SuratTugas $surat)
    {
        $surat->load(['pegawai.unit', 'persetujuan.penyetuju', 'pembuat']);

        return view('admin.surat-tugas.show', [
            'judul'     => 'Detail Surat Tugas',
            'surat'     => $surat,
            'dataSurat' => $this->layanan->dataSurat($surat),
        ]);
    }

    public function update(Request $request, SuratTugas $surat)
    {
        $this->pastikanBisaDiubah($surat);
        $data = $this->validasi($request, 'edit');

        DB::transaction(function () use ($data, $request, $surat) {
            $isi = Arr::except($data, ['pegawai', 'lampiran']);

            if ($request->hasFile('lampiran')) {
                if ($surat->lampiran_path) {
                    Storage::delete($surat->lampiran_path);
                }
                $isi['lampiran_path'] = $request->file('lampiran')->store('lampiran-surat');
            }

            $surat->update($isi);
            $surat->pegawai()->sync($data['pegawai']);
        });

        return $this->setelahSimpan($request, $surat->refresh());
    }

    /** Kirim draf / kirim ulang surat yang ditolak. */
    public function kirim(SuratTugas $surat)
    {
        $this->pastikanBisaDiubah($surat);

        if ($galat = $this->layanan->kirim($surat)) {
            return back()->with('galat', $galat);
        }

        return redirect()->route('admin.surat-tugas')
            ->with('sukses', "Surat tugas \"{$surat->perihal}\" dikirim ke kepala sekolah.");
    }

    public function destroy(SuratTugas $surat)
    {
        $this->pastikanBisaDiubah($surat);

        if ($surat->lampiran_path) {
            Storage::delete($surat->lampiran_path);
        }
        $surat->delete();

        return redirect()->route('admin.surat-tugas')->with('sukses', "Surat tugas \"{$surat->perihal}\" dihapus.");
    }

    // ================= Helper =================

    private function setelahSimpan(Request $request, SuratTugas $surat)
    {
        if ($request->input('aksi') === 'kirim') {
            if ($galat = $this->layanan->kirim($surat)) {
                return redirect()->route('admin.surat-tugas.show', $surat)->with('galat', $galat);
            }

            return redirect()->route('admin.surat-tugas')
                ->with('sukses', "Surat tugas \"{$surat->perihal}\" dikirim ke kepala sekolah.");
        }

        return redirect()->route('admin.surat-tugas')
            ->with('sukses', "Draf surat tugas \"{$surat->perihal}\" disimpan.");
    }

    private function pastikanBisaDiubah(SuratTugas $surat): void
    {
        abort_unless(
            in_array($surat->status, ['Draf', 'Ditolak'], true),
            403,
            'Surat yang sedang diproses atau sudah disetujui tidak dapat diubah.'
        );
    }

    private function validasi(Request $request, string $mode): array
    {
        $validator = Validator::make($request->all(), [
            'perihal'         => ['required', 'string', 'max:200'],
            'pemberi_tugas'   => ['required', Rule::in(['Dinas', 'Yayasan', 'Lainnya'])],
            'pegawai'         => ['required', 'array', 'min:1'],
            'pegawai.*'       => ['integer', Rule::exists('users', 'id')->where('status', 'Aktif')->whereNull('deleted_at')],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'lokasi'          => ['required', 'string', 'max:150'],
            'jenis_kegiatan'  => ['required', Rule::in(['Dalam', 'Luar'])],
            'sifat'           => ['required', Rule::in(['Terencana', 'Mendadak'])],
            'lampiran'        => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'required'                       => ':attribute wajib diisi.',
            'pegawai.required'               => 'Pilih minimal satu pegawai yang ditugaskan.',
            'pegawai.min'                    => 'Pilih minimal satu pegawai yang ditugaskan.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'lampiran.mimes'                 => 'Lampiran harus berformat PDF, JPG, atau PNG.',
            'lampiran.max'                   => 'Ukuran lampiran maksimal 2 MB.',
            'max'                            => ':attribute maksimal :max karakter.',
        ], [
            'perihal' => 'Perihal', 'pemberi_tugas' => 'Pemberi tugas', 'tanggal_mulai' => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai', 'lokasi' => 'Lokasi',
            'jenis_kegiatan' => 'Jenis kegiatan', 'sifat' => 'Sifat',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(back()->withErrors($validator)->withInput()->with('modal', $mode));
        }

        return $validator->validated() + ['kasus_khusus' => $request->boolean('kasus_khusus')];
    }
}