@extends('layouts.publik', ['judul' => 'Presensi'])

@section('konten')
<div class="w-full max-w-md rounded-3xl bg-white p-8 text-center shadow-sm">
    <i data-lucide="scan-face" class="mx-auto h-12 w-12 text-merah"></i>
    <h1 class="mt-4 text-xl font-extrabold">Scan wajah</h1>
    <p class="mt-2 text-sm text-gray-500">Halaman ini dibangun pada langkah modul presensi.</p>
    <a href="{{ route('beranda') }}" class="mt-6 inline-block font-semibold text-navy">← Kembali</a>
</div>
@endsection