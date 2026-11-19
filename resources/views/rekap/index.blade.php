@extends('layouts.app')

@section('konten')
@php
    use App\Services\RekapService as R;
    $efektif = $unit ? ($efektifUnit[$unit->id] ?? 0) : null;
    $th = 'sticky top-0 z-20 px-2 py-2 text-center font-semibold leading-tight';
    $teksBulat = [
        R::BULAT_SETENGAH      => 'ke 0,5 jam terdekat',
        R::BULAT_SETENGAH_ATAS => 'ke atas, kelipatan 0,5 jam',
        R::BULAT_DESIMAL       => '1 angka desimal',
    ][\App\Models\Pengaturan::ambil('pembulatan_jam_rekap', R::BULAT_SETENGAH)] ?? 'ke 0,5 jam terdekat';

    // Kolom No dan Nama tetap terlihat saat tabel digeser ke samping
    $kolomNo = 'sticky left-0 w-10 min-w-[2.5rem] max-w-[2.5rem]';
    $kolomNama = 'sticky left-10 min-w-[9rem] md:min-w-[14rem] shadow-[1px_0_0_0_#e2dcef]';
    $jumlahKolom = count($format['kolom']) + 3;
@endphp

<div>
    <h1 class="text-2xl font-extrabold lg:text-3xl">Rekap Presensi Guru & Staf</h1>
    <p class="text-sm text-gray-500">
        Format rekap yayasan · {{ $label }}
        · {{ $efektif !== null ? $efektif.' hari efektif' : 'hari efektif per unit' }}
        · data s.d. {{ $akhir->lt($mulai) ? '–' : $akhir->translatedFormat('j F Y') }}
    </p>
</div>

@include('rekap._tab', ['unduh' => [
    ['Unduh PDF', 'PDF', route('rekap.pdf', request()->query()), false],
    ['Unduh Excel', 'Excel', route('rekap.excel', request()->query()), true],
]])
@include('rekap._filter')

<div class="mt-3 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    @include('rekap._cari', ['tabel' => 'tabel-rekap'])
    <div class="flex items-center gap-2 text-xs text-gray-500">
        <span class="h-3 w-3 rounded bg-gray-200"></span> Kolom diisi manual (di luar lingkup sistem)
    </div>
</div>

<div class="mt-2 overflow-hidden rounded-2xl border border-garis bg-white">
    @if ($unit)
        <div class="border-b border-garis bg-krem/60 px-4 py-2 text-center text-sm font-bold">
            {{ $format['judul'] }} · {{ $format['subjudul'] }}
        </div>
    @endif
    {{-- Area gulir sendiri: judul kolom tetap di atas, No & Nama tetap di kiri --}}
    <div class="max-h-[70vh] overflow-auto">
        <table id="tabel-rekap" class="w-full text-sm" style="min-width: {{ 24 + count($format['kolom']) * 4.5 }}rem">
            <thead class="text-white">
                <tr>
                    <th class="sticky left-0 top-0 z-30 w-10 min-w-[2.5rem] max-w-[2.5rem] bg-gray-900 px-2 py-2 text-center font-semibold">No</th>
                    <th class="sticky left-10 top-0 z-30 min-w-[9rem] bg-gray-900 px-3 py-2 text-left font-semibold md:min-w-[14rem]">Nama</th>
                    @foreach ($format['kolom'] as [$judulKolom, $satuan, $isiKolom])
                        <th class="{{ $th }} {{ $isiKolom ? 'bg-gray-900' : 'bg-gray-700' }}">
                            {{ $judulKolom }}<div class="text-xs font-normal opacity-70">{{ $satuan }}</div>
                        </th>
                    @endforeach
                    <th class="sticky top-0 z-20 bg-gray-900 px-3 py-2 text-left font-semibold">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @foreach (['Guru' => 'A. GURU', 'Staf' => 'B. STAF'] as $kel => $judulKel)
                    @php $isi = $baris->where('kelompok', $kel)->values(); @endphp
                    @if ($isi->isNotEmpty())
                        <tr data-grup="{{ $kel }}">
                            <td colspan="{{ $jumlahKolom }}" class="bg-merah-muda px-3 py-2 font-bold text-merah">
                                <span class="sticky left-3">{{ $judulKel }}</span>
                            </td>
                        </tr>
                        @foreach ($isi as $i => $r)
                            <tr data-nama="{{ mb_strtolower($r['user']->nama_lengkap) }}" data-grup-baris="{{ $kel }}">
                                <td class="{{ $kolomNo }} z-10 bg-white px-2 py-2.5 text-center text-gray-500">{{ $i + 1 }}</td>
                                <td class="{{ $kolomNama }} z-10 bg-white px-3 py-2.5">
                                    {{ $r['user']->nama_lengkap }}
                                    @if (! $unit || $r['user']->unit_id !== $unit->id)
                                        <span class="text-xs text-gray-400">· {{ $r['user']->unit?->label() }}</span>
                                    @endif
                                    @if ($r['user']->trashed() || $r['user']->status !== 'Aktif') <span class="text-xs text-gray-400">(arsip)</span> @endif
                                </td>
                                @foreach ($format['kolom'] as [$judulKolom, $satuan, $isiKolom])
                                    @if ($isiKolom)
                                        <td class="px-2 py-2.5 text-center {{ $isiKolom === 'hadir' ? 'font-bold' : '' }}">{{ R::nilaiKolom($r, $isiKolom) }}</td>
                                    @else
                                        <td class="bg-gray-100"></td>
                                    @endif
                                @endforeach
                                <td class="px-3 py-2.5">{{ $r['keterangan'] }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach

                @if ($baris->isEmpty())
                    <tr><td colspan="{{ $jumlahKolom }}" class="px-4 py-10 text-center text-gray-500">Tidak ada pegawai pada filter ini.</td></tr>
                @else
                    <tr data-cari-kosong hidden>
                        <td colspan="{{ $jumlahKolom }}" class="px-4 py-10 text-gray-500">
                            <span class="sticky left-4">Nama pegawai tidak ditemukan.</span>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<p class="mt-2 text-xs text-gray-500">
    Jam terlambat dan lembur dibulatkan sesuai Pengaturan &rsaquo; Rekap
    ({{ $teksBulat }}).
    Tugas khusus di hari libur dihitung di kolom kegiatan dan tidak mengurangi alpa.
</p>

{{-- Blok tanda tangan --}}
<div class="mt-4 grid gap-6 rounded-2xl border border-garis bg-white p-6 text-center text-sm sm:grid-cols-3">
    <div>
        <div class="text-gray-500">{{ $kota }}, {{ now()->translatedFormat('j F Y') }}</div>
        <div class="mt-1">Kepala Sekolah</div>
        <div class="mt-12 font-bold">{{ $penandatangan['kepsek'] ?? '..............................' }}</div>
    </div>
    <div>
        <div class="text-gray-500">Divalidasi,</div>
        <div class="mt-1">Kabag Adm. Umum</div>
        <div class="mt-12 font-bold">{{ $penandatangan['kabag'] ?? '..............................' }}</div>
    </div>
    <div>
        <div class="text-gray-500">Mengetahui,</div>
        <div class="mt-1">Ketua Yayasan</div>
        <div class="mt-12 font-bold">{{ $penandatangan['ketua'] ?? '..............................' }}</div>
    </div>
</div>
@endsection
