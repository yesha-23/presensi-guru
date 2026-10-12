@extends('layouts.app')

@section('konten')
@php
    use App\Models\Presensi;
    $efektif = $unit ? ($efektifUnit[$unit->id] ?? 0) : null;
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Laporan Presensi untuk PKG</h1>
        <p class="text-sm text-gray-500">
            Bahan pendukung penilaian kinerja guru, bukan nilai akhir PKG
            · data s.d. {{ $akhir->lt($mulai) ? '–' : $akhir->translatedFormat('j F Y') }}
            · {{ $efektif !== null ? $efektif.' hari efektif' : 'hari efektif per unit' }}
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('pkg.excel', request()->query()) }}"
           class="inline-flex items-center gap-2 rounded-xl border border-garis bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
            <i data-lucide="download" class="h-4 w-4"></i> Unduh Excel
        </a>
        <a href="{{ route('pkg.pdf', request()->query()) }}"
           class="inline-flex items-center gap-2 rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">
            <i data-lucide="download" class="h-4 w-4"></i> Unduh PDF
        </a>
    </div>
</div>

@include('rekap._tab')
@include('rekap._filter', ['modePkg' => true, 'kelompok' => 'Guru'])

{{-- Perbandingan antarunit (UR-27): satu baris di laptop --}}
@if ($perUnit->isNotEmpty())
    <div class="mt-3 flex flex-col gap-3 md:flex-row">
        @foreach ($perUnit as $namaUnit => $u)
            @php $baik = $u['persen'] >= 95; @endphp
            <div class="min-w-0 rounded-2xl border border-garis bg-white p-5 md:flex-1">
                <div class="flex items-center justify-between">
                    <span class="text-lg font-bold">{{ $namaUnit }}</span>
                    <span class="rounded-full bg-krem px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ $u['jumlah'] }} guru</span>
                </div>

                <div class="mt-3 text-xs text-gray-500">Rata-rata kehadiran</div>
                <div class="flex items-end justify-between gap-3">
                    <span class="text-3xl font-extrabold {{ $baik ? 'text-blue-800' : 'text-orange-700' }}">
                        {{ number_format($u['persen'], 1, ',', '.') }}%
                    </span>
                    <span class="pb-1 text-sm text-gray-500">
                        alpa <b class="text-gray-800">{{ number_format($u['persen_alpa'], 1, ',', '.') }}%</b>
                    </span>
                </div>

                <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full {{ $baik ? 'bg-blue-700' : 'bg-orange-600' }}" style="width: {{ $u['persen'] }}%"></div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="mt-4 overflow-hidden rounded-2xl border border-garis bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="bg-krem text-gray-600">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold">Guru</th>
                    <th class="px-3 py-3 font-semibold">Hadir</th>
                    <th class="px-3 py-3 font-semibold">Tugas khusus</th>
                    <th class="px-3 py-3 font-semibold">Izin</th>
                    <th class="px-3 py-3 font-semibold">Sakit</th>
                    <th class="px-3 py-3 font-semibold">Alpa</th>
                    <th class="px-3 py-3 text-left font-semibold">Terlambat</th>
                    <th class="px-3 py-3 text-left font-semibold">Kehadiran</th>
                    <th class="px-4 py-3 text-right font-semibold">% Alpa</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @forelse ($baris as $r)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $r['user']->nama_lengkap }}</div>
                            @if (! $unit) <div class="text-xs text-gray-500">{{ $r['user']->unit?->label() }}</div> @endif
                        </td>
                        <td class="px-3 py-3 text-center">{{ $r['hadir'] }}</td>
                        <td class="px-3 py-3 text-center">{{ $r['tugas'] }}</td>
                        <td class="px-3 py-3 text-center">{{ $r['izin'] }}</td>
                        <td class="px-3 py-3 text-center">{{ $r['sakit'] }}</td>
                        <td class="px-3 py-3 text-center font-bold">{{ $r['alpa'] }}</td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            {{ $r['terlambat'] }} kali{{ $r['detik_terlambat'] ? ' · '.Presensi::formatDetik($r['detik_terlambat']) : '' }}
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-3">
                                <div class="h-2 w-32 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full {{ $r['persen'] >= 95 ? 'bg-blue-700' : 'bg-orange-600' }}" style="width: {{ $r['persen'] }}%"></div>
                                </div>
                                <span class="font-bold tabular-nums">{{ number_format($r['persen'], 1, ',', '.') }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($r['persen_alpa'], 1, ',', '.') }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-10 text-center text-gray-500">Tidak ada data guru pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 rounded-2xl border border-garis bg-white p-4 text-sm text-gray-600">
    <b>Kehadiran</b> = (Hadir + Tugas Khusus) ÷ Hari Efektif × 100%. Izin dan sakit tetap masuk hari efektif dan ditampilkan terpisah.<br>
    <b>% Alpa</b> = Alpa ÷ Hari Efektif × 100%, sebagai ukuran kedisiplinan.
</div>
@endsection