<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lembur;
use App\Models\LogKoreksi;
use App\Models\PengajuanIzin;
use App\Models\Unit;
use App\Notifications\Pemberitahuan;
use App\Services\StatusHarianService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PengajuanController extends Controller
{
    public const LABEL_LOG = [
        'jenis' => 'Jenis', 'tanggal_mulai' => 'Mulai', 'tanggal_selesai' => 'Selesai',
        'status' => 'Status', 'alasan_penolakan' => 'Alasan ditolak',
    ];

    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'lembur' ? 'lembur' : 'izin';
        $filter = $request->only(['cari', 'unit', 'status', 'bulan']);
        $bulan = preg_match('/^\d{4}-\d{2}$/', (string) ($filter['bulan'] ?? ''))
            ? Carbon::createFromFormat('Y-m-d', $filter['bulan'].'-01') : null;
        $kolomTanggal = $tab === 'izin' ? 'tanggal_mulai' : 'tanggal';

        $query = ($tab === 'izin' ? PengajuanIzin::query() : Lembur::query())
            ->with(['user.unit', 'penyetuju'])
            ->when($filter['cari'] ?? null, fn ($q, $cari) => $q->whereHas('user', fn ($u) => $u
                ->where('nama_lengkap', 'like', "%{$cari}%")->orWhere('nip', 'like', "%{$cari}%")))
            ->when($filter['unit'] ?? null, fn ($q, $unit) => $q->whereHas('user', fn ($u) => $u->where('unit_id', $unit)))
            ->when($filter['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($bulan, fn ($q) => $q->whereYear($kolomTanggal, $bulan->year)->whereMonth($kolomTanggal, $bulan->month))
            ->latest();

        return view('admin.pengajuan', [
            'judul'        => 'Izin, Sakit & Lembur',
            'tab'          => $tab,
            'filter'       => $filter,
            'units'        => Unit::orderBy('id')->get(),
            'data'         => $query->paginate(10)->withQueryString(),
            'pilihanBulan' => collect(range(0, 11))->map(fn ($i) => today()->startOfMonth()->subMonths($i)),
            'log'          => LogKoreksi::with(['pengubah', 'target.user'])
                ->where('target_type', PengajuanIzin::class)->latest()->limit(10)->get(),
        ]);
    }

    /** SR-F18: koreksi pengajuan izin/sakit yang sudah diproses, tercatat di log. */
    public function updateIzin(Request $request, PengajuanIzin $pengajuan, StatusHarianService $statusHarian)
    {
        abort_unless(in_array($pengajuan->status, ['Disetujui', 'Ditolak'], true), 403,
            'Pengajuan yang masih menunggu diproses oleh penyetuju.');

        if ($pengajuan->user_id === $request->user()->id) {
            return back()->with('galat', 'Pengajuan milik Anda sendiri tidak dapat dikoreksi dari halaman ini.');
        }

        $validator = Validator::make($request->all(), [
            'jenis'            => ['required', Rule::in(['Izin', 'Sakit'])],
            'tanggal_mulai'    => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai'  => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'status'           => ['required', Rule::in(['Disetujui', 'Ditolak'])],
            'alasan_penolakan' => ['nullable', 'required_if:status,Ditolak', 'string', 'max:500'],
            'alasan_edit'      => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'required'                       => ':attribute wajib diisi.',
            'alasan_penolakan.required_if'   => 'Alasan penolakan wajib diisi jika status Ditolak.',
            'alasan_edit.min'                => 'Alasan koreksi minimal 5 karakter.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ], [
            'tanggal_mulai' => 'Tanggal mulai', 'tanggal_selesai' => 'Tanggal selesai',
            'alasan_edit' => 'Alasan koreksi',
        ]);

        $validator->after(function ($v) use ($request, $pengajuan) {
            if ($v->errors()->hasAny(['tanggal_mulai', 'tanggal_selesai'])) {
                return;
            }
            $bentrok = PengajuanIzin::where('user_id', $pengajuan->user_id)
                ->whereKeyNot($pengajuan->id)
                ->whereIn('status', ['Menunggu', 'Disetujui'])
                ->whereDate('tanggal_mulai', '<=', $request->input('tanggal_selesai'))
                ->whereDate('tanggal_selesai', '>=', $request->input('tanggal_mulai'))
                ->exists();
            if ($bentrok) {
                $v->errors()->add('tanggal_mulai', 'Pegawai sudah punya pengajuan lain pada rentang tanggal tersebut.');
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('modal', 'edit');
        }

        $data = $validator->validated();
        $nilai = fn (PengajuanIzin $p) => [
            'jenis'            => $p->jenis,
            'tanggal_mulai'    => $p->tanggal_mulai->toDateString(),
            'tanggal_selesai'  => $p->tanggal_selesai->toDateString(),
            'status'           => $p->status,
            'alasan_penolakan' => $p->alasan_penolakan,
        ];

        $lama = $nilai($pengajuan);
        $mulaiLama = $pengajuan->tanggal_mulai->copy();
        $selesaiLama = $pengajuan->tanggal_selesai->copy();

        DB::transaction(function () use ($pengajuan, $data, $lama, $nilai, $request) {
            $pengajuan->update([
                'jenis'            => $data['jenis'],
                'tanggal_mulai'    => $data['tanggal_mulai'],
                'tanggal_selesai'  => $data['tanggal_selesai'],
                'status'           => $data['status'],
                'alasan_penolakan' => $data['status'] === 'Ditolak' ? $data['alasan_penolakan'] : null,
            ]);

            LogKoreksi::create([
                'user_id'            => $request->user()->id,
                'target_type'        => PengajuanIzin::class,
                'target_id'          => $pengajuan->id,
                'nilai_lama'         => $lama,
                'nilai_baru'         => $nilai($pengajuan->refresh()),
                'alasan'             => $data['alasan_edit'],
                'status_persetujuan' => 'Tidak Perlu',
            ]);
        });

        // Hitung ulang status presensi pada rentang lama & baru (sampai hari ini)
        $mulai = $mulaiLama->min($pengajuan->tanggal_mulai);
        $selesai = $selesaiLama->max($pengajuan->tanggal_selesai)->min(today());
        if ($mulai->lte($selesai)) {
            foreach (CarbonPeriod::create($mulai, $selesai) as $tanggal) {
                $statusHarian->tetapkan($pengajuan->user, $tanggal->copy());
            }
        }

        $pengajuan->user?->notify(new Pemberitahuan(
            "Pengajuan {$pengajuan->jenis} Anda dikoreksi administrator",
            'Alasan: '.$data['alasan_edit'],
            route('pegawai.izin'),
            'info'
        ));

        return back()->with('sukses', "Pengajuan {$pengajuan->user?->nama_lengkap} dikoreksi; status presensi terkait dihitung ulang.");
    }

    /** Ringkasan perubahan untuk tabel log. */
    public static function ringkasan(?array $lama, ?array $baru): string
    {
        $bagian = [];
        foreach (self::LABEL_LOG as $kunci => $label) {
            $a = $lama[$kunci] ?? null;
            $b = $baru[$kunci] ?? null;
            if ($a !== $b) {
                $bagian[] = "{$label}: ".($a ?? '—').' → '.($b ?? '—');
            }
        }

        return $bagian ? implode('; ', $bagian) : 'Tidak ada perubahan nilai';
    }
}