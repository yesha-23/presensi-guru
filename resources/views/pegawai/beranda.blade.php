@extends('layouts.app')

@section('konten')
@php
    $jamFormat = fn ($carbon) => $carbon->format('H.i');
    $teksJadwal = $kondisi
        ? $kondisi['status']
        : ($jadwalHariIni
            ? $jamFormat($jadwalHariIni['masuk']).'–'.$jamFormat($jadwalHariIni['pulang']).($jadwalHariIni['hkt'] ? ' · Hari kerja tambahan' : '')
            : 'Bukan hari kerja');

    $lengkap = $hariIni?->jam_datang && $hariIni?->jam_pulang;
    $lewatBatas = $batasDatang && ! $hariIni?->jam_datang && now()->gt($batasDatang);
@endphp

{{-- ================= HEADER MERAH ================= --}}
<section class="-mx-4 -mt-6 bg-merah px-4 pb-24 pt-6 text-white lg:mx-0 lg:mt-0 lg:rounded-3xl lg:px-8 lg:py-7">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-white/80">{{ now()->translatedFormat('l, j F Y') }}</p>
            <h1 class="mt-1 text-2xl font-extrabold lg:text-3xl">
                {{ $salam }}, {{ $user->nama_panggilan ?? $user->nama_lengkap }}
            </h1>
            <p class="mt-1 text-sm text-white/80">
                {{ $user->kategori }} · {{ $user->unit->nama }} · {{ $teksJadwal }}
            </p>
        </div>
        <div class="hidden lg:block">@include('partials.lonceng', ['terang' => true])</div>
    </div>
</section>

