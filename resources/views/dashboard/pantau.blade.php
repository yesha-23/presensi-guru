@extends('layouts.app')

@section('konten')
@php
    $chip = [
        ''           => ['Semua', 'Semua', $ringkas['total']],
        'hadir'      => ['Hadir', 'Hadir', $ringkas['hadir']],
        'terlambat'  => ['Terlambat', 'Terlambat', $ringkas['terlambat']],
        'belum'      => ['Belum presensi', 'Belum', $ringkas['belum']],
        'izin_sakit' => ['Izin / Sakit', 'Izin/Sakit', $ringkas['izin_sakit']],
        'tugas'      => ['Tugas khusus', 'Tugas', $ringkas['tugas']],
    ];
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Pantau Kehadiran</h1>
        <p class="text-sm text-gray-500">{{ $unit->nama }} · {{ now()->translatedFormat('l, j F Y') }} · diperbarui {{ now()->format('H.i') }} WIB</p>
    </div>
    <div class="hidden lg:block">@include('partials.lonceng')</div>
</div>

@include('dashboard._kartu')

{{-- HP: 3 kolom × 2 baris. Laptop: satu baris --}}
<div class="mt-5 grid grid-cols-3 gap-2 md:flex md:flex-wrap">
    @foreach ($chip as $kunci => [$teks, $teksPendek, $jumlah])
        @php $aktif = ($filter ?? '') === $kunci; @endphp
        <a href="{{ route('kepsek.pantau', $kunci !== '' ? ['status' => $kunci] : []) }}"
           class="truncate rounded-full border px-2 py-2 text-center text-xs font-semibold md:px-4 md:text-sm
                  {{ $aktif ? 'border-gray-900 bg-gray-900 text-white' : 'border-garis bg-white text-gray-700 hover:bg-gray-50' }}">
            <span class="md:hidden">{{ $teksPendek }}</span>
            <span class="hidden md:inline">{{ $teks }}</span>
            · {{ $jumlah }}
        </a>
    @endforeach
</div>

<div class="mt-3">
    @include('rekap._cari', ['tabel' => 'tabel-kehadiran'])
</div>

<div class="mt-2 overflow-hidden rounded-2xl border border-garis bg-white">
    @include('dashboard._tabel', ['idTabel' => 'tabel-kehadiran'])
</div>

@include('dashboard._segarkan')
@endsection