@extends('layouts.publik')

@section('konten')
<div class="w-full max-w-xl text-center">
    <div class="mx-auto w-60 sm:w-80">
        <img src="{{ asset('images/logo.png') }}" alt="Logo Sekolah Teruna Bangsa" class="mx-auto w-56 drop-shadow-sm sm:w-72">
    </div>

    <h1 class="mt-6 text-4xl font-extrabold text-navy sm:text-5xl">SISTEM PRESENSI</h1>
    <p class="mx-auto mt-3 max-w-md text-gray-700">
        Presensi Mandiri Guru dan Karyawan KB, TK &amp; SD dengan Pengenalan Wajah dan Lokasi.
    </p>

    @if (session('info'))
        <div class="mx-auto mt-6 max-w-md rounded-xl bg-yellow-100 px-4 py-3 text-sm text-yellow-900">
            {{ session('info') }}
        </div>
    @endif

    <div class="mx-auto mt-8 flex max-w-md flex-col gap-3 sm:flex-row">
        <a href="{{ route('presensi.scan') }}"
           class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-merah py-4 text-lg font-bold text-white hover:bg-merah-tua">
            <i data-lucide="scan-face" class="h-6 w-6"></i> Presensi
        </a>
        <a href="{{ route('login') }}"
           class="flex flex-1 items-center justify-center gap-2 rounded-2xl border border-garis bg-white py-4 text-lg font-bold text-navy hover:bg-gray-50">
            <i data-lucide="layout-grid" class="h-6 w-6"></i> Lihat Dashboard
        </a>
    </div>
</div>
@endsection