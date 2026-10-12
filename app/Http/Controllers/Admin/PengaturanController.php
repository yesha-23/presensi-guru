<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JamKerja;
use App\Models\Lokasi;
use App\Models\Pengaturan;
use Illuminate\Http\Request;

class PengaturanController extends Controller
{
    public function index()
    {
        $nilai = fn (string $kunci, $bawaan = null) => Pengaturan::ambil($kunci, $bawaan);

        return view('admin.pengaturan', [
            'judul'    => 'Pengaturan',
            'jamKerja' => JamKerja::with('unit')->orderBy('unit_id')->orderBy('kategori')->get(),
            'lokasi'   => Lokasi::first(),
            'nilai'    => [
                'ambang_wajah'          => $nilai('ambang_wajah', '0.5'),
                'akurasi_gps_maks'      => $nilai('akurasi_gps_maks', '150'),
                'jam_pengingat_datang'  => substr((string) $nilai('jam_pengingat_datang', '06:45'), 0, 5),
                'batas_presensi_datang' => substr((string) $nilai('batas_presensi_datang', '12:00'), 0, 5),
                'hari_kerja'            => array_map('intval', explode(',', (string) $nilai('hari_kerja', '1,2,3,4,5'))),
                'alamat_yayasan'        => $nilai('alamat_yayasan', ''),
                'kota_surat'            => $nilai('kota_surat', 'Sleman'),
                'telepon_surat'         => $nilai('telepon_surat', ''),
                'web_surat'             => $nilai('web_surat', ''),
            ],
        ]);
    }

    /** SR-F07: jam masuk, jam pulang, toleransi per unit & kategori. */
    public function jamKerja(Request $request)
    {
        // Input jam dinormalkan ke HH:MM
        $request->merge(['jam' => collect($request->input('jam', []))->map(fn ($j) => [
            'jam_masuk'       => substr((string) ($j['jam_masuk'] ?? ''), 0, 5),
            'jam_pulang'      => substr((string) ($j['jam_pulang'] ?? ''), 0, 5),
            'toleransi_menit' => $j['toleransi_menit'] ?? null,
        ])->all()]);

        $data = $request->validate([
            'jam'                   => ['required', 'array'],
            'jam.*.jam_masuk'       => ['required', 'date_format:H:i'],
            'jam.*.jam_pulang'      => ['required', 'date_format:H:i'],
            'jam.*.toleransi_menit' => ['required', 'integer', 'min:0', 'max:120'],
        ], [
            'date_format' => 'Format jam tidak valid.',
            'required'    => 'Wajib diisi.',
            'max'         => 'Toleransi maksimal 120 menit.',
        ]);

        foreach ($data['jam'] as $id => $j) {
            if ($j['jam_pulang'] <= $j['jam_masuk']) {
                return back()->withErrors(["jam.{$id}.jam_pulang" => 'Jam pulang harus setelah jam masuk.'])->withInput();
            }
        }

        foreach ($data['jam'] as $id => $j) {
            JamKerja::whereKey($id)->update($j);
        }

        return back()->with('sukses', 'Jam kerja disimpan. Berlaku untuk presensi berikutnya; presensi yang sudah tercatat tidak berubah.');
    }

    /** SR-F08: titik lokasi kompleks & radius presensi. */
    public function lokasi(Request $request)
    {
        $data = $request->validate([
            'nama'         => ['required', 'string', 'max:100'],
            'latitude'     => ['required', 'numeric', 'between:-90,90'],
            'longitude'    => ['required', 'numeric', 'between:-180,180'],
            'radius_meter' => ['required', 'integer', 'min:20', 'max:1000'],
        ], [
            'required' => ':attribute wajib diisi.',
            'between'  => ':attribute tidak valid.',
            'min'      => 'Radius minimal 20 meter.',
            'max'      => 'Radius maksimal 1000 meter.',
        ], ['nama' => 'Nama lokasi', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'radius_meter' => 'Radius']);

        $lokasi = Lokasi::first();
        $lokasi ? $lokasi->update($data) : Lokasi::create($data);

        return back()->with('sukses', 'Lokasi presensi disimpan.');
    }

    /** Verifikasi wajah, GPS, pengingat, batas datang, hari kerja. */
    public function presensi(Request $request)
    {
        $data = $request->validate([
            'ambang_wajah'          => ['required', 'numeric', 'min:0.3', 'max:0.7'],
            'akurasi_gps_maks'      => ['required', 'integer', 'min:10', 'max:1000'],
            'jam_pengingat_datang'  => ['required', 'date_format:H:i'],
            'batas_presensi_datang' => ['required', 'date_format:H:i'],
            'hari_kerja'            => ['required', 'array', 'min:1'],
            'hari_kerja.*'          => ['integer', 'between:1,7'],
        ], [
            'ambang_wajah.min'    => 'Ambang wajah minimal 0,30.',
            'ambang_wajah.max'    => 'Ambang wajah maksimal 0,70.',
            'hari_kerja.required' => 'Pilih minimal satu hari kerja.',
            'date_format'         => 'Format jam tidak valid.',
        ]);

        foreach (['ambang_wajah', 'akurasi_gps_maks', 'jam_pengingat_datang', 'batas_presensi_datang'] as $kunci) {
            Pengaturan::updateOrCreate(['kunci' => $kunci], ['nilai' => (string) $data[$kunci]]);
        }
        Pengaturan::updateOrCreate(['kunci' => 'hari_kerja'], [
            'nilai' => collect($data['hari_kerja'])->map(fn ($h) => (int) $h)->unique()->sort()->implode(','),
        ]);

        return back()->with('sukses', 'Pengaturan presensi disimpan.');
    }

    /** Data kop surat tugas. */
    public function kop(Request $request)
    {
        $data = $request->validate([
            'alamat_yayasan' => ['required', 'string', 'max:300'],
            'kota_surat'     => ['required', 'string', 'max:50'],
            'telepon_surat'  => ['nullable', 'string', 'max:150'],
            'web_surat'      => ['nullable', 'string', 'max:150'],
        ]);

        foreach ($data as $kunci => $isi) {
            Pengaturan::updateOrCreate(['kunci' => $kunci], ['nilai' => (string) $isi]);
        }

        return back()->with('sukses', 'Data kop surat disimpan. Berlaku untuk surat tugas yang disetujui berikutnya.');
    }
}