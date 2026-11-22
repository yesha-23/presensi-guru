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

        $surat = SuratTugas::with(['pegawai', 'persetujuan', 'pengusul'])
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
            ->map(fn ($u) => ['id' => $u->id, 'nama' => $u->nama_lengkap, 'unit' => $u->unit->label(), 'kode' => $u->unit->kode])
            ->values();

        return view('admin.surat-tugas.index', [
            'judul'          => 'Surat Tugas',
            'surat'          => $surat,
            'filter'         => $filter,
            'pilihanPegawai' => $pilihanPegawai,
            'usulanBaru'     => SuratTugas::where('status', 'Diajukan')->count(),
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
        $surat->load(['pegawai.unit', 'persetujuan.penyetuju', 'pembuat', 'pengusul.unit']);

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

    /** Mulai memproses usulan pegawai (Diajukan -> Diproses). */
    public function proses(Request $request, SuratTugas $surat)
    {
        abort_unless($surat->status === 'Diajukan', 403, 'Usulan ini sudah diproses atau dibatalkan pengusul.');

        $this->layanan->proses($surat, $request->user());

        return redirect()->route('admin.surat-tugas.show', $surat)
            ->with('sukses', 'Usulan diproses. Periksa dan lengkapi data (pegawai lain, luar kota, dll.) lewat tombol Ubah, lalu kirim ke kepala sekolah.');
    }

    /** Tolak usulan pegawai dengan alasan. */
    public function tolakUsulan(Request $request, SuratTugas $surat)
    {
        abort_unless(in_array($surat->status, ['Diajukan', 'Diproses'], true) && $surat->diajukan_oleh, 403, 'Usulan ini tidak dapat ditolak.');

        $data = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan.required' => 'Alasan penolakan usulan wajib diisi.',
            'alasan.min'      => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $this->layanan->tolakUsulan($surat, $request->user(), $data['alasan']);

        return redirect()->route('admin.surat-tugas')
            ->with('sukses', "Usulan \"{$surat->perihal}\" ditolak. Pengusul sudah diberi tahu.");
    }

    /** Kirim draf / usulan yang sudah diproses / surat hasil revisi. */
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
        abort_unless($surat->bisaDihapus(), 403, 'Hanya draf, surat yang perlu revisi, atau surat yang ditolak yang dapat dihapus.');

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
            ->with('sukses', ($surat->status === 'Draf' ? 'Draf surat tugas' : 'Perubahan surat tugas')." \"{$surat->perihal}\" disimpan.");
    }

    private function pastikanBisaDiubah(SuratTugas $surat): void
    {
        abort_unless(
            $surat->bisaDiubah(),
            403,
            'Hanya draf, usulan yang sedang diproses, atau surat yang perlu revisi yang dapat diubah dan dikirim.'
        );
    }

    private function validasi(Request $request, string $mode): array
    {
        // Sebagian browser mengirim jam dalam format HH:MM:SS
        $request->merge([
            'jam_mulai'   => $request->filled('jam_mulai') ? substr($request->input('jam_mulai'), 0, 5) : null,
            'jam_selesai' => $request->filled('jam_selesai') ? substr($request->input('jam_selesai'), 0, 5) : null,
        ]);

        $validator = Validator::make($request->all(), [
            'perihal'         => ['required', 'string', 'max:200'],
            'pemberi_tugas'   => ['required', Rule::in(['Dinas', 'Yayasan', 'Lainnya'])],
            'pegawai'         => ['required', 'array', 'min:1'],
            'pegawai.*'       => ['integer', Rule::exists('users', 'id')->where('status', 'Aktif')->whereNull('deleted_at')],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'jam_mulai'       => ['nullable', 'date_format:H:i'],
            'jam_selesai'     => ['nullable', 'date_format:H:i'],
            'lokasi'          => ['required', 'string', 'max:150'],
            'keterangan'      => ['nullable', 'string', 'max:500'],
            'jenis_kegiatan'  => ['required', Rule::in(['Dalam', 'Luar'])],
            'sifat'           => ['required', Rule::in(['Terencana', 'Mendadak'])],
            'lampiran'        => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ], [
            'required'                       => ':attribute wajib diisi.',
            'pegawai.required'               => 'Pilih minimal satu pegawai yang ditugaskan.',
            'pegawai.min'                    => 'Pilih minimal satu pegawai yang ditugaskan.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'date_format'                    => 'Format :attribute tidak valid.',
            'lampiran.mimes'                 => 'Lampiran harus berformat PDF, JPG, atau PNG.',
            'lampiran.max'                   => 'Ukuran lampiran maksimal 2 MB.',
            'max'                            => ':attribute maksimal :max karakter.',
        ], [
            'perihal' => 'Perihal', 'pemberi_tugas' => 'Pemberi tugas', 'tanggal_mulai' => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai', 'jam_mulai' => 'Jam mulai', 'jam_selesai' => 'Jam selesai',
            'lokasi' => 'Lokasi', 'keterangan' => 'Keterangan',
            'jenis_kegiatan' => 'Jenis kegiatan', 'sifat' => 'Sifat',
        ]);

        // Jam selesai hanya boleh diisi bersama jam mulai, dan harus setelahnya
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
            throw new HttpResponseException(back()->withErrors($validator)->withInput()->with('modal', $mode));
        }

        $data = $validator->validated();

        // Pegawai KB & TK digabung dengan unit lain wajib kasus khusus (Kepala Sekolah SD lalu Ketua Yayasan)
        $pegawai = User::with('unit')->whereIn('id', $data['pegawai'])->get();
        $data['kasus_khusus'] = $request->boolean('kasus_khusus') || $this->layanan->lintasUnit($pegawai);

        return $data;
    }
}