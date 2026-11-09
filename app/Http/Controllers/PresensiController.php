<?php

namespace App\Http\Controllers;

use App\Models\LogKoreksi;
use App\Models\Lokasi;
use App\Models\Pengaturan;
use App\Models\Presensi;
use App\Models\User;
use App\Services\FaceMatcherService;
use App\Services\JadwalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LemburService;

class PresensiController extends Controller
{
    private const KOLOM_DATANG = [
        'status_harian', 'jam_datang', 'status_datang', 'detik_terlambat',
        'foto_datang', 'lat_datang', 'lng_datang', 'jarak_datang', 'skor_wajah_datang',
    ];

    private const KOLOM_PULANG = [
        'jam_pulang', 'pulang_cepat', 'detik_pulang_cepat',
        'foto_pulang', 'lat_pulang', 'lng_pulang', 'jarak_pulang', 'skor_wajah_pulang',
    ];

    public function index()
    {
        return view('presensi.scan', [
            'judul'  => 'Presensi',
            'lokasi' => Lokasi::first(),
        ]);
    }

    /** Proses scan: wajah 1:N -> lokasi -> kondisi khusus -> datang/pulang. */
    public function scan(Request $request, FaceMatcherService $matcher, JadwalService $jadwal): JsonResponse
    {
        $data = $request->validate([
            'descriptor'   => ['required', 'array', 'size:128'],
            'descriptor.*' => ['numeric'],
            'lat'          => ['required', 'numeric', 'between:-90,90'],
            'lng'          => ['required', 'numeric', 'between:-180,180'],
            'akurasi'      => ['required', 'numeric', 'min:0'],
            'foto'         => ['required', 'string', 'starts_with:data:image/jpeg;base64,', 'max:500000'],
        ], ['*' => 'Data scan tidak lengkap. Muat ulang halaman lalu coba lagi.']);

        $waktu = now()->startOfSecond(); // waktu server WIB (SR-F11)

        $lokasi = Lokasi::first();
        if (! $lokasi) {
            return $this->gagal('Lokasi sekolah belum diatur', '-', 'Hubungi administrator', $waktu, null);
        }

        $jarak = $lokasi->jarakKe((float) $data['lat'], (float) $data['lng']);
        $infoJarak = ['jarak' => (int) round($jarak), 'radius' => $lokasi->radius_meter];

        // 1. Cocokkan wajah 1:N di server (SR-F09)
        $cocok = $matcher->cocokkan(array_map('floatval', $data['descriptor']));
        $ambang = (float) Pengaturan::ambil('ambang_wajah', 0.5);
        $user = ($cocok && $cocok['jarak'] <= $ambang) ? User::with('roles')->find($cocok['user_id']) : null;

        if (! $user || ! $user->wajibPresensi()) {
            return $this->gagal('Wajah tidak dikenali', 'Tidak dikenali', 'Wajah tidak cocok', $waktu, $infoJarak);
        }

        // 2. Validasi lokasi: akurasi GPS (SR-NF06) & radius (SR-F10)
        $akurasiMaks = (int) Pengaturan::ambil('akurasi_gps_maks', 150);
        if ((float) $data['akurasi'] > $akurasiMaks) {
            return $this->gagal('Akurasi GPS kurang baik', 'Dikenali', 'Akurasi ±'.round($data['akurasi']).' m', $waktu, $infoJarak);
        }
        if ($jarak > $lokasi->radius_meter) {
            return $this->gagal('Anda berada di luar radius sekolah', 'Dikenali', 'Di luar radius', $waktu, $infoJarak);
        }

        // 3. Libur / izin / sakit / tugas khusus?
        if ($khusus = $jadwal->kondisiKhusus($user, $waktu)) {
            return response()->json([
                'hasil' => 'info',
                'judul' => 'Tidak perlu presensi',
                'nama'  => $user->nama_lengkap,
                'pesan' => $khusus['keterangan'],
            ]);
        }

        $presensi = Presensi::firstOrNew(['user_id' => $user->id, 'tanggal' => $waktu->toDateString()]);

        // 4. Sudah lengkap?
        if ($presensi->jam_pulang) {
            return response()->json([
                'hasil'  => 'info',
                'judul'  => 'Presensi hari ini sudah lengkap',
                'nama'   => $user->nama_lengkap,
                'pesan'  => 'Anda sudah melakukan presensi datang dan pulang hari ini.',
                'datang' => $presensi->jam_datang,
                'pulang' => $presensi->jam_pulang,
            ]);
        }

        $j = $jadwal->jadwal($user, $waktu);

        // Presensi datang setelah batas (mis. 12.00): dicatat "Lewat Batas" atau ditolak, sesuai Pengaturan.
        // Presensi datang setelah jam kerja berakhir (jadwal pulang) selalu ditolak.
        if (! $presensi->jam_datang && $waktu->lt($j['pulang'])
            && Presensi::tolakLewatBatas() && Presensi::lewatBatas($waktu, $j)) {
            return response()->json([
                'hasil' => 'info',
                'judul' => 'Presensi datang sudah ditutup',
                'nama'  => $user->nama_lengkap,
                'pesan' => 'Batas presensi datang hari ini pukul '.Presensi::batasDatang($j)->format('H.i').' sudah lewat. '
                    .'Hari ini akan tercatat Alpa, kecuali Anda memiliki izin, sakit, atau tugas khusus yang disetujui. '
                    .'Jika Anda sebenarnya hadir tetapi gagal presensi, hubungi administrator untuk koreksi.',
            ]);
        }

        if (! $presensi->jam_datang && $waktu->gte($j['pulang'])) {
            return response()->json([
                'hasil' => 'info',
                'judul' => 'Jam kerja hari ini sudah berakhir',
                'nama'  => $user->nama_lengkap,
                'pesan' => 'Presensi datang hanya dapat dilakukan sebelum jadwal pulang ('.$j['pulang']->format('H.i').'). '
                    .'Hari ini akan tercatat Alpa, kecuali Anda memiliki izin, sakit, atau tugas khusus yang disetujui. '
                    .'Jika Anda sebenarnya hadir tetapi gagal presensi, hubungi Kepala Bagian Administrasi Umum untuk koreksi.',
            ]);
        }

        $bukti = [
            'lat'   => $data['lat'],
            'lng'   => $data['lng'],
            'jarak' => $jarak,
            'skor'  => $cocok['jarak'],
            'foto'  => $this->simpanFoto($data['foto']),
        ];

        // 5. Sudah datang? -> jenis presensi = pulang (SR-F13)
        if ($presensi->jam_datang) {
            if ($waktu->lt($j['pulang'])) {
                // Pulang sebelum jadwal: minta konfirmasi dulu, belum disimpan
                session(['presensi_tertunda' => [
                    'user_id'    => $user->id,
                    'bukti'      => $bukti,
                    'info'       => $infoJarak,
                    'kedaluwarsa' => now()->addMinutes(2)->timestamp,
                ]]);

                return response()->json([
                    'hasil'         => 'konfirmasi_pulang',
                    'nama'          => $user->nama_lengkap,
                    'waktu'         => $waktu->format('H:i:s'),
                    'jadwal_pulang' => $j['pulang']->format('H:i:s'),
                    'lebih_awal'    => Presensi::formatDetik($j['pulang']->timestamp - $waktu->timestamp),
                ] + $infoJarak);
            }

            return $this->simpanPulang($presensi, $user, $waktu, $j, $bukti, $infoJarak);
        }

        return $this->simpanDatang($presensi, $user, $waktu, $j, $bukti, $infoJarak);
    }

