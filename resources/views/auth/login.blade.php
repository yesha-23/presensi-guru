@extends('layouts.publik', ['judul' => 'Masuk'])

@section('konten')
<div class="w-full max-w-md">
    <a href="{{ route('beranda') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-semibold text-navy">
        <i data-lucide="chevron-left" class="h-4 w-4"></i> Kembali ke halaman awal
    </a>

    <div class="rounded-3xl border-t-4 border-merah bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-extrabold text-navy">Masuk ke Dashboard</h1>
        <p class="text-sm text-gray-500">Gunakan akun yang diberikan administrator.</p>

        <form method="POST" action="{{ route('login.proses') }}" class="mt-6 space-y-4" novalidate>
            @csrf

            <div>
                <label for="identitas" class="text-sm font-semibold">NIP atau email</label>
                <input id="identitas" name="identitas" type="text" value="{{ old('identitas') }}"
                       placeholder="Masukkan NIP atau email" autocomplete="username" autofocus
                       class="mt-1 w-full rounded-xl border px-4 py-3 outline-none focus:border-navy {{ $errors->has('identitas') ? 'border-merah' : 'border-garis' }}">
                @error('identitas')
                    <p class="mt-1 text-sm text-merah">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="text-sm font-semibold">Kata sandi</label>
                <div class="relative mt-1">
                    <input id="password" name="password" type="password" autocomplete="current-password"
                           class="w-full rounded-xl border px-4 py-3 pr-12 outline-none focus:border-navy {{ $errors->has('password') ? 'border-merah' : 'border-garis' }}">
                    <button type="button" data-toggle-sandi="password" aria-label="Lihat kata sandi"
                            class="absolute inset-y-0 right-0 px-4 text-gray-500">
                        <i data-lucide="eye" class="h-5 w-5"></i>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-merah">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-xl bg-merah py-3 font-bold text-white hover:bg-merah-tua">
                Masuk
            </button>
        </form>

        <p class="mt-5 text-center text-sm text-gray-500">
            Lupa kata sandi? Hubungi Kepala Bagian Administrasi Umum.
        </p>
    </div>
</div>
@endsection