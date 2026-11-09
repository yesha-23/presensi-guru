<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JamKerja;
use App\Models\JamKerjaKhusus;
use App\Models\Lokasi;
use App\Models\Pengaturan;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\User;
use App\Services\PersetujuanService;
use App\Services\RekapService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengaturanController extends Controller
{
    public function index()
    {
        $nilai = fn (string $kunci, $bawaan = null) => Pengaturan::ambil($kunci, $bawaan);

        return view('admin.pengaturan', [
            'judul'    => 'Pengaturan',
            'jamKerja' => JamKerja::with('unit')->orderBy('unit_id')->orderBy('kategori')->get(),
            // Jam kerja khusus per pegawai, urut nama
            'jamKhusus' => JamKerjaKhusus::with('user.unit')->get()
                ->sortBy(fn ($j) => $j->user?->nama_lengkap)->values(),
            'pilihanPegawai' => $this->pegawaiPresensi()->with('unit')->orderBy('nama_lengkap')
                ->get(['id', 'nama_lengkap', 'unit_id', 'kategori']),
            'lokasi'   => Lokasi::first(),
            // Administrator yang dapat ditunjuk menyetujui pengajuan kepala sekolah
            'daftarAdmin'   => $this->administrator()->with('unit')->orderBy('nama_lengkap')->get(),
            'idPenyetujuKs' => PersetujuanService::idPenyetujuKs(),
            // Pegawai unit Yayasan (mis. Kabag Adm. Umum) yang dapat ditampilkan di rekap SD / KB-TK
            'pegawaiYayasan'  => $this->pegawaiPresensi()->whereHas('unit', fn ($q) => $q->where('kode', 'YYS'))
                ->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'kategori', 'jabatan']),
            'tampilanYayasan' => RekapService::tampilanYayasan(),
            'nilai'    => [
                'ambang_wajah'          => $nilai('ambang_wajah', '0.5'),
                'akurasi_gps_maks'      => $nilai('akurasi_gps_maks', '150'),
                'jam_pengingat_datang'  => substr((string) $nilai('jam_pengingat_datang', '06:45'), 0, 5),
                'batas_presensi_datang' => substr((string) $nilai('batas_presensi_datang', '12:00'), 0, 5),
                'mode_lewat_batas'      => $nilai('mode_lewat_batas', Presensi::MODE_CATAT),
                'hari_kerja'            => array_map('intval', explode(',', (string) $nilai('hari_kerja', '1,2,3,4,5'))),
                'alamat_yayasan'        => $nilai('alamat_yayasan', ''),
                'kota_surat'            => $nilai('kota_surat', 'Sleman'),
                'telepon_surat'         => $nilai('telepon_surat', ''),
                'web_surat'             => $nilai('web_surat', ''),
                'awal_periode_rekap_SD'   => (int) $nilai('awal_periode_rekap_SD', 1),
                'awal_periode_rekap_KBTK' => (int) $nilai('awal_periode_rekap_KBTK', 26),
                'pembulatan_jam_rekap'    => $nilai('pembulatan_jam_rekap', RekapService::BULAT_SETENGAH),
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

    /** Tambah atau ubah jam kerja khusus satu pegawai (satu pegawai = satu baris). */
    public function simpanJamKhusus(Request $request)
    {
        $request->merge([
            'jam_masuk'  => substr((string) $request->input('jam_masuk'), 0, 5),
            'jam_pulang' => substr((string) $request->input('jam_pulang'), 0, 5),
        ]);

        $data = $request->validateWithBag('jamKhusus', [
            'user_id'    => ['required', Rule::in($this->pegawaiPresensi()->pluck('id'))],
            'jam_masuk'  => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i', 'after:jam_masuk'],
            'keterangan' => ['nullable', 'string', 'max:150'],
        ], [
            'user_id.required' => 'Pilih pegawai.',
            'user_id.in'       => 'Pegawai tidak valid atau tidak wajib presensi.',
            'required'         => ':attribute wajib diisi.',
            'date_format'      => 'Format :attribute tidak valid.',
            'jam_pulang.after' => 'Jam pulang harus setelah jam masuk.',
            'max'              => ':attribute maksimal :max karakter.',
        ], ['jam_masuk' => 'Jam masuk', 'jam_pulang' => 'Jam pulang', 'keterangan' => 'Keterangan']);

        $baru = ! JamKerjaKhusus::where('user_id', $data['user_id'])->exists();
        $jam = JamKerjaKhusus::updateOrCreate(['user_id' => $data['user_id']], $data);

        return back()->with('sukses', ($baru ? 'Jam kerja khusus ditambahkan' : 'Jam kerja khusus diperbarui')
            ." untuk {$jam->user->nama_lengkap} ({$data['jam_masuk']}–{$data['jam_pulang']}). Berlaku untuk presensi berikutnya.");
    }

    /** Hapus jam kerja khusus: pegawai kembali memakai jam unit & kategorinya. */
    public function hapusJamKhusus(JamKerjaKhusus $jamKhusus)
    {
        $nama = $jamKhusus->user?->nama_lengkap ?? 'pegawai';
        $jamKhusus->delete();

        return back()->with('sukses', "Jam kerja khusus {$nama} dihapus. Pegawai kembali memakai jam kerja unit dan kategorinya.");
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
            'mode_lewat_batas'      => ['required', Rule::in([Presensi::MODE_CATAT, Presensi::MODE_TOLAK])],
            'hari_kerja'            => ['required', 'array', 'min:1'],
            'hari_kerja.*'          => ['integer', 'between:1,7'],
        ], [
            'ambang_wajah.min'    => 'Ambang wajah minimal 0,30.',
            'ambang_wajah.max'    => 'Ambang wajah maksimal 0,70.',
            'hari_kerja.required' => 'Pilih minimal satu hari kerja.',
            'date_format'         => 'Format jam tidak valid.',
        ]);

        foreach (['ambang_wajah', 'akurasi_gps_maks', 'jam_pengingat_datang', 'batas_presensi_datang', 'mode_lewat_batas'] as $kunci) {
            Pengaturan::updateOrCreate(['kunci' => $kunci], ['nilai' => (string) $data[$kunci]]);
        }
        Pengaturan::updateOrCreate(['kunci' => 'hari_kerja'], [
            'nilai' => collect($data['hari_kerja'])->map(fn ($h) => (int) $h)->unique()->sort()->implode(','),
        ]);

        return back()->with('sukses', 'Pengaturan presensi disimpan.');
    }

    /**
     * Administrator yang ditunjuk menyetujui izin/sakit/lembur milik kepala sekolah
     * (selain Ketua Yayasan), misalnya Kabag Adm. Umum. Admin lain (mis. admin kedua) tidak ikut menyetujui.
     */
    public function penyetuju(Request $request)
    {
        $data = $request->validate([
            'penyetuju_ks'   => ['nullable', 'array'],
            'penyetuju_ks.*' => ['integer', Rule::in($this->administrator()->pluck('id'))],
        ], ['penyetuju_ks.*.in' => 'Penyetuju harus pegawai aktif dengan peran Administrator.']);

        $id = collect($data['penyetuju_ks'] ?? [])->map(fn ($i) => (int) $i)->unique()->sort()->values();

        Pengaturan::updateOrCreate(['kunci' => 'penyetuju_pengajuan_ks'], ['nilai' => $id->implode(',')]);

        $nama = User::whereIn('id', $id)->orderBy('nama_lengkap')->pluck('nama_lengkap');

        return back()->with('sukses', 'Penyetuju pengajuan kepala sekolah disimpan: Ketua Yayasan'
            .($nama->isNotEmpty() ? ', '.$nama->join(', ') : ' saja').'.');
    }

    /** Periode bulanan per unit, pembulatan jam, dan pegawai Yayasan di rekap unit. */
    public function rekap(Request $request)
    {
        $kelompok = ['', 'Guru', 'Staf'];
        $idYayasan = $this->pegawaiPresensi()->whereHas('unit', fn ($q) => $q->where('kode', 'YYS'))->pluck('id');

        $data = $request->validate([
            'awal_periode_rekap_SD'   => ['required', 'integer', 'between:1,28'],
            'awal_periode_rekap_KBTK' => ['required', 'integer', 'between:1,28'],
            'pembulatan_jam_rekap'    => ['required', Rule::in([RekapService::BULAT_SETENGAH, RekapService::BULAT_SETENGAH_ATAS, RekapService::BULAT_DESIMAL])],
            'yayasan'                 => ['nullable', 'array'],
            'yayasan.*.SD'            => ['nullable', Rule::in($kelompok)],
            'yayasan.*.KBTK'          => ['nullable', Rule::in($kelompok)],
        ], [
            'between' => 'Tanggal awal periode harus antara 1 dan 28.',
        ]);

        foreach (['awal_periode_rekap_SD', 'awal_periode_rekap_KBTK', 'pembulatan_jam_rekap'] as $kunci) {
            Pengaturan::updateOrCreate(['kunci' => $kunci], ['nilai' => (string) $data[$kunci]]);
        }

        // Hanya pegawai Yayasan yang valid, dan hanya yang tampil di minimal satu rekap unit
        $tampilan = collect($data['yayasan'] ?? [])
            ->filter(fn ($a, $id) => $idYayasan->contains((int) $id))
            ->map(fn ($a) => ['SD' => $a['SD'] ?? '', 'KBTK' => $a['KBTK'] ?? ''])
            ->filter(fn ($a) => $a['SD'] !== '' || $a['KBTK'] !== '');

        Pengaturan::updateOrCreate(['kunci' => 'rekap_pegawai_yayasan'], ['nilai' => $tampilan->toJson()]);

        return back()->with('sukses', 'Pengaturan rekap disimpan. Berlaku untuk tampilan dan unduhan rekap berikutnya.');
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

    /** Pegawai aktif dengan peran Administrator. */
    private function administrator()
    {
        return User::where('status', 'Aktif')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::ADMIN));
    }

    /** Pegawai aktif yang wajib presensi (punya kategori & peran Pegawai). */
    private function pegawaiPresensi()
    {
        return User::where('status', 'Aktif')
            ->whereNotNull('kategori')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::PEGAWAI));
    }
}