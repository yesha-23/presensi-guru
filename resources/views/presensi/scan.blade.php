@extends('layouts.publik', ['judul' => 'Presensi'])

@section('konten')
@if (! $lokasi)
    <div class="w-full max-w-md rounded-3xl bg-white p-8 text-center">
        <p class="font-bold">Lokasi sekolah belum diatur.</p>
        <p class="mt-1 text-sm text-gray-500">Hubungi administrator.</p>
    </div>
@else
<div id="halaman-presensi" class="w-full max-w-md"
     data-lat="{{ $lokasi->latitude }}"
     data-lng="{{ $lokasi->longitude }}"
     data-radius="{{ $lokasi->radius_meter }}"
     data-url-model="{{ asset('models') }}"
     data-url-scan="{{ route('presensi.proses') }}"
     data-url-konfirmasi="{{ route('presensi.pulang.konfirmasi') }}"
     data-url-batal-pulang="{{ route('presensi.pulang.batal') }}"
     data-url-bukan-saya="{{ route('presensi.bukan-saya') }}"
     data-url-login="{{ route('login') }}"
     data-url-beranda="{{ route('beranda') }}">

    <div class="flex items-center gap-3">
        <a href="{{ route('beranda') }}" class="rounded-xl border border-garis bg-white p-2.5" aria-label="Kembali">
            <i data-lucide="chevron-left" class="h-5 w-5"></i>
        </a>
        <h1 class="text-2xl font-extrabold">Presensi</h1>
    </div>

    {{-- Kamera --}}
    <div class="relative mt-4 aspect-[3/4] overflow-hidden rounded-3xl bg-gray-900 sm:aspect-[4/3]">
        <video playsinline muted class="h-full w-full -scale-x-100 object-cover"></video>

        <div id="oval"
             class="pointer-events-none absolute left-1/2 top-[45%] aspect-[3/4] h-[62%] -translate-x-1/2 -translate-y-1/2 rounded-[50%] border-4 border-dashed border-kuning"></div>

        <div class="absolute inset-x-4 bottom-4 rounded-2xl bg-white px-4 py-3 text-center">
            <div id="petunjuk" class="font-bold">Memuat…</div>
            <div id="sub-petunjuk" class="text-sm text-gray-500"></div>
        </div>
    </div>

    {{-- Lokasi --}}
    <div class="mt-4 divide-y divide-garis rounded-2xl border border-garis bg-white">
        <div class="p-4">
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-2"><i data-lucide="map-pin" class="h-5 w-5 text-blue-700"></i> Jarak ke sekolah</span>
                <span id="teks-jarak" class="font-bold tabular-nums">– / {{ $lokasi->radius_meter }} m</span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100">
                <div id="bar-jarak" class="h-full w-0 rounded-full bg-blue-700 transition-all"></div>
            </div>
        </div>
        <div class="flex items-center justify-between p-4">
            <span class="flex items-center gap-2"><i data-lucide="crosshair" class="h-5 w-5 text-gray-600"></i> Akurasi GPS</span>
            <span id="teks-akurasi" class="font-bold tabular-nums">–</span>
        </div>
    </div>

    <button type="button" id="tombol-scan" disabled
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-merah py-4 text-lg font-bold text-white hover:bg-merah-tua disabled:opacity-50">
        <i data-lucide="scan-face" class="h-6 w-6"></i> <span>Scan Wajah</span>
    </button>

    <form id="form-masuk" method="POST" action="{{ route('presensi.masuk') }}" class="hidden">@csrf</form>

    <dialog id="popup" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl p-6 backdrop:bg-gray-900/70">
        <div id="isi-popup"></div>
    </dialog>
</div>

@vite('resources/js/presensi.js')
@endif
@endsection