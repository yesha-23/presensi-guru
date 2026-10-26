<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ?? 'Dashboard' }} · Sistem Presensi YPK Teruna Bangsa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $pengguna = auth()->user();
    $peran = $pengguna->peranUtama();
    $menu = config("menu.$peran", []);
    $menuPribadi = ($peran !== \App\Models\Role::PEGAWAI && $pengguna->hasRole(\App\Models\Role::PEGAWAI)) ? config('menu.Pribadi', []) : [];
    $inisial = collect(preg_split('/\s+/', preg_replace('/,.*$/', '', $pengguna->nama_lengkap)))
        ->filter()->take(2)
        ->map(fn ($kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
        ->join('');

    // Menu aktif: rute itu sendiri, turunannya (route.*), dan pola tambahan di kunci 'aktif' (opsional)
    $menuAktif = fn (array $item) => request()->routeIs($item['route'], $item['route'].'.*', ...($item['aktif'] ?? []));
@endphp
<body class="bg-krem font-sans text-gray-900 antialiased">

{{-- Latar gelap saat menu dibuka di HP --}}
<div id="overlay-sidebar" data-tutup-sidebar class="fixed inset-0 z-30 hidden bg-black/40 lg:hidden"></div>

{{-- ================= SIDEBAR ================= --}}
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-garis bg-white p-4 transition-transform lg:translate-x-0">
    <div class="flex items-center justify-between">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            <img src="{{ asset('images/logo.png') }}" alt="" class="h-9 w-auto">
            <div class="leading-tight">
                <div class="font-bold">Sistem Presensi</div>
                <div class="text-xs text-gray-500">YPK Teruna Bangsa</div>
            </div>
        </a>
        <button type="button" data-tutup-sidebar class="rounded-lg p-1 text-gray-500 lg:hidden" aria-label="Tutup menu">
            <i data-lucide="x" class="h-5 w-5"></i>
        </button>
    </div>

    <div class="mt-5 flex items-center gap-3 rounded-2xl bg-krem p-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-kuning text-sm font-bold">
            {{ $inisial }}
        </span>
        <div class="min-w-0 leading-tight">
            <div class="truncate text-sm font-bold">{{ $pengguna->nama_lengkap }}</div>
            <div class="text-xs text-gray-500">
                {{ $peran }}@if ($pengguna->jabatan && $pengguna->jabatan !== $peran) · {{ $pengguna->jabatan }}@endif
            </div>
        </div>
    </div>

    <nav class="mt-5 flex-1 space-y-1 overflow-y-auto">
        @foreach ($menu as $item)
            @php $aktif = $menuAktif($item); @endphp
            <a href="{{ route($item['route']) }}" @if ($aktif) aria-current="page" @endif
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm {{ $aktif ? 'bg-merah-muda font-semibold text-merah' : 'font-medium text-gray-700 hover:bg-krem' }}">
                <i data-lucide="{{ $item['ikon'] }}" class="h-[18px] w-[18px]"></i>
                {{ $item['label'] }}
            </a>
        @endforeach

        @if ($menuPribadi)
            <div class="px-3 pb-1 pt-5 text-xs font-bold uppercase tracking-wide text-gray-400">Presensi saya</div>
            @foreach ($menuPribadi as $item)
                @php $aktif = $menuAktif($item); @endphp
                <a href="{{ route($item['route']) }}" @if ($aktif) aria-current="page" @endif
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm {{ $aktif ? 'bg-merah-muda font-semibold text-merah' : 'font-medium text-gray-700 hover:bg-krem' }}">
                    <i data-lucide="{{ $item['ikon'] }}" class="h-[18px] w-[18px]"></i>
                    {{ $item['label'] }}
                </a>
            @endforeach
        @endif
    </nav>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-xl border border-garis py-2.5 text-sm font-semibold text-merah hover:bg-merah-muda">
            <i data-lucide="log-out" class="h-4 w-4"></i> Keluar
        </button>
    </form>
</aside>

{{-- ================= KONTEN ================= --}}
<div class="lg:pl-64">
    {{-- Bar atas khusus HP --}}
    <header class="sticky top-0 z-20 flex items-center justify-between border-b border-garis bg-white/95 px-4 py-3 backdrop-blur lg:hidden">
        <button type="button" data-buka-sidebar class="rounded-lg p-1" aria-label="Buka menu">
            <i data-lucide="menu" class="h-6 w-6"></i>
        </button>
        <span class="font-bold">{{ $judul ?? 'Dashboard' }}</span>
        @include('partials.lonceng')
    </header>

    <main class="px-4 py-6 lg:px-8 {{ $peran === \App\Models\Role::PEGAWAI ? 'pb-24 lg:pb-8' : '' }}">
        @foreach (['sukses' => 'bg-emerald-50 text-emerald-800', 'info' => 'bg-yellow-100 text-yellow-900', 'galat' => 'bg-red-100 text-red-800'] as $kunci => $warna)
            @if (session($kunci))
                <div class="mb-4 rounded-xl px-4 py-3 text-sm {{ $warna }}">{{ session($kunci) }}</div>
            @endif
        @endforeach

        @yield('konten')
    </main>
</div>

{{-- Navigasi bawah khusus Pegawai di HP: jumlah kolom mengikuti jumlah menu --}}
@if ($peran === \App\Models\Role::PEGAWAI)
    <nav class="fixed inset-x-0 bottom-0 z-20 grid border-t border-garis bg-white pb-[env(safe-area-inset-bottom)] lg:hidden"
         style="grid-template-columns: repeat({{ max(count($menu), 1) }}, minmax(0, 1fr));">
        @foreach ($menu as $item)
            @php $aktif = $menuAktif($item); @endphp
            <a href="{{ route($item['route']) }}" @if ($aktif) aria-current="page" @endif
               class="flex flex-col items-center gap-1 px-1 py-2.5 text-[11px] leading-none {{ $aktif ? 'font-semibold text-merah' : 'text-gray-600' }}">
                <i data-lucide="{{ $item['ikon'] }}" class="h-5 w-5"></i>
                <span class="max-w-full truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>
@endif
@stack('script')
</body>
</html>