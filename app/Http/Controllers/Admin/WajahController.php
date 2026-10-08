<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\User;
use App\Services\FaceMatcherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WajahController extends Controller
{
    /** Batas jarak antarfoto milik orang yang sama (nilai umum face-api.js). */
    private const BATAS_ORANG_SAMA = 0.6;

    public function create(User $pegawai)
    {
        if ($galat = $this->cekBoleh($pegawai)) {
            return redirect()->route('admin.pegawai')->with('galat', $galat);
        }

        $pegawai->load('unit')->loadCount('faceDescriptors');

        return view('admin.pegawai.wajah', [
            'judul'   => 'Pendaftaran Data Wajah',
            'pegawai' => $pegawai,
        ]);
    }

    public function store(Request $request, User $pegawai, FaceMatcherService $matcher)
    {
        if ($galat = $this->cekBoleh($pegawai)) {
            return response()->json(['message' => $galat], 422);
        }

        $request->validate([
            'persetujuan'          => ['accepted'],
            'foto'                 => ['required', 'array', 'size:3'],
            'foto.*.descriptor'    => ['required', 'array', 'size:128'],
            'foto.*.descriptor.*'  => ['numeric'],
            'foto.*.gambar'        => ['required', 'string', 'starts_with:data:image/jpeg;base64,', 'max:700000'],
        ], [
            'persetujuan.accepted' => 'Persetujuan pemrosesan data biometrik wajib dicentang.',
            'foto.required'        => 'Diperlukan 3 foto wajah.',
            'foto.size'            => 'Diperlukan tepat 3 foto wajah.',
            '*'                    => 'Data foto tidak valid. Ulangi pengambilan foto.',
        ]);

        $foto = $request->input('foto');
        $descriptors = array_map(fn ($f) => array_map('floatval', $f['descriptor']), $foto);

        // 1. Ketiga foto harus milik orang yang sama
        for ($i = 0; $i < 3; $i++) {
            for ($j = $i + 1; $j < 3; $j++) {
                if (FaceMatcherService::jarak($descriptors[$i], $descriptors[$j]) > self::BATAS_ORANG_SAMA) {
                    return response()->json([
                        'message' => 'Foto '.($i + 1).' dan foto '.($j + 1).' tidak tampak sebagai orang yang sama. Ulangi foto.',
                    ], 422);
                }
            }
        }

        // 2. Wajah tidak boleh mirip pegawai lain (mencegah salah daftar / titip presensi)
        $ambang = (float) Pengaturan::ambil('ambang_wajah', 0.5);

        foreach ($descriptors as $descriptor) {
            $cocok = $matcher->cocokkan($descriptor, $pegawai->id);

            if ($cocok && $cocok['jarak'] < $ambang) {
                $nama = User::find($cocok['user_id'])?->nama_lengkap;

                return response()->json([
                    'message' => "Wajah ini sangat mirip dengan data wajah {$nama}. Pastikan yang difoto adalah {$pegawai->nama_lengkap}.",
                ], 422);
            }
        }

        // 3. Ubah gambar base64 menjadi file
        $gambar = [];
        foreach ($foto as $f) {
            $isi = base64_decode(substr($f['gambar'], strpos($f['gambar'], ',') + 1), true);

            if ($isi === false || @getimagesizefromstring($isi) === false) {
                return response()->json(['message' => 'File foto rusak. Ulangi pengambilan foto.'], 422);
            }
            $gambar[] = $isi;
        }

        // 4. Simpan (ambil ulang = ganti data lama)
        $fotoLama = $pegawai->faceDescriptors()->pluck('foto_path')->all();

        DB::transaction(function () use ($pegawai, $gambar, $descriptors) {
            $pegawai->faceDescriptors()->delete();

            foreach ($gambar as $i => $isi) {
                $path = "wajah/{$pegawai->id}/".Str::uuid().'.jpg';
                Storage::put($path, $isi); // storage/app/private, tidak bisa diakses publik

                $pegawai->faceDescriptors()->create([
                    'descriptor' => $descriptors[$i],
                    'foto_path'  => $path,
                ]);
            }

            $pegawai->update(['persetujuan_wajah_at' => now()]);
        });

        Storage::delete($fotoLama);

        session()->flash('sukses', "Data wajah {$pegawai->nama_lengkap} tersimpan (3 foto referensi).");

        return response()->json(['redirect' => route('admin.pegawai')]);
    }

    private function cekBoleh(User $pegawai): ?string
    {
        if (! $pegawai->hasRole(Role::PEGAWAI)) {
            return 'Akun ini tidak melakukan presensi, sehingga tidak memerlukan data wajah.';
        }
        if ($pegawai->status === 'Keluar') {
            return 'Pegawai berstatus Keluar tidak dapat didaftarkan wajahnya.';
        }

        return null;
    }
}