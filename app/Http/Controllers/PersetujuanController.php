<?php

namespace App\Http\Controllers;

use App\Models\Lembur;
use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\HasilPengajuan;
use App\Notifications\HasilPengajuanLembur;
use App\Services\JadwalService;
use App\Services\LemburService;
use App\Services\PersetujuanService;
use App\Services\StatusHarianService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use App\Models\SuratTugas;
use App\Services\SuratTugasService;
use App\Models\LogKoreksi;
use App\Services\KoreksiPresensiService;
use App\Notifications\Pemberitahuan;

class PersetujuanController extends Controller
{
    public function __construct(
        private PersetujuanService $persetujuan,
        private LemburService $lemburService,
        private JadwalService $jadwal,
    ) {
    }

    public function index(Request $request)
    {
        $penyetuju = $request->user()->load('unit', 'roles');
        $boleh = fn ($p) => $p->user && $this->persetujuan->bolehMenyetujui($penyetuju, $p->user);

        // Hanya pengajuan yang menjadi wewenang pengguna yang sedang login
        $menunggu = PengajuanIzin::with(['user.unit', 'user.roles'])
            ->where('status', 'Menunggu')->oldest()->get()->filter($boleh)->values();

        $menungguLembur = Lembur::with(['user.unit', 'user.roles'])
            ->where('status', 'Menunggu')->oldest()->get()->filter($boleh)->values();

        $layananSurat = app(SuratTugasService::class);
        $menungguSurat = SuratTugas::with(['pegawai.unit', 'persetujuan.penyetuju', 'pembuat'])
            ->where('status', 'Menunggu')->oldest()->get()
            ->filter(fn ($s) => $layananSurat->bolehMemproses($penyetuju, $s))->values();
        
        $layananKoreksi = app(KoreksiPresensiService::class);
        $menungguKoreksi = LogKoreksi::with(['pengubah', 'target.user.unit'])
            ->where('target_type', Presensi::class)
            ->where('status_persetujuan', 'Menunggu')
            ->oldest()->get()
            ->filter(fn ($log) => $layananKoreksi->bolehMemproses($penyetuju, $log))->values();
        
        // Tab sesuai peran
        $tabs = [
            'izin'   => ['Izin & Sakit', $menunggu->count()],
            'lembur' => ['Lembur', $menungguLembur->count()],
        ];
        if ($penyetuju->hasRole(Role::KEPALA_SEKOLAH) || $penyetuju->hasRole(Role::KETUA_YAYASAN)) {
            $tabs['surat'] = ['Surat Tugas', $menungguSurat->count()];
        }
        if ($penyetuju->hasRole(Role::KEPALA_SEKOLAH)) {
            $tabs['koreksi'] = ['Koreksi Presensi Admin', $menungguKoreksi->count()];
        }

        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'izin';
        $id = $request->integer('id');

        $dipilih = $tab === 'izin' ? ($menunggu->firstWhere('id', $id) ?? $menunggu->first()) : null;
        $lemburDipilih = $tab === 'lembur' ? ($menungguLembur->firstWhere('id', $id) ?? $menungguLembur->first()) : null;
        $suratDipilih = $tab === 'surat' ? ($menungguSurat->firstWhere('id', $id) ?? $menungguSurat->first()) : null;
        $koreksiDipilih = $tab === 'koreksi' ? ($menungguKoreksi->firstWhere('id', $id) ?? $menungguKoreksi->first()) : null;

        return view('persetujuan.index', [
            'judul'          => 'Persetujuan',
            'tabs'           => $tabs,
            'tab'            => $tab,

            // Koreksi presensi admin
            'menungguKoreksi' => $menungguKoreksi,
            'koreksiDipilih'  => $koreksiDipilih,

            // Izin & sakit
            'menunggu'       => $menunggu,
            'dipilih'        => $dipilih,
            'info'           => $dipilih ? $this->info($dipilih) : null,
            'diproses'       => PengajuanIzin::with('user')
                ->where('disetujui_oleh', $penyetuju->id)->latest('diproses_at')->limit(5)->get(),

            // Lembur
            'menungguLembur' => $menungguLembur,
            'lemburDipilih'  => $lemburDipilih,
            'infoLembur'     => $lemburDipilih ? $this->infoLembur($lemburDipilih) : null,
            'diprosesLembur' => Lembur::with('user')
                ->where('disetujui_oleh', $penyetuju->id)->latest('diproses_at')->limit(5)->get(),
            
            // Surat tugas
            'menungguSurat'  => $menungguSurat,
            'suratDipilih'   => $suratDipilih,
            'dataSurat'      => $suratDipilih ? $layananSurat->dataSurat($suratDipilih) : null,
            'ttdSaya'        => $penyetuju->ttd_path,
        ]);
    }

