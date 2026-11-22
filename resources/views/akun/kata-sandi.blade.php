@extends('layouts.app')

@section('konten')
@php
    $isian = [
        'kata_sandi_lama'              => ['Kata sandi lama', 'current-password'],
        'kata_sandi_baru'              => ['Kata sandi baru', 'new-password'],
        'kata_sandi_baru_confirmation' => ['Ulangi kata sandi baru', 'new-password'],
    ];
@endphp

<div class="mx-auto max-w-md">
    <h1 class="text-2xl font-extrabold">Ganti Kata Sandi</h1>
    <p class="mt-1 text-sm text-gray-500">
        Kata sandi awal diberikan administrator. Gantilah dengan kata sandi yang hanya Anda ketahui.
    </p>

    <form method="POST" action="{{ route('akun.kata-sandi.ubah') }}" novalidate
          class="mt-6 space-y-4 rounded-2xl border border-garis bg-white p-5">
        @csrf
        @method('PUT')

        @foreach ($isian as $nama => [$label, $autocomplete])
            <div>
                <label for="{{ $nama }}" class="text-sm font-semibold">{{ $label }}</label>
                <div class="relative mt-1">
                    <input id="{{ $nama }}" name="{{ $nama }}" type="password" maxlength="64"
                           autocomplete="{{ $autocomplete }}"
                           class="w-full rounded-xl border bg-white px-4 py-3 pr-12 text-sm outline-none focus:border-navy {{ $errors->has($nama) ? 'border-merah' : 'border-garis' }}">
                    <button type="button" data-toggle-sandi="{{ $nama }}" aria-label="Lihat kata sandi"
                            class="absolute inset-y-0 right-0 px-4 text-gray-500">
                        <i data-lucide="eye" class="h-5 w-5"></i>
                    </button>
                </div>
                @error($nama)
                    <p class="mt-1 text-sm text-merah">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <p class="text-xs text-gray-500">
            Minimal 8 karakter. Setelah diganti, akun Anda otomatis keluar dari perangkat lain.
        </p>

        <button type="submit" class="w-full rounded-xl bg-merah py-3 font-bold text-white hover:bg-merah-tua">
            Simpan kata sandi
        </button>
    </form>
</div>
@endsection