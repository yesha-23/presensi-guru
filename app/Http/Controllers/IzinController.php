<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzin;
use App\Models\Presensi;
use App\Models\User;
use App\Notifications\PengajuanBaru;
use App\Notifications\Pemberitahuan;
use App\Services\PersetujuanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IzinController extends Controller
{
    public function __construct(private PersetujuanService $persetujuan)
    {
    }

    public function create(Request $request)
    {
        return $this->halaman($request, null);
    }

    /** Form edit: hanya pengajuan milik sendiri yang masih Menunggu. */
    public function edit(Request $request, PengajuanIzin $pengajuan)
    {
        abort_unless($pengajuan->user_id === $request->user()->id, 403);

        if ($pengajuan->status !== 'Menunggu') {
            return $this->keRiwayat('Pengajuan ini sudah diproses sehingga tidak dapat diubah.', 'galat');
        }

        return $this->halaman($request, $pengajuan);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validator = $this->validator($request, $user, null);
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

        Notification::send($penyetuju, new PengajuanBaru($pengajuan));

        return $this->keRiwayat(
            "Pengajuan {$pengajuan->jenis} terkirim ke {$this->persetujuan->labelPenyetuju($user)}. Status: Menunggu.",
            'sukses',
            'izin-'.$pengajuan->id
        );
    }

    /** Simpan perubahan pengajuan yang masih Menunggu. */
    public function update(Request $request, PengajuanIzin $pengajuan)
    {
        $user = $request->user();
        abort_unless($pengajuan->user_id === $user->id, 403);

        $validator = $this->validator($request, $user, $pengajuan);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        $data = $validator->validated();

        // Bukti baru opsional; disimpan dulu, bukti lama dihapus setelah berhasil
        $buktiBaru = $request->file('bukti')?->store("bukti/{$user->id}");
        $buktiLama = null;

        // Kunci baris agar tidak bentrok dengan penyetuju yang sedang memproses
        $berhasil = DB::transaction(function () use ($pengajuan, $data, $buktiBaru, &$buktiLama) {
            $p = PengajuanIzin::whereKey($pengajuan->id)->lockForUpdate()->first();
            if (! $p || $p->status !== 'Menunggu') {
                return false;
            }

            $isi = [
                'jenis'           => $data['jenis'],
                'tanggal_mulai'   => $data['tanggal_mulai'],
                'tanggal_selesai' => $data['tanggal_selesai'],
                'alasan'          => $data['alasan'],
            ];
            if ($buktiBaru) {
                $buktiLama = $p->bukti_path;
                $isi['bukti_path'] = $buktiBaru;
            }

            $p->update($isi);

            return true;
        });

        if (! $berhasil) {
            if ($buktiBaru) {
                Storage::delete($buktiBaru);
            }

            return $this->keRiwayat('Pengajuan sudah diproses penyetuju sehingga tidak dapat diubah.', 'galat');
        }

        if ($buktiLama) {
            Storage::delete($buktiLama);
        }

        $pengajuan->refresh();
        $this->kabariPenyetuju(
            $user,
            "Pengajuan {$pengajuan->jenis} diperbarui: {$user->nama_lengkap}",
            PengajuanBaru::rentang($pengajuan),
            route('persetujuan', ['id' => $pengajuan->id])
        );

        return $this->keRiwayat('Perubahan pengajuan disimpan. Status tetap Menunggu.', 'sukses', 'izin-'.$pengajuan->id);
    }

    /** Pegawai dapat membatalkan pengajuan yang belum diproses. */
    public function batal(Request $request, PengajuanIzin $pengajuan)
    {
        $user = $request->user();
        abort_unless($pengajuan->user_id === $user->id, 403);

        $jenis = $pengajuan->jenis;
        $rentang = PengajuanBaru::rentang($pengajuan);
        $bukti = $pengajuan->bukti_path;

        $berhasil = DB::transaction(function () use ($pengajuan) {
            $p = PengajuanIzin::whereKey($pengajuan->id)->lockForUpdate()->first();
            if (! $p || $p->status !== 'Menunggu') {
                return false;
            }
            $p->delete();

            return true;
        });

        if (! $berhasil) {
            return $this->keRiwayat('Pengajuan sudah diproses penyetuju sehingga tidak dapat dibatalkan.', 'galat');
        }

        if ($bukti) {
            Storage::delete($bukti);
        }

        $this->kabariPenyetuju($user, "Pengajuan {$jenis} dibatalkan: {$user->nama_lengkap}", $rentang, route('persetujuan'));

        return $this->keRiwayat("Pengajuan {$jenis} dibatalkan.");
    }

    // ================= Helper =================

    private function halaman(Request $request, ?PengajuanIzin $edit)
    {
        $user = $request->user();

        return view('pegawai.izin', [
            'judul'   => $edit ? 'Edit Izin / Sakit' : 'Ajukan Izin / Sakit',
            'riwayat' => PengajuanIzin::where('user_id', $user->id)->latest()->limit(3)->get(),
            'tujuan'  => $this->persetujuan->labelPenyetuju($user),
            'edit'    => $edit,
        ]);
    }

    /** Setelah berhasil, pegawai diarahkan ke menu Pengajuan. */
    private function keRiwayat(string $pesan, string $tipe = 'sukses', ?string $sorot = null)
    {
        return redirect()->route('pegawai.pengajuan')
            ->with($tipe, $pesan)
            ->with('sorot', $sorot);
    }

    /**
     * Validasi bersama untuk pengajuan baru dan edit.
     * $kecuali = pengajuan yang sedang diedit (bukti opsional, tidak dihitung bentrok).
     */
    private function validator(Request $request, User $user, ?PengajuanIzin $kecuali)
    {
        $hariIni = today();

        $validator = Validator::make($request->all(), [
            'jenis'           => ['required', Rule::in(['Izin', 'Sakit'])],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'alasan'          => ['required', 'string', 'max:1000'],
            'bukti'           => [$kecuali ? 'nullable' : 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
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

        // Batas waktu izin (terencana H-1 / mendadak hari H), sakit H+1, dan tidak bentrok dengan pengajuan lain
        $validator->after(function ($v) use ($request, $user, $hariIni, $kecuali) {
            if ($v->errors()->hasAny(['jenis', 'tanggal_mulai', 'tanggal_selesai'])) {
                return;
            }

            $mulai = Carbon::parse($request->input('tanggal_mulai'));
            $selesai = Carbon::parse($request->input('tanggal_selesai'));

            if ($request->input('jenis') === 'Izin') {
                // Izin terencana paling lambat H-1; izin mendadak (keadaan darurat) boleh untuk hari ini
                if ($mulai->lt($hariIni)) {
                    $v->errors()->add('tanggal_mulai', 'Izin tidak dapat diajukan untuk tanggal yang sudah lewat. Izin mendadak paling awal untuk hari ini.');
                } elseif ($mulai->isSameDay($hariIni) && $this->sudahDatangHariIni($user)) {
                    // Sudah presensi datang: yang dibutuhkan izin keluar (belum didukung), bukan izin sehari
                    $v->errors()->add('tanggal_mulai', 'Anda sudah presensi datang hari ini, sehingga izin untuk hari ini tidak dapat diajukan. Hubungi kepala sekolah bila harus meninggalkan sekolah.');
                }
            }

            if ($request->input('jenis') === 'Sakit') {
                if ($mulai->lt($hariIni->copy()->subDay())) {
                    $v->errors()->add('tanggal_mulai', 'Pengajuan sakit susulan paling lambat H+1 (tanggal mulai paling awal kemarin).');
                } elseif ($mulai->gt($hariIni)) {
                    $v->errors()->add('tanggal_mulai', 'Pengajuan sakit dimulai hari ini atau kemarin.');
                }
            }

            $bentrok = PengajuanIzin::where('user_id', $user->id)
                ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id))
                ->whereIn('status', ['Menunggu', 'Disetujui'])
                ->whereDate('tanggal_mulai', '<=', $selesai)
                ->whereDate('tanggal_selesai', '>=', $mulai)
                ->exists();

            if ($bentrok) {
                $v->errors()->add('tanggal_mulai', 'Sudah ada pengajuan lain pada rentang tanggal tersebut.');
            }
        });

        return $validator;
    }

    private function sudahDatangHariIni(User $user): bool
    {
        return Presensi::where('user_id', $user->id)
            ->whereDate('tanggal', today())
            ->whereNotNull('jam_datang')
            ->exists();
    }

    private function kabariPenyetuju(User $user, string $judul, string $keterangan, string $url): void
    {
        $penyetuju = $this->persetujuan->penyetujuUntuk($user);
        if ($penyetuju->isNotEmpty()) {
            Notification::send($penyetuju, new Pemberitahuan($judul, $keterangan, $url, 'pengajuan'));
        }
    }
}