    /** "Ya, pulang" pada popup pulang lebih awal. */
    public function konfirmasiPulang(JadwalService $jadwal): JsonResponse
    {
        $tertunda = session()->pull('presensi_tertunda');

        if (! $tertunda || $tertunda['kedaluwarsa'] < now()->timestamp) {
            return $this->gagal('Waktu konfirmasi habis', '-', 'Silakan scan ulang', now(), null);
        }

        $user = User::find($tertunda['user_id']);
        $waktu = now()->startOfSecond();
        $j = $user ? $jadwal->jadwal($user, $waktu) : null;
        $presensi = $user ? Presensi::where('user_id', $user->id)->whereDate('tanggal', $waktu)->first() : null;

        if (! $j || ! $presensi?->jam_datang || $presensi->jam_pulang) {
            return $this->gagal('Kondisi presensi berubah', 'Dikenali', 'Silakan scan ulang', $waktu, $tertunda['info']);
        }

        return $this->simpanPulang($presensi, $user, $waktu, $j, $tertunda['bukti'], $tertunda['info']);
    }

    /** "Batal" pada popup pulang lebih awal. */
    public function batalPulang(): JsonResponse
    {
        $tertunda = session()->pull('presensi_tertunda');

        if ($tertunda['bukti']['foto'] ?? null) {
            Storage::delete($tertunda['bukti']['foto']);
        }

        return response()->json(['hasil' => 'dibatalkan']);
    }

