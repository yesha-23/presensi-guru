<?php

namespace App\Services;

use App\Models\KalenderEvent;
use App\Models\Pengaturan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class KalenderService
{
    public const KATEGORI = ['Libur Nasional', 'Cuti Bersama', 'Libur Internal', 'Kegiatan Sekolah', 'Hari Kerja Tambahan'];
    public const BERLAKU = ['Guru dan Staf', 'Siswa'];

    /** Entri yang berlaku untuk satu unit pada rentang tanggal (unit_id null = semua unit). */
    public function entri(?int $unitId, Carbon $mulai, Carbon $selesai): Collection
    {
        return KalenderEvent::query()
            ->where(fn ($q) => $q->whereNull('unit_id')->when($unitId, fn ($q) => $q->orWhere('unit_id', $unitId)))
            ->whereDate('tanggal_mulai', '<=', $selesai)
            ->whereDate('tanggal_selesai', '>=', $mulai)
            ->orderBy('tanggal_mulai')
            ->get();
    }

    public function hariKerja(): array
    {
        return array_map('intval', array_map('trim', explode(',', (string) Pengaturan::ambil('hari_kerja', '1,2,3,4,5'))));
    }

    /** Jenis hari guru & staf: 'hkt', 'kerja', 'libur', atau 'nonkerja' (HKT menang, lalu hari kerja, lalu libur). */
    public function jenisHari(Carbon $tanggal, Collection $entri): string
    {
        $berlaku = $entri->filter(fn ($e) => $e->berlaku_untuk === 'Guru dan Staf'
            && $tanggal->between($e->tanggal_mulai->copy()->startOfDay(), $e->tanggal_selesai->copy()->endOfDay()));

        if ($berlaku->contains('kategori', KalenderEvent::HARI_KERJA_TAMBAHAN)) {
            return 'hkt';
        }
        if (! in_array($tanggal->dayOfWeekIso, $this->hariKerja(), true)) {
            return 'nonkerja';
        }
        if ($berlaku->whereIn('kategori', KalenderEvent::KATEGORI_LIBUR)->isNotEmpty()) {
            return 'libur';
        }

        return 'kerja';
    }

    /** Hari efektif = hari kerja − libur guru & staf + hari kerja tambahan (SR-F26). */
    public function hariEfektif(?int $unitId, Carbon $mulai, Carbon $selesai): array
    {
        $entri = $this->entri($unitId, $mulai, $selesai);
        $hasil = ['kerja' => 0, 'libur' => 0, 'hkt' => 0];

        foreach (CarbonPeriod::create($mulai->copy()->startOfDay(), $selesai->copy()->startOfDay()) as $tanggal) {
            $jenis = $this->jenisHari($tanggal, $entri);
            if (isset($hasil[$jenis])) {
                $hasil[$jenis]++;
            }
        }

        return $hasil + ['total' => $hasil['kerja'] + $hasil['hkt']];
    }

    // ---------- Aturan validasi entri (dipakai form & impor) ----------

    public static function aturan(): array
    {
        $hkt = KalenderEvent::HARI_KERJA_TAMBAHAN;

        return [
            'nama'            => ['required', 'string', 'max:150'],
            'kategori'        => ['required', Rule::in(self::KATEGORI)],
            'berlaku_untuk'   => ['required', Rule::in(self::BERLAKU)],
            'unit_id'         => ['nullable', 'exists:units,id'],
            'tanggal_mulai'   => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'jam_masuk'       => ['nullable', "required_if:kategori,{$hkt}", 'date_format:H:i'],
            'jam_pulang'      => ['nullable', "required_if:kategori,{$hkt}", 'date_format:H:i', 'after:jam_masuk'],
        ];
    }

    public static function pesan(): array
    {
        return [
            'required'                       => ':attribute wajib diisi.',
            'max'                            => ':attribute maksimal :max karakter.',
            'in'                             => ':attribute tidak valid.',
            'unit_id.exists'                 => 'Unit harus Semua, SD, atau KBTK.',
            'tanggal_mulai.required'         => 'Tanggal wajib diisi.',
            'tanggal_selesai.required'       => 'Tanggal wajib diisi.',
            'date_format'                    => 'Format :attribute tidak dikenali.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai sebelum tanggal mulai.',
            'required_if'                    => ':attribute wajib diisi untuk Hari Kerja Tambahan.',
            'jam_pulang.after'               => 'Jam pulang harus setelah jam masuk.',
        ];
    }

    public static function atribut(): array
    {
        return [
            'nama' => 'Nama entri', 'kategori' => 'Kategori', 'berlaku_untuk' => 'Berlaku untuk',
            'unit_id' => 'Unit', 'tanggal_mulai' => 'Tanggal mulai', 'tanggal_selesai' => 'Tanggal selesai',
            'jam_masuk' => 'Jam masuk', 'jam_pulang' => 'Jam pulang',
        ];
    }

    /** Hari Kerja Tambahan hanya masuk akal untuk guru & staf. */
    public static function cekTambahan(Validator $validator, array $data): void
    {
        if (($data['kategori'] ?? null) === KalenderEvent::HARI_KERJA_TAMBAHAN
            && ($data['berlaku_untuk'] ?? null) !== 'Guru dan Staf') {
            $validator->errors()->add('berlaku_untuk', 'Hari Kerja Tambahan harus berlaku untuk Guru dan Staf.');
        }
    }
}