<div class="mt-5 flex flex-wrap gap-2">
    @foreach (['rekap' => 'Rekap presensi', 'pkg' => 'Laporan PKG'] as $rute => $teks)
        <a href="{{ route($rute) }}"
           class="rounded-full border px-4 py-2 text-sm font-semibold {{ request()->routeIs($rute) ? 'border-gray-900 bg-gray-900 text-white' : 'border-garis bg-white text-gray-700 hover:bg-gray-50' }}">
            {{ $teks }}
        </a>
    @endforeach
</div>