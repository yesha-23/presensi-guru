<?php

namespace App\Http\Controllers;

use App\Models\Lembur;
use App\Models\User;
use App\Notifications\PengajuanLemburBaru;
use App\Notifications\Pemberitahuan;
use App\Services\JadwalService;
use App\Services\PersetujuanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return $this->halaman($request, null);
    }

    /** Form edit: hanya lembur milik sendiri yang masih Menunggu. */
    public function edit(Request $request, Lembur $lembur)
    {
        abort_unless($lembur->user_id === $request->user()->id, 403);

        if ($lembur->status !== 'Menunggu') {
            return $this->keRiwayat('Pengajuan lembur ini sudah diproses sehingga tidak dapat diubah.', 'galat');
        }

        return $this->halaman($request, $lembur);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->rapikanJam($request);

        $validator = $this->validator($request, $user, null);
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

        return $this->keRiwayat(
            'Pengajuan lembur terkirim ke '.$this->persetujuan->labelPenyetuju($user).'. Status: Menunggu.',
            'sukses',
            'lembur-'.$lembur->id
        );
    }

    /** Simpan perubahan lembur yang masih Menunggu. */
    public function update(Request $request, Lembur $lembur)
    {
        $user = $request->user();
        abort_unless($lembur->user_id === $user->id, 403);
        $this->rapikanJam($request);

        $validator = $this->validator($request, $user, $lembur);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        $data = $validator->validated();

        $berhasil = DB::transaction(function () use ($lembur, $data) {
            $l = Lembur::whereKey($lembur->id)->lockForUpdate()->first();
            if (! $l || $l->status !== 'Menunggu') {
                return false;
            }

            $l->update([
                'tanggal'     => $data['tanggal'],
                'jam_mulai'   => $data['jam_mulai'],
                'jam_selesai' => $data['jam_selesai'],
                'uraian'      => $data['uraian'],
            ]);

            return true;
        });

        if (! $berhasil) {
            return $this->keRiwayat('Pengajuan lembur sudah diproses penyetuju sehingga tidak dapat diubah.', 'galat');
        }

        $lembur->refresh();
        $this->kabariPenyetuju(
            $user,
            "Pengajuan lembur diperbarui: {$user->nama_lengkap}",
            $this->keterangan($lembur),
            route('persetujuan', ['tab' => 'lembur', 'id' => $lembur->id])
        );

        return $this->keRiwayat('Perubahan pengajuan lembur disimpan. Status tetap Menunggu.', 'sukses', 'lembur-'.$lembur->id);
    }

    public function batal(Request $request, Lembur $lembur)
    {
        $user = $request->user();
        abort_unless($lembur->user_id === $user->id, 403);

        $keterangan = $this->keterangan($lembur);

        $berhasil = DB::transaction(function () use ($lembur) {
            $l = Lembur::whereKey($lembur->id)->lockForUpdate()->first();
            if (! $l || $l->status !== 'Menunggu') {
                return false;
            }
            $l->delete();

            return true;
        });

        if (! $berhasil) {
            return $this->keRiwayat('Pengajuan lembur sudah diproses penyetuju sehingga tidak dapat dibatalkan.', 'galat');
        }

        $this->kabariPenyetuju($user, "Pengajuan lembur dibatalkan: {$user->nama_lengkap}", $keterangan, route('persetujuan', ['tab' => 'lembur']));

        return $this->keRiwayat('Pengajuan lembur dibatalkan.');
    }

    // ================= Helper =================

    private function halaman(Request $request, ?Lembur $edit)
    {
        $user = $request->user();
        $jadwalHariIni = $this->jadwal->jadwal($user, now());

        return view('pegawai.lembur', [
            'judul'     => $edit ? 'Edit Lembur' : 'Ajukan Lembur',
            'riwayat'   => Lembur::where('user_id', $user->id)->latest()->limit(3)->get(),
            'tujuan'    => $this->persetujuan->labelPenyetuju($user),
            // nilai awal jam mulai = jadwal pulang hari ini (atau jam pulang normal)
            'jamPulang' => $jadwalHariIni
                ? $jadwalHariIni['pulang']->format('H:i')
                : substr($user->jamKerja()?->jam_pulang ?? '15:00:00', 0, 5),
            'edit'      => $edit,
        ]);
    }

    /** Setelah berhasil, pegawai diarahkan ke menu Pengajuan. */
    private function keRiwayat(string $pesan, string $tipe = 'sukses', ?string $sorot = null)
    {
        return redirect()->route('pegawai.pengajuan')
            ->with($tipe, $pesan)
            ->with('sorot', $sorot);
    }

    /** Sebagian browser mengirim jam dalam format HH:MM:SS */
    private function rapikanJam(Request $request): void
    {
        $request->merge([
            'jam_mulai'   => substr((string) $request->input('jam_mulai'), 0, 5),
            'jam_selesai' => substr((string) $request->input('jam_selesai'), 0, 5),
        ]);
    }

    /** Validasi bersama untuk pengajuan baru dan edit ($kecuali = lembur yang sedang diedit). */
    private function validator(Request $request, User $user, ?Lembur $kecuali)
    {
        $validator = Validator::make($request->all(), [
            'tanggal'     => ['required', 'date_format:Y-m-d'],
            'jam_mulai'   => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'uraian'      => ['required', 'string', 'max:1000'],
        ], [
            // Pesan berisi apa yang salah dan cara memperbaikinya (tindak lanjut uji usabilitas)
            'required'          => ':attribute wajib diisi.',
            'uraian.required'   => 'Uraian pekerjaan wajib diisi. Tuliskan pekerjaan yang dilakukan, misalnya "Menyusun nilai rapor kelas 5".',
            'date_format'       => 'Format :attribute tidak valid.',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai. Periksa kembali kedua jam tersebut.',
            'uraian.max'        => 'Uraian pekerjaan maksimal 1000 karakter. Persingkat uraian Anda.',
        ], [
            'tanggal' => 'Tanggal', 'jam_mulai' => 'Jam mulai',
            'jam_selesai' => 'Jam selesai', 'uraian' => 'Uraian pekerjaan',
        ]);

        $validator->after(function ($v) use ($request, $user, $kecuali) {
            if ($v->errors()->hasAny(['tanggal', 'jam_mulai', 'jam_selesai'])) {
                return;
            }

            $tanggal = Carbon::parse($request->input('tanggal'));

            if ($tanggal->lt(today()->subDay()) || $tanggal->gt(today()->addDays(30))) {
                $v->errors()->add('tanggal', 'Lembur dapat diajukan untuk kemarin sampai 30 hari ke depan.');
                return;
            }

            // Hari libur atau izin/sakit: tidak ada lembur.
            // Hari Tugas Khusus boleh (mis. retret/pertandingan sampai malam: jam setelah jadwal pulang = lembur).
            $khusus = $this->jadwal->kondisiKhusus($user, $tanggal);
            if ($khusus && $khusus['status'] !== 'Tugas Khusus') {
                $v->errors()->add('tanggal', 'Tanggal tersebut tidak dapat diajukan lembur. '.$khusus['keterangan']);
                return;
            }

            // Jam mulai tidak boleh lebih awal dari jadwal pulang (termasuk Hari Kerja Tambahan).
            // Tugas di hari nonkerja: acuan jam pulang normal pegawai.
            $jadwalHari = $this->jadwal->jadwal($user, $tanggal);
            $jadwalPulang = $jadwalHari
                ? $jadwalHari['pulang']->format('H:i')
                : substr($user->jamKerja()?->jam_pulang ?? '15:00:00', 0, 5);
            if ($request->input('jam_mulai') < $jadwalPulang) {
                $v->errors()->add('jam_mulai', 'Jam mulai lembur tidak boleh lebih awal dari jadwal pulang ('.str_replace(':', '.', $jadwalPulang).').');
            }

            $bentrok = Lembur::where('user_id', $user->id)
                ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id))
                ->whereDate('tanggal', $tanggal)
                ->whereIn('status', ['Menunggu', 'Disetujui'])
                ->exists();

            if ($bentrok) {
                $v->errors()->add('tanggal', 'Sudah ada pengajuan lembur pada tanggal tersebut.');
            }
        });

        return $validator;
    }

    private function keterangan(Lembur $l): string
    {
        $jam = fn ($t) => str_replace(':', '.', substr($t, 0, 5));

        return $l->tanggal->translatedFormat('l, j F Y').' · '.$jam($l->jam_mulai).'–'.$jam($l->jam_selesai);
    }

    private function kabariPenyetuju(User $user, string $judul, string $keterangan, string $url): void
    {
        $penyetuju = $this->persetujuan->penyetujuUntuk($user);
        if ($penyetuju->isNotEmpty()) {
            Notification::send($penyetuju, new Pemberitahuan($judul, $keterangan, $url, 'pengajuan'));
        }
    }
}