{{-- ================= ISI ================= --}}
{{-- HP: satu kolom menumpang header. Laptop: dua kolom di bawah header. --}}
<div class="relative -mt-16 grid gap-4 lg:mt-6 lg:grid-cols-3 lg:items-start lg:gap-6">

    {{-- Presensi hari ini (kiri atas di laptop) --}}
    {{-- lg:self-stretch + flex-col: kartu memanjang setinggi kolom kanan, tidak ada ruang kosong di bawahnya --}}
    <div class="flex flex-col rounded-3xl border border-garis bg-white p-5 shadow-sm lg:col-span-2 lg:col-start-1 lg:row-start-1 lg:self-stretch lg:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-bold lg:text-lg">Presensi hari ini</h2>
            <div class="flex gap-1.5">
                @if ($hariIni?->status_datang)
                    <x-lencana :status="$hariIni->status_datang" />
                @elseif ($hariIni?->status_harian)
                    <x-lencana :status="$hariIni->status_harian" />
                @endif
                @if ($hariIni?->jam_pulang && $hariIni->pulang_cepat)
                    <x-lencana status="Pulang Cepat" />
                @endif
            </div>
        </div>

        @if ($kondisi)
            <div class="mt-4 rounded-2xl bg-krem p-4 text-sm">
                <b>Tidak perlu presensi.</b> {{ $kondisi['keterangan'] }}
            </div>
        @endif

        {{-- flex-1: kotak Datang & Pulang mengisi sisa tinggi kartu di laptop --}}
        <div class="mt-4 grid flex-1 grid-cols-2 gap-3">
            <div class="flex flex-col justify-center rounded-2xl bg-krem p-4 lg:p-5">
                <div class="text-sm text-gray-500">Datang</div>
                <div class="text-2xl font-extrabold tabular-nums lg:text-4xl {{ $hariIni?->jam_datang ? '' : 'text-gray-400' }}">
                    {{ $hariIni?->jam_datang ?? '--:--:--' }}
                </div>
                @if ($hariIni?->status_datang === 'Terlambat')
                    <div class="mt-1 text-xs text-orange-800 lg:text-sm">Terlambat {{ \App\Models\Presensi::formatDetik($hariIni->detik_terlambat) }}</div>
                @elseif ($jadwalHariIni)
                    <div class="mt-1 text-xs text-gray-500 lg:text-sm">
                        Jadwal {{ $jadwalHariIni['masuk']->format('H.i') }} · batas {{ $batasDatang->format('H.i') }}
                    </div>
                @endif
            </div>

            <div class="flex flex-col justify-center rounded-2xl bg-krem p-4 lg:p-5">
                <div class="text-sm text-gray-500">Pulang</div>
                <div class="text-2xl font-extrabold tabular-nums lg:text-4xl {{ $hariIni?->jam_pulang ? '' : 'text-gray-400' }}">
                    {{ $hariIni?->jam_pulang ?? '--:--:--' }}
                </div>
                @if ($hariIni?->pulang_cepat)
                    <div class="mt-1 text-xs text-orange-800 lg:text-sm">Lebih awal {{ \App\Models\Presensi::formatDetik($hariIni->detik_pulang_cepat) }}</div>
                @elseif ($jadwalHariIni)
                    <div class="mt-1 text-xs text-gray-500 lg:text-sm">Jadwal {{ $jadwalHariIni['pulang']->format('H.i') }}</div>
                @endif
            </div>
        </div>

        @if ($lengkap)
            <div class="mt-4 rounded-2xl bg-emerald-50 py-3.5 text-center text-sm font-semibold text-emerald-800">
                Presensi hari ini sudah lengkap
            </div>
        @elseif ($lewatBatas)
            <div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm text-red-800">
                <b>Batas presensi datang ({{ $batasDatang->format('H.i') }}) sudah lewat.</b>
                Hari ini akan tercatat Alpa, kecuali ada izin, sakit, atau tugas khusus yang disetujui.
                <a href="{{ route('pegawai.izin') }}" class="font-semibold underline">Ajukan sakit</a>
            </div>
        @elseif (! $kondisi)
            <a href="{{ route('presensi.scan') }}"
               class="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-merah py-3.5 font-bold text-white hover:bg-merah-tua">
                <i data-lucide="scan-face" class="h-5 w-5"></i>
                {{ $hariIni?->jam_datang ? 'Presensi pulang' : 'Presensi datang' }}
            </a>
        @else
            {{-- Ada tugas khusus / izin / sakit / libur: tombol tetap tampil tetapi nonaktif --}}
            <button type="button" disabled aria-disabled="true"
                    class="mt-4 flex w-full cursor-not-allowed items-center justify-center gap-2 rounded-2xl bg-gray-200 py-3.5 font-semibold text-gray-500">
                <i data-lucide="scan-face" class="h-5 w-5"></i>
                {{ ($kondisi['status'] ?? null) === 'Libur' ? 'Hari libur · tidak perlu presensi' : 'Tidak perlu presensi hari ini' }}
            </button>
        @endif
    </div>

    {{-- Menu cepat (kanan atas di laptop) --}}
    <div class="lg:col-start-3 lg:row-start-1">
        <h2 class="mb-3 hidden font-bold lg:block">Pengajuan & tugas</h2>
        <div class="grid grid-cols-3 gap-3 lg:grid-cols-1">
            @foreach ([
                ['Izin / Sakit', 'pegawai.izin', 'file', 'Ajukan izin atau sakit dengan bukti'],
                ['Lembur', 'pegawai.lembur', 'clock', 'Ajukan lembur ke kepala sekolah'],
                ['Surat Tugas', 'pegawai.surat-tugas', 'briefcase', 'Lihat dan unduh surat tugas'],
            ] as [$label, $rute, $ikon, $keterangan])
                <a href="{{ route($rute) }}"
                   class="flex flex-col items-center gap-2 rounded-2xl border border-garis bg-white p-4 text-center text-sm font-semibold hover:bg-gray-50 lg:flex-row lg:gap-4 lg:text-left">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-merah-muda text-merah">
                        <i data-lucide="{{ $ikon }}" class="h-5 w-5"></i>
                    </span>
                    <span>
                        {{ $label }}
                        <span class="hidden text-xs font-normal text-gray-500 lg:block">{{ $keterangan }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Rekap bulan ini (kiri bawah di laptop) --}}
    <div class="rounded-3xl border border-garis bg-white p-5 lg:col-span-2 lg:col-start-1 lg:row-start-2 lg:p-6">
        <div class="flex items-center justify-between">
            <h2 class="font-bold lg:text-lg">Rekap {{ now()->translatedFormat('F Y') }}</h2>
            <a href="{{ route('pegawai.riwayat') }}" class="text-sm font-semibold text-merah underline">Lihat riwayat</a>
        </div>
        <div class="mt-4 grid grid-cols-4 gap-2 text-center">
            @foreach ($rekap as $label => $jumlah)
                <div class="lg:rounded-2xl lg:bg-krem lg:py-4">
                    <div class="text-2xl font-extrabold lg:text-3xl {{ $label === 'Terlambat' ? 'text-orange-800' : '' }}">{{ $jumlah }}</div>
                    <div class="text-xs text-gray-500">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Pengajuan terakhir (kanan bawah di laptop) --}}
    <div class="lg:col-start-3 lg:row-start-2">
        @if ($pengajuanTerakhir)
            <div class="flex items-center justify-between gap-3 rounded-3xl border border-garis bg-white p-5">
                <div>
                    <div class="font-bold">
                        {{ $pengajuanTerakhir->jenis }} · {{ $pengajuanTerakhir->tanggal_mulai->translatedFormat('j F Y') }}
                    </div>
                    <div class="text-sm text-gray-500">Pengajuan terakhir</div>
                </div>
                <x-lencana :status="$pengajuanTerakhir->status" />
            </div>
        @else
            <div class="hidden rounded-3xl border border-dashed border-garis p-5 text-sm text-gray-500 lg:block">
                Belum ada pengajuan izin atau sakit.
            </div>
        @endif
    </div>
</div>
{{-- Unduh model wajah lebih awal agar halaman presensi langsung siap --}}
@if (! $kondisi && ! $lengkap)
    @foreach (glob(public_path('models/*')) as $berkasModel)
        @if (\Illuminate\Support\Str::contains(basename($berkasModel), ['tiny_face_detector', 'face_landmark_68', 'face_recognition']))
            <link rel="prefetch" href="{{ asset('models/'.basename($berkasModel)) }}">
        @endif
    @endforeach
@endif
@endsection