<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Notifications\PengajuanBaru;
use App\Services\PersetujuanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Notification;

class IzinController extends Controller
{
    public function __construct(private PersetujuanService $persetujuan)
    {
    }

    public function create(Request $request)
    {
        $user = $request->user();

        return view('pegawai.izin', [
            'judul'     => 'Ajukan Izin / Sakit',
            'riwayat'   => PengajuanIzin::where('user_id', $user->id)->latest()->limit(10)->get(),
            'tujuan'    => $this->persetujuan->labelPenyetuju($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $hariIni = today();

        // Validasi 1, 2, 4: kelengkapan, urutan tanggal, format & ukuran bukti
        $validator = Validator::make($request->all(), [
            'jenis'           => ['required', Rule::in(['Izin', 'Sakit'])],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'alasan'          => ['required', 'string', 'max:1000'],
            'bukti'           => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ], [
            'required'                       => ':attribute wajib diisi.',
            'bukti.required'                 => 'Bukti pendukung wajib diunggah.',
            'bukti.mimes'                    => 'Bukti harus berformat JPG, PNG, atau PDF.',
            'bukti.max'                      => 'Ukuran bukti maksimal 2 MB.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'alasan.max'                     => 'Alasan maksimal 1000 karakter.',
        ], [
            'jenis' => 'Jenis pengajuan', 'tanggal_mulai' => 'Tanggal mulai',
            'tanggal_selesai' => 'Tanggal selesai', 'alasan' => 'Alasan',
        ]);

        // Validasi 3 & 5: batas H-1 / H+1 dan tidak bentrok dengan pengajuan lain
        $validator->after(function ($v) use ($request, $user, $hariIni) {
            if ($v->errors()->hasAny(['jenis', 'tanggal_mulai', 'tanggal_selesai'])) {
                return;
            }

            $mulai = Carbon::parse($request->input('tanggal_mulai'));
            $selesai = Carbon::parse($request->input('tanggal_selesai'));

            if ($request->input('jenis') === 'Izin' && $mulai->lt($hariIni->copy()->addDay())) {
                $v->errors()->add('tanggal_mulai', 'Izin wajib diajukan paling lambat H-1 (tanggal mulai paling cepat besok).');
            }

            if ($request->input('jenis') === 'Sakit') {
                if ($mulai->lt($hariIni->copy()->subDay())) {
                    $v->errors()->add('tanggal_mulai', 'Pengajuan sakit susulan paling lambat H+1 (tanggal mulai paling awal kemarin).');
                } elseif ($mulai->gt($hariIni)) {
                    $v->errors()->add('tanggal_mulai', 'Pengajuan sakit dimulai hari ini atau kemarin.');
                }
            }

            $bentrok = PengajuanIzin::where('user_id', $user->id)
                ->whereIn('status', ['Menunggu', 'Disetujui'])
                ->whereDate('tanggal_mulai', '<=', $selesai)
                ->whereDate('tanggal_selesai', '>=', $mulai)
                ->exists();

            if ($bentrok) {
                $v->errors()->add('tanggal_mulai', 'Sudah ada pengajuan lain pada rentang tanggal tersebut.');
            }
        });

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $penyetuju = $this->persetujuan->penyetujuUntuk($user);
        if ($penyetuju->isEmpty()) {
            return back()->withInput()->with('galat', 'Penyetuju belum terdaftar. Hubungi administrator.');
        }

        $data = $validator->validated();

        // Bukti disimpan di penyimpanan privat (SR-NF04)
        $pengajuan = PengajuanIzin::create([
            'user_id'         => $user->id,
            'jenis'           => $data['jenis'],
            'tanggal_mulai'   => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'alasan'          => $data['alasan'],
            'bukti_path'      => $request->file('bukti')->store("bukti/{$user->id}"),
            'status'          => 'Menunggu',
        ]);

        // Semua penyetuju yang berwenang menerima notifikasi
        Notification::send($penyetuju, new PengajuanBaru($pengajuan));

        return redirect()->route('pegawai.izin')
            ->with('sukses', "Pengajuan {$pengajuan->jenis} terkirim ke {$this->persetujuan->labelPenyetuju($user)}. Status: Menunggu.");
    }

    /** Pegawai dapat membatalkan pengajuan yang belum diproses. */
    public function batal(Request $request, PengajuanIzin $pengajuan)
    {
        abort_unless($pengajuan->user_id === $request->user()->id && $pengajuan->status === 'Menunggu', 403);

        if ($pengajuan->bukti_path) {
            Storage::delete($pengajuan->bukti_path);
        }
        $pengajuan->delete();

        return back()->with('sukses', 'Pengajuan dibatalkan.');
    }
}