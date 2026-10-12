<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogKoreksi;
use App\Models\Presensi;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\Pemberitahuan;
use App\Services\KoreksiPresensiService;
use App\Services\PersetujuanService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DataPresensiController extends Controller
{
    public const FILTER_STATUS = [
        'Tepat Waktu', 'Terlambat', 'Pulang Cepat', 'Tidak Presensi Pulang',
        'Izin', 'Sakit', 'Tugas Khusus', 'Alpa', 'Libur', 'Belum Presensi',
    ];

    public function __construct(private KoreksiPresensiService $koreksi)
    {
    }

    public function index(Request $request)
    {
        [$tanggal, $filter] = $this->bacaFilter($request);

        $pegawai = $this->query($tanggal, $filter)->paginate(10)->withQueryString();

        return view('admin.presensi.index', [
            'judul'        => 'Data Presensi',
            'tanggal'      => $tanggal,
            'filter'       => $filter,
            'pegawai'      => $pegawai,
            'units'        => Unit::orderBy('id')->get(),
            'daftarStatus' => self::FILTER_STATUS,
            'statusEdit'   => KoreksiPresensiService::STATUS,
            // Presensi yang sedang menunggu persetujuan koreksi: tombol Edit dinonaktifkan
            'menunggu'     => LogKoreksi::where('target_type', Presensi::class)
                ->where('status_persetujuan', 'Menunggu')->pluck('target_id')->all(),
            'log'          => LogKoreksi::with(['pengubah', 'target.user'])
                ->where('target_type', Presensi::class)->latest()->limit(10)->get(),
        ]);
    }

    public function koreksi(Request $request, PersetujuanService $persetujuan)
    {
        // Input jam dinormalkan ke HH:MM:SS
        $jam = fn ($nilai) => $nilai ? (strlen($nilai) === 5 ? "{$nilai}:00" : $nilai) : null;
        $request->merge([
            'jam_datang' => $jam($request->input('jam_datang')),
            'jam_pulang' => $jam($request->input('jam_pulang')),
        ]);

        $validator = Validator::make($request->all(), [
            'user_id'       => ['required', 'exists:users,id'],
            'tanggal'       => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status_harian' => ['required', Rule::in(KoreksiPresensiService::STATUS)],
            'jam_datang'    => ['nullable', 'date_format:H:i:s'],
            'jam_pulang'    => ['nullable', 'date_format:H:i:s'],
            'alasan'        => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan.required'        => 'Alasan edit wajib diisi.',
            'alasan.min'             => 'Alasan edit minimal 5 karakter.',
            'date_format'            => 'Format :attribute harus HH:MM:SS.',
            'tanggal.before_or_equal' => 'Presensi tanggal yang akan datang tidak dapat diubah.',
        ], [
            'jam_datang' => 'jam datang', 'jam_pulang' => 'jam pulang',
        ]);

        // Validasi konsistensi (catatan activity diagram 5.8)
        $validator->after(function ($v) use ($request) {
            if ($v->errors()->hasAny(['status_harian', 'jam_datang', 'jam_pulang', 'user_id', 'tanggal'])) {
                return;
            }

            $status = $request->input('status_harian');
            $datang = $request->input('jam_datang');
            $pulang = $request->input('jam_pulang');

            if ($status === 'Hadir' && ! $datang) {
                $v->errors()->add('jam_datang', 'Status Hadir wajib memiliki jam datang.');
            }
            if ($status !== 'Hadir' && ($datang || $pulang)) {
                $v->errors()->add('status_harian', "Status {$status} tidak boleh memiliki jam datang atau jam pulang.");
            }
            if ($pulang && ! $datang) {
                $v->errors()->add('jam_pulang', 'Jam pulang hanya dapat diisi jika ada jam datang.');
            }
            if ($datang && $pulang && $pulang <= $datang) {
                $v->errors()->add('jam_pulang', 'Jam pulang harus lebih besar dari jam datang.');
            }

            $sedangMenunggu = LogKoreksi::where('target_type', Presensi::class)
                ->where('status_persetujuan', 'Menunggu')
                ->whereIn('target_id', Presensi::where('user_id', $request->input('user_id'))
                    ->whereDate('tanggal', $request->input('tanggal'))->pluck('id'))
                ->exists();
            if ($sedangMenunggu) {
                $v->errors()->add('alasan', 'Masih ada koreksi untuk presensi ini yang menunggu persetujuan.');
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal', 'edit');
        }

        $data = $validator->validated();
        $pegawai = User::withTrashed()->findOrFail($data['user_id']);
        $tanggal = Carbon::parse($data['tanggal']);

        $log = $this->koreksi->ajukan(
            $request->user(),
            $pegawai,
            $tanggal,
            [
                'status_harian' => $data['status_harian'],
                'jam_datang'    => $data['status_harian'] === 'Hadir' ? $data['jam_datang'] : null,
                'jam_pulang'    => $data['status_harian'] === 'Hadir' ? $data['jam_pulang'] : null,
            ],
            $data['alasan']
        );

        if ($log->status_persetujuan === 'Menunggu') {
            Notification::send($persetujuan->penyetujuUntuk($pegawai), new Pemberitahuan(
                "Koreksi presensi admin menunggu persetujuan: {$pegawai->nama_lengkap}",
                $tanggal->translatedFormat('j F Y').' · '.KoreksiPresensiService::ringkasan($log->nilai_lama, $log->nilai_baru),
                route('persetujuan', ['tab' => 'koreksi', 'id' => $log->id]),
                'pengajuan'
            ));

            return back()->with('info', 'Koreksi presensi milik Anda sendiri dikirim ke Kepala Sekolah untuk disetujui. Data belum berubah.');
        }

        return back()->with('sukses', "Presensi {$pegawai->nama_lengkap} tanggal ".$tanggal->translatedFormat('j F Y').' diperbarui dan dicatat di log.');
    }

    public function ekspor(Request $request)
    {
        [$tanggal, $filter] = $this->bacaFilter($request);
        $pegawai = $this->query($tanggal, $filter)->get();

        $buku = new Spreadsheet();
        $lembar = $buku->getActiveSheet()->setTitle('Presensi');
        $lembar->fromArray(['Data Presensi '.$tanggal->translatedFormat('l, j F Y')], null, 'A1');
        $lembar->fromArray([
            'No', 'Nama', 'NIP', 'Unit', 'Kategori', 'Datang', 'Status datang', 'Terlambat',
            'Pulang', 'Status pulang', 'Pulang lebih awal', 'Status harian',
        ], null, 'A3');

        $baris = 4;
        foreach ($pegawai as $i => $u) {
            $p = $u->presensi->first();
            $lembar->fromArray([
                $i + 1, $u->nama_lengkap, $u->nip, $u->unit->label(), $u->kategori,
                $p?->jam_datang ?? '-', $this->statusDatang($p, $tanggal),
                $p?->detik_terlambat ? Presensi::formatDetik($p->detik_terlambat) : '-',
                $p?->jam_pulang ?? '-', $this->statusPulang($p, $tanggal),
                $p?->detik_pulang_cepat ? Presensi::formatDetik($p->detik_pulang_cepat) : '-',
                $p?->status_harian ?? '-',
            ], null, 'A'.$baris++);
        }

        $lembar->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $lembar->getStyle('A3:L3')->getFont()->setBold(true);
        foreach (range('A', 'L') as $kolom) {
            $lembar->getColumnDimension($kolom)->setAutoSize(true);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            'data_presensi_'.$tanggal->toDateString().'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    // ================= Helper (juga dipakai view) =================

    public static function statusDatang(?Presensi $p, Carbon $tanggal): string
    {
        return match (true) {
            (bool) $p?->jam_datang    => $p->status_datang,
            (bool) $p?->status_harian => $p->status_harian,
            $tanggal->isToday()       => 'Belum Presensi',
            default                   => '—',
        };
    }

    public static function statusPulang(?Presensi $p, Carbon $tanggal): string
    {
        return match (true) {
            (bool) $p?->jam_pulang                              => $p->pulang_cepat ? 'Pulang Cepat' : 'Tepat Waktu',
            (bool) $p?->jam_datang && $tanggal->lt(today())     => 'Tidak Presensi Pulang',
            default                                             => '—',
        };
    }

    private function bacaFilter(Request $request): array
    {
        $tanggal = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('tanggal'))
            ? Carbon::parse($request->query('tanggal'))->min(today())
            : today();

        return [$tanggal, $request->only(['unit', 'status', 'cari'])];
    }

    /** Semua pegawai wajib presensi beserta presensinya pada tanggal tersebut. */
    private function query(Carbon $tanggal, array $filter): Builder
    {
        $query = User::with(['unit', 'presensi' => fn ($p) => $p->whereDate('tanggal', $tanggal)])
            ->where('status', 'Aktif')
            ->whereNotNull('kategori')
            ->whereHas('roles', fn ($q) => $q->where('nama', Role::PEGAWAI))
            ->when($filter['unit'] ?? null, fn ($q, $unit) => $q->where('unit_id', $unit))
            ->when($filter['cari'] ?? null, fn ($q, $cari) => $q->where(fn ($q) => $q
                ->where('nama_lengkap', 'like', "%{$cari}%")
                ->orWhere('nip', 'like', "%{$cari}%")))
            ->orderBy('unit_id')
            ->orderBy('nama_lengkap');

        $status = $filter['status'] ?? null;
        if (! $status) {
            return $query;
        }

        $ada = fn (callable $kondisi) => $query->whereHas('presensi', function ($p) use ($kondisi, $tanggal) {
            $kondisi($p->whereDate('tanggal', $tanggal));
        });

        return match ($status) {
            'Tepat Waktu', 'Terlambat' => $ada(fn ($p) => $p->where('status_datang', $status)),
            'Pulang Cepat'             => $ada(fn ($p) => $p->where('pulang_cepat', true)),
            'Tidak Presensi Pulang'    => $ada(fn ($p) => $p->whereNotNull('jam_datang')->whereNull('jam_pulang')),
            'Belum Presensi'           => $query->whereDoesntHave('presensi', fn ($p) => $p
                ->whereDate('tanggal', $tanggal)->whereNotNull('status_harian')),
            default                    => $ada(fn ($p) => $p->where('status_harian', $status)),
        };
    }
}