<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\PegawaiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->only(['cari', 'unit', 'kategori', 'status']);

        $pegawai = User::with(['unit', 'roles'])
            ->withCount('faceDescriptors')
            ->when($filter['cari'] ?? null, function ($q, $cari) {
                $q->where(fn ($q) => $q
                    ->where('nama_lengkap', 'like', "%{$cari}%")
                    ->orWhere('nama_panggilan', 'like', "%{$cari}%")
                    ->orWhere('nip', 'like', "%{$cari}%"));
            })
            ->when($filter['unit'] ?? null, fn ($q, $unit) => $q->where('unit_id', $unit))
            ->when($filter['kategori'] ?? null, fn ($q, $kategori) => $q->where('kategori', $kategori))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'Aktif', 'Nonaktif', 'Keluar')")
            ->orderBy('nama_lengkap')
            ->paginate(10)
            ->withQueryString();

        return view('admin.pegawai.index', [
            'judul'          => 'Data Pegawai',
            'pegawai'        => $pegawai,
            'filter'         => $filter,
            'units'          => Unit::orderBy('id')->get(),
            'jumlahStatus'   => User::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'daftarPeran'    => PegawaiService::PERAN,
            'daftarKategori' => PegawaiService::KATEGORI,
            'daftarStatus'   => PegawaiService::STATUS,
        ]);
    }

    public function store(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make(
            $input,
            PegawaiService::aturan($input) + ['kata_sandi' => ['required', 'string', 'min:8']],
            PegawaiService::pesan(),
            PegawaiService::atribut()
        );
        $validator->after(fn ($v) => $this->cekJamKerja($v, $input));

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal', 'tambah');
        }

        $data = $validator->validated();
        $kataSandi = $data['kata_sandi'];
        unset($data['kata_sandi']);

        $user = PegawaiService::simpan($data + ['password' => $kataSandi, 'status' => 'Aktif']);

        return redirect()->route('admin.pegawai')
            ->with('sukses', "Pegawai {$user->nama_lengkap} tersimpan. Kata sandi awal: {$kataSandi}")
            ->with('tawarkan_wajah', $user->id);
    }

    public function update(Request $request, User $pegawai)
    {
        $input = $request->all();

        $validator = Validator::make(
            $input,
            PegawaiService::aturan($input, $pegawai) + ['status' => ['required', Rule::in(PegawaiService::STATUS)]],
            PegawaiService::pesan(),
            PegawaiService::atribut()
        );

        $validator->after(function ($v) use ($input, $pegawai, $request) {
            $this->cekJamKerja($v, $input);

            // Administrator tidak boleh mengunci dirinya sendiri
            if ($pegawai->is($request->user())) {
                if (($input['status'] ?? null) !== 'Aktif') {
                    $v->errors()->add('status', 'Anda tidak dapat menonaktifkan akun sendiri.');
                }
                if (! in_array(Role::ADMIN, (array) ($input['peran'] ?? []), true)) {
                    $v->errors()->add('peran', 'Anda tidak dapat melepas peran Administrator dari akun sendiri.');
                }
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal', 'edit');
        }

        PegawaiService::simpan($validator->validated(), $pegawai);

        // Pegawai yang dinonaktifkan/keluar langsung diputus sesinya
        if ($pegawai->status !== 'Aktif') {
            DB::table('sessions')->where('user_id', $pegawai->id)->delete();
        }

        return back()->with('sukses', "Data {$pegawai->nama_lengkap} diperbarui.");
    }

    public function resetSandi(Request $request, User $pegawai)
    {
        // Administrator mengetik sendiri kata sandi baru, lalu menyampaikannya kepada pegawai
        $validator = Validator::make($request->all(), [
            'kata_sandi_baru' => ['required', 'string', 'min:8', 'max:64'],
        ], [], ['kata_sandi_baru' => 'Kata sandi baru']);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput(['_id' => $pegawai->id])->with('modal', 'edit');
        }

        $pegawai->update(['password' => $validator->validated()['kata_sandi_baru']]);

        if (! $pegawai->is($request->user())) {
            DB::table('sessions')->where('user_id', $pegawai->id)->delete();
        }

        return back()->with('sukses', "Kata sandi {$pegawai->nama_lengkap} berhasil diganti. Sampaikan kata sandi baru kepada pegawai yang bersangkutan.");
    }

    public function destroy(Request $request, User $pegawai)
    {
        if ($pegawai->status !== 'Keluar') {
            return back()->with('galat', 'Hanya pegawai berstatus Keluar yang dapat dihapus.');
        }
        if ($pegawai->is($request->user())) {
            return back()->with('galat', 'Anda tidak dapat menghapus akun sendiri.');
        }
        if (! $request->boolean('paham')) {
            return back()->with('galat', 'Centang pernyataan konfirmasi sebelum menghapus.');
        }

        $nama = $pegawai->nama_lengkap;

        DB::transaction(function () use ($pegawai) {
            // Dihapus permanen: data & foto wajah, akun login, NIP, email
            foreach ($pegawai->faceDescriptors as $wajah) {
                Storage::delete($wajah->foto_path);
            }
            if ($pegawai->ttd_path) {
                Storage::delete($pegawai->ttd_path);
            }

            $pegawai->faceDescriptors()->delete();
            $pegawai->roles()->detach();
            $pegawai->forceFill([
                'nip' => null, 'email' => null, 'password' => null, 'ttd_path' => null,
                'persetujuan_wajah_at' => null, 'remember_token' => null,
            ])->save();

            // Tetap disimpan: nama & riwayat presensi (soft delete -> deleted_at)
            $pegawai->delete();
        });

        DB::table('sessions')->where('user_id', $pegawai->id)->delete();

        return back()->with('sukses', "Data pegawai {$nama} dihapus. Nama dan riwayat presensinya tetap tersimpan sebagai arsip.");
    }

    private function cekJamKerja($validator, array $input): void
    {
        if ($validator->errors()->hasAny(['unit_id', 'kategori'])) {
            return;
        }

        if ($pesan = PegawaiService::cekJamKerja($input)) {
            $validator->errors()->add('kategori', $pesan);
        }
    }
}