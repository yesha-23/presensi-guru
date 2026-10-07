<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $judul ?? 'Sistem Presensi' }} · YPK Teruna Bangsa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-krem font-sans text-gray-900 antialiased">

<header class="border-b-4 border-kuning bg-navy">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3">
        <a href="{{ route('beranda') }}" class="flex items-center gap-3">
            <span class="rounded-xl bg-white p-1.5">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Teruna Bangsa" class="h-9 w-auto">
            </span>
            <span class="font-bold leading-tight text-white">
                <span class="sm:hidden">YPK Teruna<br>Bangsa</span>
                <span class="hidden sm:inline">Yayasan Pendidikan Kristen Teruna Bangsa</span>
            </span>
        </a>

        <div class="rounded-xl border border-white/20 px-3 py-1.5 text-right"
             data-jam-server="{{ now()->getTimestampMs() }}">
            <div class="text-xs font-bold text-kuning">
                <span class="sm:hidden" data-tanggal-pendek></span>
                <span class="hidden sm:inline" data-tanggal-panjang></span>
            </div>
            <div class="text-lg font-bold tabular-nums text-white" data-jam>{{ now()->format('H:i:s') }}</div>
        </div>
    </div>
</header>

<main class="flex flex-1 items-center justify-center px-4 py-10">
    @yield('konten')
</main>

<footer class="bg-navy py-4 text-center text-sm text-white/80">
    © {{ date('Y') }} · Hak cipta dilindungi
</footer>

</body>
</html>