    // ================= Izin & sakit =================

    public function setujui(Request $request, PengajuanIzin $pengajuan, StatusHarianService $statusHarian)
    {
        $this->pastikanBoleh($request->user(), $pengajuan);
        if ($this->versiBerubah($request, $pengajuan)) {
            return $this->kembaliVersiBerubah(['id' => $pengajuan->id]);
        }

        $pengajuan->update([
            'status'           => 'Disetujui',
            'alasan_penolakan' => null,
            'disetujui_oleh'   => $request->user()->id,
            'diproses_at'      => now(),
        ]);

        // SR-F19: tanggal yang sudah lewat & hari ini langsung menjadi Izin/Sakit
        $akhir = $pengajuan->tanggal_selesai->copy()->min(today());
        if ($pengajuan->tanggal_mulai->lte($akhir)) {
            foreach (CarbonPeriod::create($pengajuan->tanggal_mulai, $akhir) as $tanggal) {
                $statusHarian->tetapkan($pengajuan->user, $tanggal->copy());
            }
        }

        $pengajuan->user->notify(new HasilPengajuan($pengajuan));

        return redirect()->route('persetujuan')
            ->with('sukses', "Pengajuan {$pengajuan->jenis} {$pengajuan->user->nama_lengkap} disetujui.");
    }

    public function tolak(Request $request, PengajuanIzin $pengajuan)
    {
        $this->pastikanBoleh($request->user(), $pengajuan);
        if ($this->versiBerubah($request, $pengajuan)) {
            return $this->kembaliVersiBerubah(['id' => $pengajuan->id]);
        }
        $this->validasiAlasan($request);

        $pengajuan->update([
            'status'           => 'Ditolak',
            'alasan_penolakan' => $request->input('alasan_penolakan'),
            'disetujui_oleh'   => $request->user()->id, // diproses oleh
            'diproses_at'      => now(),
        ]);

        $pengajuan->user->notify(new HasilPengajuan($pengajuan));

        return redirect()->route('persetujuan')
            ->with('sukses', "Pengajuan {$pengajuan->jenis} {$pengajuan->user->nama_lengkap} ditolak.");
    }

    // ================= Lembur =================
    public function setujuiLembur(Request $request, Lembur $lembur)
    {
        $this->pastikanBoleh($request->user(), $lembur);
        if ($this->versiBerubah($request, $lembur)) {
            return $this->kembaliVersiBerubah(['tab' => 'lembur', 'id' => $lembur->id]);
        }

        $lembur->update([
            'status'                 => 'Disetujui',
            'alasan_penolakan'       => null,
            'disetujui_oleh'         => $request->user()->id,
            'diproses_at'            => now(),
            // SR-F22: dibatasi presensi pulang aktual; null jika belum presensi pulang
            'durasi_disetujui_menit' => $this->lemburService->hitungDurasi($lembur),
        ]);

        $lembur->user->notify(new HasilPengajuanLembur($lembur));

        return redirect()->route('persetujuan', ['tab' => 'lembur'])
            ->with('sukses', "Lembur {$lembur->user->nama_lengkap} disetujui.");
    }

