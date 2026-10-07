@extends('layouts.app')

@section('konten')
@php
    $warna = [
        'Tepat Waktu'  => 'bg-blue-50 text-blue-800',
        'Terlambat'    => 'bg-orange-100 text-orange-800',
        'Izin'         => 'bg-purple-100 text-purple-800',
        'Sakit'        => 'bg-purple-100 text-purple-800',
        'Tugas Khusus' => 'bg-emerald-50 text-emerald-800',
        'Alpa'         => 'bg-red-100 text-red-800',
        'Libur'        => 'bg-gray-100 text-gray-700',
        'Menunggu'     => 'bg-yellow-100 text-yellow-800',
        'Disetujui'    => 'bg-emerald-50 text-emerald-800',
        'Ditolak'      => 'bg-red-100 text-red-800',
    ];
    $statusHariIni = $hariIni?->status_datang ?? $hariIni?->status_harian;
@endphp

{{-- Header merah --}}
<section class="-mx-4 -mt-6 bg-merah px-4 pb-24 pt-6 text-white lg:mx-0 lg:mt-0 lg:rounded-3xl lg:px-8">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-white/80">{{ now()->translatedFormat('l, j F Y') }}</p>
            <h1 class="mt-1 text-2xl font-extrabold lg:text-3xl">
                {{ $salam }}, {{ $user->nama_panggilan ?? $user->nama_lengkap }}
            </h1>
            <p class="mt-1 text-sm text-white/80">
                {{ $user->kategori }} · {{ $user->unit->nama }}
                @if ($jamKerja)
                    · {{ str_replace(':', '.', substr($jamKerja->jam_masuk, 0, 5)) }}–{{ str_replace(':', '.', substr($jamKerja->jam_pulang, 0, 5)) }}
                @endif
            </p>
        </div>
        <div class="hidden lg:block">@include('partials.lonceng', ['terang' => true])</div>
    </div>
</section>

<div class="relative -mt-16 space-y-4 lg:mx-auto lg:max-w-3xl">
    {{-- Presensi hari ini --}}
    <div class="rounded-3xl border border-garis bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="font-bold">Presensi hari ini</h2>
            @if ($statusHariIni)
                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $warna[$statusHariIni] ?? 'bg-gray-100' }}">
                    {{ $statusHariIni }}
                </span>
            @endif
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-krem p-4">
                <div class="text-sm text-gray-500">Datang</div>
                <div class="text-2xl font-extrabold tabular-nums">{{ $hariIni?->jam_datang ?? '--:--:--' }}</div>
            </div>
            <div class="rounded-2xl bg-krem p-4">
                <div class="text-sm text-gray-500">Pulang</div>
                <div class="text-2xl font-extrabold tabular-nums {{ $hariIni?->jam_pulang ? '' : 'text-gray-400' }}">
                    {{ $hariIni?->jam_pulang ?? '--:--:--' }}
                </div>
            </div>
        </div>

        <a href="{{ route('presensi.scan') }}"
           class="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-merah py-3.5 font-bold text-white hover:bg-merah-tua">
            <i data-lucide="scan-face" class="h-5 w-5"></i> Presensi
        </a>
    </div>

    {{-- Menu cepat --}}
    <div class="grid grid-cols-3 gap-3">
        @foreach ([
            ['Izin / Sakit', 'pegawai.izin', 'file'],
            ['Lembur', 'pegawai.lembur', 'clock'],
            ['Surat Tugas', 'pegawai.surat-tugas', 'briefcase'],
        ] as [$label, $rute, $ikon])
            <a href="{{ route($rute) }}"
               class="flex flex-col items-center gap-2 rounded-2xl border border-garis bg-white p-4 text-center text-sm font-semibold hover:bg-gray-50">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-merah-muda text-merah">
                    <i data-lucide="{{ $ikon }}" class="h-5 w-5"></i>
                </span>
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Rekap bulan ini --}}
    <div class="rounded-3xl border border-garis bg-white p-5">
        <div class="flex items-center justify-between">
            <h2 class="font-bold">Rekap {{ now()->translatedFormat('F Y') }}</h2>
            <a href="{{ route('pegawai.riwayat') }}" class="text-sm font-semibold text-merah underline">Lihat riwayat</a>
        </div>
        <div class="mt-4 grid grid-cols-4 text-center">
            @foreach ($rekap as $label => $jumlah)
                <div>
                    <div class="text-2xl font-extrabold {{ $label === 'Terlambat' ? 'text-orange-800' : '' }}">{{ $jumlah }}</div>
                    <div class="text-xs text-gray-500">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Pengajuan terakhir --}}
    @if ($pengajuanTerakhir)
        <div class="flex items-center justify-between rounded-3xl border border-garis bg-white p-5">
            <div>
                <div class="font-bold">
                    {{ $pengajuanTerakhir->jenis }} · {{ $pengajuanTerakhir->tanggal_mulai->translatedFormat('j F Y') }}
                </div>
                <div class="text-sm text-gray-500">Pengajuan terakhir</div>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $warna[$pengajuanTerakhir->status] }}">
                {{ $pengajuanTerakhir->status }}
            </span>
        </div>
    @endif
</div>
@endsection