<?php

namespace App\Http\Controllers;

use App\Models\Lembur;
use App\Notifications\PengajuanLemburBaru;
use App\Services\JadwalService;
use App\Services\PersetujuanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

class LemburController extends Controller
{
    public function __construct(
        private PersetujuanService $persetujuan,
        private JadwalService $jadwal,
    ) {
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $jadwalHariIni = $this->jadwal->jadwal($user, now());

        return view('pegawai.lembur', [
            'judul'     => 'Ajukan Lembur',
            'riwayat'   => Lembur::where('user_id', $user->id)->latest()->limit(10)->get(),
            'tujuan'    => $this->persetujuan->labelPenyetuju($user),
            // nilai awal jam mulai = jadwal pulang hari ini (atau jam pulang normal)
            'jamPulang' => $jadwalHariIni
                ? $jadwalHariIni['pulang']->format('H:i')
                : substr($user->jamKerja()?->jam_pulang ?? '15:00:00', 0, 5),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Sebagian browser mengirim jam dalam format HH:MM:SS
        $request->merge([
            'jam_mulai'   => substr((string) $request->input('jam_mulai'), 0, 5),
            'jam_selesai' => substr((string) $request->input('jam_selesai'), 0, 5),
        ]);

        $validator = Validator::make($request->all(), [
            'tanggal'     => ['required', 'date_format:Y-m-d'],
            'jam_mulai'   => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'uraian'      => ['required', 'string', 'max:1000'],
        ], [
            'required'          => ':attribute wajib diisi.',
            'date_format'       => 'Format :attribute tidak valid.',
            'jam_selesai.after' => 'Jam selesai harus lebih besar dari jam mulai.',
            'uraian.max'        => 'Uraian pekerjaan maksimal 1000 karakter.',
        ], [
            'tanggal' => 'Tanggal', 'jam_mulai' => 'Jam mulai',
            'jam_selesai' => 'Jam selesai', 'uraian' => 'Uraian pekerjaan',
        ]);

        $validator->after(function ($v) use ($request, $user) {
            if ($v->errors()->hasAny(['tanggal', 'jam_mulai', 'jam_selesai'])) {
                return;
            }

            $tanggal = Carbon::parse($request->input('tanggal'));

            if ($tanggal->lt(today()->subDay()) || $tanggal->gt(today()->addDays(30))) {
                $v->errors()->add('tanggal', 'Lembur dapat diajukan untuk kemarin sampai 30 hari ke depan.');
                return;
            }

            // Hari libur, izin/sakit, atau tugas khusus: tidak ada lembur
            if ($khusus = $this->jadwal->kondisiKhusus($user, $tanggal)) {
                $v->errors()->add('tanggal', 'Tanggal tersebut tidak dapat diajukan lembur. '.$khusus['keterangan']);
                return;
            }

            // Jam mulai tidak boleh lebih awal dari jadwal pulang (termasuk Hari Kerja Tambahan)
            $jadwalPulang = $this->jadwal->jadwal($user, $tanggal)['pulang']->format('H:i');
            if ($request->input('jam_mulai') < $jadwalPulang) {
                $v->errors()->add('jam_mulai', 'Jam mulai lembur tidak boleh lebih awal dari jadwal pulang ('.str_replace(':', '.', $jadwalPulang).').');
            }

            $bentrok = Lembur::where('user_id', $user->id)
                ->whereDate('tanggal', $tanggal)
                ->whereIn('status', ['Menunggu', 'Disetujui'])
                ->exists();

            if ($bentrok) {
                $v->errors()->add('tanggal', 'Sudah ada pengajuan lembur pada tanggal tersebut.');
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

        $lembur = Lembur::create([
            'user_id'     => $user->id,
            'tanggal'     => $data['tanggal'],
            'jam_mulai'   => $data['jam_mulai'],
            'jam_selesai' => $data['jam_selesai'],
            'uraian'      => $data['uraian'],
            'status'      => 'Menunggu',
        ]);

        Notification::send($penyetuju, new PengajuanLemburBaru($lembur));

        return redirect()->route('pegawai.lembur')
            ->with('sukses', 'Pengajuan lembur terkirim ke '.$this->persetujuan->labelPenyetuju($user).'. Status: Menunggu.');
    }

    public function batal(Request $request, Lembur $lembur)
    {
        abort_unless($lembur->user_id === $request->user()->id && $lembur->status === 'Menunggu', 403);

        $lembur->delete();

        return back()->with('sukses', 'Pengajuan lembur dibatalkan.');
    }
}