    public function tolakLembur(Request $request, Lembur $lembur)
    {
        $this->pastikanBoleh($request->user(), $lembur);
        if ($this->versiBerubah($request, $lembur)) {
            return $this->kembaliVersiBerubah(['tab' => 'lembur', 'id' => $lembur->id]);
        }
        $this->validasiAlasan($request);

        $lembur->update([
            'status'           => 'Ditolak',
            'alasan_penolakan' => $request->input('alasan_penolakan'),
            'disetujui_oleh'   => $request->user()->id,
            'diproses_at'      => now(),
        ]);

        $lembur->user->notify(new HasilPengajuanLembur($lembur));

        return redirect()->route('persetujuan', ['tab' => 'lembur'])
            ->with('sukses', "Lembur {$lembur->user->nama_lengkap} ditolak.");
    }

    // ================= Surat tugas =================

    public function setujuiSurat(Request $request, SuratTugas $surat, SuratTugasService $layanan)
    {
        $user = $request->user();
        abort_unless($layanan->bolehMemproses($user, $surat), 403, 'Surat tugas ini bukan wewenang Anda atau sudah diproses.');

        if (! $user->ttd_path) {
            return back()->with('galat', 'Unggah gambar tanda tangan Anda terlebih dahulu.');
        }

        $layanan->setujui($surat, $user);
        $surat->refresh();

        $pesan = $surat->status === 'Disetujui'
            ? "Surat tugas disetujui dan diterbitkan dengan nomor {$surat->nomor_surat}."
            : 'Surat tugas ditandatangani dan diteruskan ke Ketua Yayasan.';

        return redirect()->route('persetujuan', ['tab' => 'surat'])->with('sukses', $pesan);
    }

