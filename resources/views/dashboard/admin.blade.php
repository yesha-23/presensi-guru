@extends('layouts.app')

@section('konten')
{{-- ================= JUDUL ================= --}}
<div class="flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Kehadiran hari ini</h1>
        <p class="text-sm text-gray-500">{{ now()->translatedFormat('l, j F Y') }} · diperbarui {{ now()->format('H.i') }} WIB</p>
    </div>
    <div class="hidden lg:block">@include('partials.lonceng')</div>
</div>

{{-- ================= FILTER UNIT: chip, muat 1 baris di HP & laptop (tanpa geser) ================= --}}
@php
    $kelasChip = 'truncate rounded-full border px-3 py-2 text-center text-sm font-semibold md:px-5';
    $chipAktif = 'border-gray-900 bg-gray-900 text-white';
    $chipBiasa = 'border-garis bg-white text-gray-700 hover:bg-gray-50';
@endphp
<nav aria-label="Filter unit" class="mt-5 flex items-center gap-2">
    <span class="mr-1 hidden text-sm font-semibold text-gray-500 md:inline">Unit</span>

    <a href="{{ route('admin.dashboard') }}"
       class="{{ $kelasChip }} flex-1 md:flex-none {{ $unit ? $chipBiasa : $chipAktif }}"
       @if (! $unit) aria-current="true" @endif>
        Semua
    </a>
    @foreach ($units as $u)
        <a href="{{ route('admin.dashboard', ['unit' => $u->id]) }}"
           class="{{ $kelasChip }} flex-1 md:flex-none {{ $unit?->id === $u->id ? $chipAktif : $chipBiasa }}"
           @if ($unit?->id === $u->id) aria-current="true" @endif>
            {{ $u->label() }}
        </a>
    @endforeach
</nav>

@include('dashboard._kartu')

<div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
    {{-- Presensi pegawai --}}
    <div class="overflow-hidden rounded-2xl border border-garis bg-white">
        <div class="flex items-center justify-between px-4 py-3">
            <h2 class="font-bold">
                Presensi pegawai
                @if ($unit)
                    <span class="font-normal text-gray-500">· {{ $unit->label() }}</span>
                @endif
            </h2>
            <a href="{{ route('admin.presensi', ['unit' => $unit?->id]) }}" class="text-sm font-semibold text-merah underline">Lihat semua</a>
        </div>
        <div class="px-4 pb-3">
            @include('rekap._cari', ['tabel' => 'tabel-kehadiran'])
        </div>
        @include('dashboard._tabel', ['idTabel' => 'tabel-kehadiran'])
    </div>

    {{-- Menunggu persetujuan --}}
    <div class="rounded-2xl border border-garis bg-white p-5">
        <div class="flex items-center justify-between">
            <h2 class="font-bold">Menunggu persetujuan</h2>
            <span class="rounded-full bg-yellow-100 px-2.5 py-0.5 text-sm font-bold text-yellow-800">{{ $menunggu['jumlah'] }}</span>
        </div>
        <div class="mt-3 space-y-2">
            @forelse ($menunggu['item'] as $m)
                <div class="rounded-xl bg-krem p-3">
                    <div class="text-xs font-bold {{ $m['warna'] }}">{{ $m['jenis'] }}</div>
                    <div class="font-semibold">{{ $m['judul'] }}</div>
                    <div class="text-xs text-gray-500">{{ $m['ket'] }}</div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Tidak ada pengajuan yang menunggu.</p>
            @endforelse
        </div>
        <a href="{{ route('persetujuan') }}"
           class="mt-4 block rounded-xl border border-merah py-2.5 text-center text-sm font-bold text-merah hover:bg-merah-muda">
            Buka daftar persetujuan
        </a>
    </div>
</div>

@include('dashboard._segarkan')
@endsection