    /** Tombol "Bukan saya": batalkan presensi yang baru tersimpan & catat di log. */
    public function bukanSaya(): JsonResponse
    {
        $terakhir = session()->pull('presensi_terakhir');
        $presensi = $terakhir ? Presensi::find($terakhir['presensi_id']) : null;

        if (! $presensi || $terakhir['kedaluwarsa'] < now()->timestamp) {
            return $this->gagal('Waktu pembatalan sudah habis', '-', 'Hubungi administrator untuk koreksi', now(), null);
        }

        $datang = $terakhir['jenis'] === 'datang';
        $kolom = $datang ? self::KOLOM_DATANG : self::KOLOM_PULANG;
        $kosong = array_fill_keys($kolom, null);

        if ($datang) {
            $kosong['detik_terlambat'] = 0;
        } else {
            $kosong['pulang_cepat'] = false;
            $kosong['detik_pulang_cepat'] = 0;
        }

        $lama = $presensi->only($kolom);

        DB::transaction(function () use ($presensi, $kosong, $lama) {
            $presensi->fill($kosong + ['dibatalkan_at' => now()])->save();

            LogKoreksi::create([
                'user_id'            => $presensi->user_id,
                'target_type'        => Presensi::class,
                'target_id'          => $presensi->id,
                'nilai_lama'         => $lama,
                'nilai_baru'         => $kosong,
                'alasan'             => 'Dibatalkan lewat tombol "Bukan saya" pada popup hasil presensi.',
                'status_persetujuan' => 'Tidak Perlu',
            ]);
        });

        // Presensi pulang dibatalkan: durasi lembur ikut dihitung ulang
        if (! $datang) {
            app(LemburService::class)->perbaruiUntuk($presensi->user_id, $presensi->tanggal->copy());
        }

        return response()->json(['hasil' => 'dibatalkan']);
    }

    /** Hitung mundur habis / "Masuk sekarang": login otomatis, sesi 5 menit. */
    public function masuk(Request $request)
    {
        $terakhir = session()->pull('presensi_terakhir');
        $user = $terakhir ? User::find($terakhir['user_id']) : null;

        if (! $user || $user->status !== 'Aktif' || $terakhir['kedaluwarsa'] < now()->timestamp) {
            return redirect()->route('beranda')
                ->with('info', 'Sesi presensi berakhir. Gunakan tombol Lihat Dashboard untuk masuk.');
        }

        Auth::login($user); // tanpa "remember me"
        $request->session()->regenerate();
        $request->session()->put([
            'jenis_login'    => 'otomatis',
            'terakhir_aktif' => now()->timestamp,
        ]);
        config(['session.expire_on_close' => true]); // tidak diingat browser (10.1)

        return redirect()->route($user->rutePeran());
    }

    // ================= Helper =================

