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

        // Tab sesuai peran
        $tabs = [
            'izin'   => ['Izin & Sakit', $menunggu->count()],
            'lembur' => ['Lembur', $menungguLembur->count()],
        ];
        if ($penyetuju->hasRole(Role::KEPALA_SEKOLAH) || $penyetuju->hasRole(Role::KETUA_YAYASAN)) {
            $tabs['surat'] = ['Surat Tugas', 0];
        }
        if ($penyetuju->hasRole(Role::KEPALA_SEKOLAH)) {
            $tabs['koreksi'] = ['Koreksi Presensi Admin', 0];
        }

        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'izin';
        $id = $request->integer('id');

        $dipilih = $tab === 'izin' ? ($menunggu->firstWhere('id', $id) ?? $menunggu->first()) : null;
        $lemburDipilih = $tab === 'lembur' ? ($menungguLembur->firstWhere('id', $id) ?? $menungguLembur->first()) : null;

        return view('persetujuan.index', [
            'judul'          => 'Persetujuan',
            'tabs'           => $tabs,
            'tab'            => $tab,

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
        ]);
    }

    // ================= Izin & sakit =================

    public function setujui(Request $request, PengajuanIzin $pengajuan, StatusHarianService $statusHarian)
    {
        $this->pastikanBoleh($request->user(), $pengajuan);

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
        $diajukan = $p->created_at->copy()->startOfDay();

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