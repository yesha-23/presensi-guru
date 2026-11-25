<?php

namespace App\Http\Controllers;

use App\Models\SuratTugas;
use App\Notifications\Pemberitahuan;
use App\Services\SuratTugasService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Usulan surat tugas oleh pegawai (arahan dosen pembimbing, 4 Nov 2026).
 * Contoh: guru menerima undangan lomba/rapat dinas langsung, lalu mengusulkan surat tugas
 * dengan melampirkan undangan. Admin memproses, melengkapi, dan mengirim ke kepala sekolah.
 * Pegawai dapat mengubah/membatalkan usulan selama masih berstatus Diajukan.
 */
class UsulanSuratTugasController extends Controller
{
    public function __construct(private SuratTugasService $layanan)
    {
    }

    /** Daftar usulan milik pegawai (halaman menu "Usulan Surat"). */
    public function index(Request $request)
    {
        return view('pegawai.usulan-surat.index', [
            'judul'  => 'Usulan Surat Tugas',
            'usulan' => SuratTugas::with('persetujuan')
                ->where('diajukan_oleh', $request->user()->id)
                ->latest()->paginate(10),
            'sorot'  => session('sorot'),
        ]);
    }

    public function create()
    {
        return view('pegawai.usulan-surat.form', ['judul' => 'Usulkan Surat Tugas', 'edit' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request, true);

        $surat = $this->layanan->ajukanUsulan(
            $request->user(),
            Arr::except($data, ['lampiran']),
            $request->file('lampiran')->store('lampiran-surat')
        );

        return redirect()->route('pegawai.usulan-surat')
            ->with('sukses', "Usulan surat tugas \"{$surat->perihal}\" dikirim ke administrator.")
            ->with('sorot', $surat->id);
    }

    public function edit(Request $request, SuratTugas $surat)
    {
        $this->pastikanMilikDanDiajukan($request, $surat);

        return view('pegawai.usulan-surat.form', ['judul' => 'Ubah Usulan Surat Tugas', 'edit' => $surat]);
    }

    public function update(Request $request, SuratTugas $surat)
    {
        $data = $this->validasi($request, false, $surat);

        $hasil = DB::transaction(function () use ($request, $surat, $data) {
            $s = SuratTugas::whereKey($surat->id)->lockForUpdate()->firstOrFail();
            if (! $this->milikDanDiajukan($request, $s)) {
                return false;
            }

            $isi = Arr::except($data, ['lampiran']);
            if ($request->hasFile('lampiran')) {
                if ($s->lampiran_path) {
                    Storage::delete($s->lampiran_path);
                }
                $isi['lampiran_path'] = $request->file('lampiran')->store('lampiran-surat');
            }
            $s->update($isi);

            return true;
        });

        if (! $hasil) {
            return redirect()->route('pegawai.usulan-surat')
                ->with('galat', 'Usulan sudah diproses administrator sehingga tidak dapat diubah lagi.');
        }

        $this->kabariAdmin("Usulan surat tugas diperbarui: {$surat->perihal}", $request->user()->nama_lengkap, $surat);

        return redirect()->route('pegawai.usulan-surat')
            ->with('sukses', 'Perubahan usulan surat tugas disimpan.')
            ->with('sorot', $surat->id);
    }

    public function batal(Request $request, SuratTugas $surat)
    {
        $perihal = $surat->perihal;

        $hasil = DB::transaction(function () use ($request, $surat) {
            $s = SuratTugas::whereKey($surat->id)->lockForUpdate()->first();
            if (! $s || ! $this->milikDanDiajukan($request, $s)) {
                return false;
            }

            if ($s->lampiran_path) {
                Storage::delete($s->lampiran_path);
            }
            $s->delete();

            return true;
        });

        if (! $hasil) {
            return redirect()->route('pegawai.usulan-surat')
                ->with('galat', 'Usulan sudah diproses administrator sehingga tidak dapat dibatalkan.');
        }

        Notification::send($this->layanan->administrator(), new Pemberitahuan(
            "Usulan surat tugas dibatalkan: {$perihal}",
            'Oleh '.$request->user()->nama_lengkap,
            route('admin.surat-tugas'),
            'info'
        ));

        return redirect()->route('pegawai.usulan-surat')->with('sukses', "Usulan \"{$perihal}\" dibatalkan.");
    }

    // ================= Helper =================

    private function milikDanDiajukan(Request $request, SuratTugas $surat): bool
    {
        return $surat->diajukan_oleh === $request->user()->id && $surat->status === 'Diajukan';
    }

    private function pastikanMilikDanDiajukan(Request $request, SuratTugas $surat): void
    {
        abort_unless($surat->diajukan_oleh === $request->user()->id, 403);

        if ($surat->status !== 'Diajukan') {
            throw new HttpResponseException(redirect()->route('pegawai.usulan-surat')
                ->with('galat', 'Usulan sudah diproses administrator sehingga tidak dapat diubah lagi.'));
        }
    }

    private function kabariAdmin(string $judul, string $nama, SuratTugas $surat): void
    {
        Notification::send($this->layanan->administrator(), new Pemberitahuan(
            $judul,
            "Oleh {$nama}",
            route('admin.surat-tugas.show', $surat),
            'pengajuan'
        ));
    }

    private function validasi(Request $request, bool $baru, ?SuratTugas $surat = null): array
    {
        if ($surat) {
            abort_unless($surat->diajukan_oleh === $request->user()->id, 403);
        }

        $request->merge([
            'jam_mulai'   => $request->filled('jam_mulai') ? substr($request->input('jam_mulai'), 0, 5) : null,
            'jam_selesai' => $request->filled('jam_selesai') ? substr($request->input('jam_selesai'), 0, 5) : null,
        ]);

        $validator = Validator::make($request->all(), [
            'perihal'         => ['required', 'string', 'min:5', 'max:200'],
            'pemberi_tugas'   => ['required', Rule::in(['Dinas', 'Yayasan', 'Lainnya'])],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'jam_mulai'       => ['nullable', 'date_format:H:i'],
            'jam_selesai'     => ['nullable', 'date_format:H:i'],
            'lokasi'          => ['required', 'string', 'max:150'],
            'keterangan'      => ['nullable', 'string', 'max:500'],
            'jenis_kegiatan'  => ['required', Rule::in(['Dalam', 'Luar'])],
            'sifat'           => ['required', Rule::in(['Terencana', 'Mendadak'])],
            // Undangan/surat dari pemberi tugas wajib saat mengusulkan (bukti dasar penerbitan surat)
            'lampiran'        => [$baru ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            // Pesan berisi apa yang salah dan cara memperbaikinya (tindak lanjut uji usabilitas)
            'required'                       => ':attribute wajib diisi.',
            'perihal.required'               => 'Perihal wajib diisi. Tuliskan nama kegiatan, misalnya "Pendampingan lomba FLS2N".',
            'perihal.min'                    => 'Perihal terlalu singkat (minimal 5 karakter). Tuliskan nama kegiatan, misalnya "Pendampingan lomba FLS2N".',
            'lokasi.required'                => 'Lokasi wajib diisi. Tuliskan tempat kegiatan, misalnya "Dinas Pendidikan Sleman".',
            'lampiran.required'              => 'Lampirkan undangan atau surat dari pemberi tugas (PDF, JPG, atau PNG, maksimal 2 MB).',
            'tanggal_mulai.after_or_equal'   => 'Tanggal mulai paling cepat hari ini. Pilih tanggal hari ini atau setelahnya.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai. Pilih tanggal yang sama atau setelah tanggal mulai.',
            'date_format'                    => 'Format :attribute tidak valid.',
            'lampiran.mimes'                 => 'Lampiran harus berformat PDF, JPG, atau PNG. Pilih ulang berkas dengan format tersebut.',
            'lampiran.max'                   => 'Ukuran lampiran lebih dari 2 MB. Pilih berkas lain atau perkecil ukurannya terlebih dahulu.',
            'max'                            => ':attribute maksimal :max karakter.',
        ], [
            'perihal' => 'Perihal', 'pemberi_tugas' => 'Pemberi tugas', 'tanggal_mulai' => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai', 'jam_mulai' => 'Jam mulai', 'jam_selesai' => 'Jam selesai',
            'lokasi' => 'Lokasi', 'keterangan' => 'Keterangan', 'jenis_kegiatan' => 'Jenis kegiatan', 'sifat' => 'Sifat',
        ]);

        $validator->after(function ($v) use ($request) {
            if ($v->errors()->hasAny(['jam_mulai', 'jam_selesai'])) {
                return;
            }
            $mulai = $request->input('jam_mulai');
            $selesai = $request->input('jam_selesai');

            if ($selesai && ! $mulai) {
                $v->errors()->add('jam_mulai', 'Isi jam mulai jika jam selesai diisi.');
            } elseif ($mulai && $selesai && $selesai <= $mulai) {
                $v->errors()->add('jam_selesai', 'Jam selesai harus setelah jam mulai.');
            }
        });

        if ($validator->fails()) {
            throw new HttpResponseException(back()->withErrors($validator)->withInput());
        }

        return $validator->validated();
    }
}