    private function simpanDatang(Presensi $presensi, User $user, Carbon $waktu, array $j, array $bukti, array $infoJarak): JsonResponse
    {
        // Tepat Waktu / Terlambat / Lewat Batas (aturan di model Presensi)
        $hitung = Presensi::hitungDatang($waktu, $j);
        $status = $hitung['status_datang'];
        $lewatBatas = $status === Presensi::LEWAT_BATAS;
        $terlambat = $status !== 'Tepat Waktu';

        $presensi->fill([
            'status_harian'     => 'Hadir',
            'jam_datang'        => $waktu->format('H:i:s'),
            'status_datang'     => $status,
            'detik_terlambat'   => $hitung['detik_terlambat'],
            'foto_datang'       => $bukti['foto'],
            'lat_datang'        => $bukti['lat'],
            'lng_datang'        => $bukti['lng'],
            'jarak_datang'      => $bukti['jarak'],
            'skor_wajah_datang' => $bukti['skor'],
        ])->save();

        return $this->berhasil($presensi, $user, 'datang', [
            'judul'       => $terlambat ? 'Presensi Datang Tercatat' : 'Presensi Datang Berhasil',
            'jenis'       => 'Datang',
            'waktu'       => $waktu->format('H:i:s'),
            'status'      => $status,
            'peringatan'  => $terlambat,
            'lewat_batas' => $lewatBatas,
            'keterangan'  => Presensi::formatDetik($hitung['detik_terlambat']),
            'catatan'     => $lewatBatas
                ? 'Presensi melewati batas pukul '.Presensi::batasDatang($j)->format('H.i')
                    .'. Kehadiran tetap tercatat dengan label Lewat Batas dan terlihat di rekap.'
                : null,
        ] + $infoJarak);
    }

    private function simpanPulang(Presensi $presensi, User $user, Carbon $waktu, array $j, array $bukti, array $infoJarak): JsonResponse
    {
        $cepat = $waktu->lt($j['pulang']);
        $detik = $cepat ? $j['pulang']->timestamp - $waktu->timestamp : 0;

        $presensi->fill([
            'jam_pulang'         => $waktu->format('H:i:s'),
            'pulang_cepat'       => $cepat,
            'detik_pulang_cepat' => $detik,
            'foto_pulang'        => $bukti['foto'],
            'lat_pulang'         => $bukti['lat'],
            'lng_pulang'         => $bukti['lng'],
            'jarak_pulang'       => $bukti['jarak'],
            'skor_wajah_pulang'  => $bukti['skor'],
        ])->save();

        // SR-F22: setelah jam pulang tersimpan, hitung durasi lembur yang disetujui hari ini
        app(LemburService::class)->perbaruiUntuk($user->id, $waktu);

        return $this->berhasil($presensi, $user, 'pulang', [
            'judul'      => $cepat ? 'Presensi Pulang Tercatat' : 'Presensi Pulang Berhasil',
            'jenis'      => 'Pulang',
            'waktu'      => $waktu->format('H:i:s'),
            'status'     => $cepat ? 'Pulang Cepat' : 'Tepat Waktu',
            'peringatan' => $cepat,
            'keterangan' => Presensi::formatDetik($detik),
        ] + $infoJarak);
    }

    private function berhasil(Presensi $presensi, User $user, string $jenis, array $info): JsonResponse
    {
        // Dipakai tombol "Bukan saya" dan login otomatis, berlaku 2 menit di perangkat ini
        session(['presensi_terakhir' => [
            'presensi_id' => $presensi->id,
            'user_id'     => $user->id,
            'jenis'       => $jenis,
            'kedaluwarsa' => now()->addMinutes(2)->timestamp,
        ]]);

        return response()->json(['hasil' => 'berhasil', 'nama' => $user->nama_lengkap] + $info);
    }

    private function gagal(string $alasan, string $wajah, string $keterangan, Carbon $waktu, ?array $infoJarak): JsonResponse
    {
        return response()->json([
            'hasil'      => 'gagal',
            'alasan'     => $alasan,
            'wajah'      => $wajah,
            'waktu'      => $waktu->format('H:i:s'),
            'keterangan' => $keterangan,
        ] + ($infoJarak ?? ['jarak' => null, 'radius' => null]));
    }

    /** Foto bukti presensi (SR-F14), disimpan di penyimpanan privat. */
    private function simpanFoto(string $dataUrl): ?string
    {
        $isi = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);

        if ($isi === false || @getimagesizefromstring($isi) === false) {
            return null;
        }

        $path = 'presensi/'.now()->format('Y-m-d').'/'.Str::uuid().'.jpg';
        Storage::put($path, $isi);

        return $path;
    }
}