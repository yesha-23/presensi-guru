<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Notifications\HasilPengajuan;
use App\Services\PersetujuanService;
use App\Services\StatusHarianService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class PersetujuanController extends Controller
{
    public function __construct(private PersetujuanService $persetujuan)
    {
    }

    public function index(Request $request)
    {
        $penyetuju = $request->user()->load('unit', 'roles');

        // Hanya pengajuan yang menjadi wewenang pengguna yang sedang login
        $menunggu = PengajuanIzin::with(['user.unit', 'user.roles'])
            ->where('status', 'Menunggu')
            ->oldest()
            ->get()
            ->filter(fn ($p) => $p->user && $this->persetujuan->bolehMenyetujui($penyetuju, $p->user))
            ->values();

        $dipilih = $menunggu->firstWhere('id', $request->integer('id')) ?? $menunggu->first();

        // Tab sesuai peran (mockup: kepala sekolah punya 4 tab)
        $tabs = ['izin' => ['Izin & Sakit', $menunggu->count()], 'lembur' => ['Lembur', 0]];
        if ($penyetuju->hasRole(Role::KEPALA_SEKOLAH) || $penyetuju->hasRole(Role::KETUA_YAYASAN)) {
            $tabs['surat'] = ['Surat Tugas', 0];
        }
        if ($penyetuju->hasRole(Role::KEPALA_SEKOLAH)) {
            $tabs['koreksi'] = ['Koreksi Presensi Admin', 0];
        }

        $tab = array_key_exists($request->query('tab'), $tabs) ? $request->query('tab') : 'izin';

        return view('persetujuan.index', [
            'judul'    => 'Persetujuan',
            'tabs'     => $tabs,
            'tab'      => $tab,
            'menunggu' => $menunggu,
            'dipilih'  => $dipilih,
            'info'     => $dipilih ? $this->info($dipilih) : null,
            'diproses' => PengajuanIzin::with('user')
                ->where('disetujui_oleh', $penyetuju->id)
                ->latest('diproses_at')
                ->limit(5)
                ->get(),
        ]);
    }

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

        $request->validate([
            'alasan_penolakan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan_penolakan.required' => 'Alasan penolakan wajib diisi.',
            'alasan_penolakan.min'      => 'Alasan penolakan minimal 5 karakter.',
        ]);

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

    private function pastikanBoleh(User $penyetuju, PengajuanIzin $pengajuan): void
    {
        abort_unless(
            $pengajuan->status === 'Menunggu'
                && $pengajuan->user
                && $this->persetujuan->bolehMenyetujui($penyetuju, $pengajuan->user),
            403,
            'Pengajuan ini bukan wewenang Anda atau sudah diproses.'
        );
    }

    /** Informasi pendukung keputusan: lama, batas waktu, jumlah izin semester ini. */
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
}