    public function tolakSurat(Request $request, SuratTugas $surat, SuratTugasService $layanan)
    {
        abort_unless($layanan->bolehMemproses($request->user(), $surat), 403, 'Surat tugas ini bukan wewenang Anda atau sudah diproses.');

        $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'catatan.required' => 'Catatan penolakan wajib diisi.',
            'catatan.min'      => 'Catatan penolakan minimal 5 karakter.',
        ]);

        $layanan->tolak($surat, $request->user(), $request->input('catatan'));

        return redirect()->route('persetujuan', ['tab' => 'surat'])
            ->with('sukses', 'Surat tugas ditolak (final). Administrator dan pengusul sudah diberi tahu.');
    }

    public function revisiSurat(Request $request, SuratTugas $surat, SuratTugasService $layanan)
    {
        abort_unless($layanan->bolehMemproses($request->user(), $surat), 403, 'Surat tugas ini bukan wewenang Anda atau sudah diproses.');

        $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'catatan.required' => 'Catatan revisi wajib diisi.',
            'catatan.min'      => 'Catatan revisi minimal 5 karakter.',
        ]);

        $layanan->mintaRevisi($surat, $request->user(), $request->input('catatan'));

        return redirect()->route('persetujuan', ['tab' => 'surat'])
            ->with('sukses', 'Surat tugas dikembalikan ke administrator untuk direvisi.');
    }

    // ================= Koreksi presensi admin =================

    public function setujuiKoreksi(Request $request, LogKoreksi $log, KoreksiPresensiService $layanan)
    {
        abort_unless($layanan->bolehMemproses($request->user(), $log), 403, 'Koreksi ini bukan wewenang Anda atau sudah diproses.');

        $layanan->setujui($log, $request->user());

        $log->pengubah?->notify(new Pemberitahuan(
            'Koreksi presensi Anda tanggal '.$log->target->tanggal->translatedFormat('j F').' disetujui',
            KoreksiPresensiService::ringkasan($log->nilai_lama, $log->nilai_baru),
            route('admin.presensi', ['tanggal' => $log->target->tanggal->toDateString()]),
            'berhasil'
        ));

        return redirect()->route('persetujuan', ['tab' => 'koreksi'])->with('sukses', 'Koreksi presensi disetujui dan diterapkan.');
    }

    public function tolakKoreksi(Request $request, LogKoreksi $log, KoreksiPresensiService $layanan)
    {
        abort_unless($layanan->bolehMemproses($request->user(), $log), 403, 'Koreksi ini bukan wewenang Anda atau sudah diproses.');

        $request->validate([
            'catatan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'catatan.required' => 'Alasan penolakan wajib diisi.',
            'catatan.min'      => 'Alasan penolakan minimal 5 karakter.',
        ]);

        $layanan->tolak($log, $request->user(), $request->input('catatan'));

        $log->pengubah?->notify(new Pemberitahuan(
            'Koreksi presensi Anda tanggal '.$log->target->tanggal->translatedFormat('j F').' ditolak',
            'Alasan: '.$request->input('catatan'),
            route('admin.presensi', ['tanggal' => $log->target->tanggal->toDateString()]),
            'ditolak'
        ));

        return redirect()->route('persetujuan', ['tab' => 'koreksi'])->with('sukses', 'Koreksi presensi ditolak. Data tidak berubah.');
    }

    // ================= Helper =================

    /** @param PengajuanIzin|Lembur $pengajuan */
    private function pastikanBoleh(User $penyetuju, $pengajuan): void
    {
        abort_unless(
            $pengajuan->status === 'Menunggu'
                && $pengajuan->user
                && $this->persetujuan->bolehMenyetujui($penyetuju, $pengajuan->user),
            403,
            'Pengajuan ini bukan wewenang Anda atau sudah diproses.'
        );
    }

    /**
     * True jika pegawai mengubah pengajuan setelah halaman persetujuan dibuka.
     * Nilai "versi" dikirim dari form persetujuan (updated_at saat halaman dimuat).
     *
     * @param PengajuanIzin|Lembur $pengajuan
     */
    private function versiBerubah(Request $request, $pengajuan): bool
    {
        $versi = $request->input('versi');

        return $versi !== null && (string) $pengajuan->updated_at?->timestamp !== (string) $versi;
    }

    private function kembaliVersiBerubah(array $parameter)
    {
        return redirect()->route('persetujuan', $parameter)
            ->with('galat', 'Pengajuan baru saja diubah oleh pegawai. Periksa kembali data terbaru sebelum memutuskan.');
    }

    private function validasiAlasan(Request $request): void
    {
        $request->validate([
            'alasan_penolakan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi.',
            'alasan_penolakan.min'      => 'Alasan penolakan minimal 5 karakter.',
        ]);
    }

    /** Informasi pendukung keputusan izin/sakit. */
    private function info(PengajuanIzin $p): array
    {
        // Jika pegawai mengedit, batas H-1/H+1 dihitung dari waktu edit terakhir
        $diajukan = ($p->updated_at ?? $p->created_at)->copy()->startOfDay();

        $batasOk = $p->jenis === 'Izin'
            ? $diajukan->lt($p->tanggal_mulai)
            : $diajukan->lte($p->tanggal_mulai->copy()->addDay());

        $semester = Semester::where('unit_id', $p->user->unit_id)
            ->whereDate('tanggal_mulai', '<=', $p->tanggal_mulai)
            ->whereDate('tanggal_selesai', '>=', $p->tanggal_mulai)
            ->first();

        $jumlahSemester = $semester
            ? PengajuanIzin::where('user_id', $p->user_id)
                ->where('jenis', $p->jenis)
                ->where('status', 'Disetujui')
                ->whereBetween('tanggal_mulai', [$semester->tanggal_mulai, $semester->tanggal_selesai])
                ->count()
            : null;

        return [
            'hari'           => (int) $p->tanggal_mulai->diffInDays($p->tanggal_selesai) + 1,
            'batasLabel'     => $p->jenis === 'Izin' ? 'Batas H-1' : 'Batas H+1',
            'batasOk'        => $batasOk,
            'jumlahSemester' => $jumlahSemester,
            'pdf'            => str_ends_with(strtolower((string) $p->bukti_path), '.pdf'),
        ];
    }

    /** Informasi pendukung keputusan lembur. */
    private function infoLembur(Lembur $l): array
    {
        $jadwal = $this->jadwal->jadwal($l->user, $l->tanggal->copy());
        $presensi = Presensi::where('user_id', $l->user_id)->whereDate('tanggal', $l->tanggal)->first();

        return [
            'diajukan'     => LemburService::formatMenit(LemburService::menitDiajukan($l)),
            'jadwalPulang' => $jadwal ? $jadwal['pulang']->format('H.i') : '–',
            'pulangAktual' => $presensi?->jam_pulang,
            'perkiraan'    => $this->lemburService->hitungDurasi($l),
        ];
    }
}