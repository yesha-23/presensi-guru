@extends('layouts.app')

@section('konten')
@php
    $inisial = collect(preg_split('/\s+/', preg_replace('/,.*$/', '', $user->nama_lengkap)))
        ->filter()->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->join('');
    $wajahTerdaftar = $user->face_descriptors_count >= 3;
    $jam = fn ($t) => str_replace(':', '.', substr($t, 0, 5));
@endphp

<div class="mx-auto max-w-xl">
    <div class="text-center">
        <span class="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-kuning text-3xl font-extrabold">{{ $inisial }}</span>
        <h1 class="mt-3 text-2xl font-extrabold">{{ $user->nama_lengkap }}</h1>
        <p class="text-gray-500">
            {{ $user->nama_panggilan ? $user->nama_panggilan.' · ' : '' }}{{ $user->kategori }} · {{ $user->unit->nama }}
        </p>
    </div>

    <div class="mt-6 divide-y divide-garis rounded-2xl border border-garis bg-white">
        @foreach ([
            'NIP'       => $user->nip ?? '-',
            'Email'     => $user->email ?? '-',
            'Jabatan'   => $user->jabatan ?? '-',
            'Peran'     => $user->roles->pluck('nama')->join(', '),
            'Jam kerja' => $jamKerja ? $jam($jamKerja->jam_masuk).' – '.$jam($jamKerja->jam_pulang) : '-',
        ] as $label => $isi)
            <div class="flex items-center justify-between gap-4 px-5 py-3.5">
                <span class="text-gray-500">{{ $label }}</span>
                <span class="text-right font-semibold">{{ $isi }}</span>
            </div>
        @endforeach
    </div>

    {{-- Data wajah (SR-NF05) --}}
    <div class="mt-4 rounded-2xl border border-garis bg-white p-5">
        <div class="flex items-center justify-between">
            <h2 class="font-bold">Data wajah</h2>
            <x-lencana :status="$wajahTerdaftar ? 'Disetujui' : 'Ditolak'" :teks="$wajahTerdaftar ? 'Terdaftar' : 'Belum terdaftar'" />
        </div>
        @if ($wajahTerdaftar)
            <p class="mt-2 text-sm text-gray-600">
                {{ $user->face_descriptors_count }} foto referensi · didaftarkan administrator
                {{ $wajahTerakhir ? \Carbon\Carbon::parse($wajahTerakhir)->translatedFormat('j F Y') : '' }}.
                @if ($user->persetujuan_wajah_at)
                    Persetujuan pemrosesan data biometrik diberikan {{ $user->persetujuan_wajah_at->translatedFormat('j F Y, H.i') }}.
                @endif
            </p>
            <p class="mt-2 text-sm text-gray-500">Wajah sering gagal dikenali? Minta administrator mendaftarkan ulang.</p>
        @else
            <p class="mt-2 text-sm text-gray-600">Data wajah belum didaftarkan. Hubungi administrator agar Anda dapat melakukan presensi.</p>
        @endif
    </div>

    {{-- Kata sandi (SR-F03) --}}
    <div class="mt-4 flex items-start gap-3 rounded-2xl border border-garis bg-white p-5 text-sm text-gray-600">
        <i data-lucide="lock" class="mt-0.5 h-5 w-5 shrink-0"></i>
        <p>Kata sandi dibuat dan diubah oleh administrator. Hubungi Kepala Bagian Administrasi Umum bila perlu reset.</p>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button class="w-full rounded-2xl border border-merah bg-white py-3.5 font-bold text-merah hover:bg-merah-muda">Keluar</button>
    </form>
</div>
@endsection