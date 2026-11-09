@extends('layouts.app')

@section('konten')
@php
    use App\Services\RekapService as R;
    $efektif = $unit ? ($efektifUnit[$unit->id] ?? 0) : null;
    $th = 'px-2 py-2 text-center font-semibold leading-tight';
    $manual = 'bg-gray-700';
    $teksBulat = [
        R::BULAT_SETENGAH      => 'ke 0,5 jam terdekat',
        R::BULAT_SETENGAH_ATAS => 'ke atas, kelipatan 0,5 jam',
        R::BULAT_DESIMAL       => '1 angka desimal',
    ][\App\Models\Pengaturan::ambil('pembulatan_jam_rekap', R::BULAT_SETENGAH)] ?? 'ke 0,5 jam terdekat';
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Rekap Presensi Guru & Staf</h1>
        <p class="text-sm text-gray-500">
            Format rekap yayasan · {{ $label }}
            · {{ $efektif !== null ? $efektif.' hari efektif' : 'hari efektif per unit' }}
            · data s.d. {{ $akhir->lt($mulai) ? '–' : $akhir->translatedFormat('j F Y') }}
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('rekap.pdf', request()->query()) }}"
           class="inline-flex items-center gap-2 rounded-xl border border-garis bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
            <i data-lucide="download" class="h-4 w-4"></i> Unduh PDF
        </a>
        <a href="{{ route('rekap.excel', request()->query()) }}"
           class="inline-flex items-center gap-2 rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">
            <i data-lucide="download" class="h-4 w-4"></i> Unduh Excel
        </a>
    </div>
</div>

@include('rekap._tab')
@include('rekap._filter')

<div class="mt-3 flex items-center gap-2 text-xs text-gray-500">
    <span class="h-3 w-3 rounded bg-gray-200"></span> Kolom diisi manual (di luar lingkup sistem)
</div>

<div class="mt-2 overflow-hidden rounded-2xl border border-garis bg-white">
    @if ($unit)
        <div class="border-b border-garis bg-krem/60 px-4 py-2 text-center text-sm font-bold">
            {{ $format['judul'] }} · {{ $format['subjudul'] }}
        </div>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full text-sm" style="min-width: {{ 24 + count($format['kolom']) * 4.5 }}rem">
            <thead class="bg-gray-900 text-white">
                <tr>
                    <th class="{{ $th }} w-10">No</th>
                    <th class="px-3 py-2 text-left font-semibold">Nama</th>
                    @foreach ($format['kolom'] as [$judulKolom, $satuan, $isiKolom])
                        <th class="{{ $th }} {{ $isiKolom ? '' : $manual }}">
                            {{ $judulKolom }}<div class="text-xs font-normal opacity-70">{{ $satuan }}</div>
                        </th>
                    @endforeach
                    <th class="px-3 py-2 text-left font-semibold">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @foreach (['Guru' => 'A. GURU', 'Staf' => 'B. STAF'] as $kel => $judulKel)
                    @php $isi = $baris->where('kelompok', $kel)->values(); @endphp
                    @if ($isi->isNotEmpty())
                        <tr><td colspan="{{ count($format['kolom']) + 3 }}" class="bg-merah-muda px-3 py-2 font-bold text-merah">{{ $judulKel }}</td></tr>
                        @foreach ($isi as $i => $r)
                            <tr>
                                <td class="px-2 py-2.5 text-center text-gray-500">{{ $i + 1 }}</td>
                                <td class="px-3 py-2.5">
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
                    <tr><td colspan="{{ count($format['kolom']) + 3 }}" class="px-4 py-10 text-center text-gray-500">Tidak ada pegawai pada filter ini.</td></tr>
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
        <div class="text-gray-500">{{ $kota }}, [tanggal]</div>
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