@extends('layouts.app')

@section('konten')
<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Kehadiran hari ini</h1>
        <p class="text-sm text-gray-500">{{ now()->translatedFormat('l, j F Y') }} · diperbarui {{ now()->format('H.i') }} WIB</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="GET">
            <label class="flex h-11 items-center gap-2 rounded-xl border border-garis bg-white px-3 text-sm">
                <span class="font-semibold">Unit</span>
                <select name="unit" onchange="this.form.submit()" class="h-full border-0 bg-transparent p-0 text-sm outline-none focus:ring-0">
                    <option value="">Semua unit</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ $u->label() }}</option>
                    @endforeach
                </select>
            </label>
        </form>
        <a href="{{ route('admin.dashboard') }}" class="inline-flex h-11 items-center gap-2 rounded-xl border border-garis bg-white px-4 text-sm font-semibold hover:bg-gray-50">
            <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Reset
        </a>
        <div class="hidden lg:block">@include('partials.lonceng')</div>
    </div>
</div>

@include('dashboard._kartu')

<div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
    {{-- Presensi pegawai --}}
    <div class="overflow-hidden rounded-2xl border border-garis bg-white">
        <div class="flex items-center justify-between px-4 py-3">
            <h2 class="font-bold">Presensi pegawai</h2>
            <a href="{{ route('admin.presensi', ['unit' => $unit?->id]) }}" class="text-sm font-semibold text-merah underline">Lihat semua</a>
        </div>
        @include('dashboard._tabel')
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