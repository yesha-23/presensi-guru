{{--
    Baris tab (kiri) + tombol unduh (kanan), satu baris di laptop maupun HP.
    $unduh: daftar tombol [label panjang, label pendek (HP), url, utama (true = merah)]
--}}
@php $unduh = $unduh ?? []; @endphp

<div class="mt-5 flex flex-wrap items-center justify-between gap-2">
    <div class="flex items-center gap-1.5 sm:gap-2">
        @foreach (['rekap' => ['Rekap presensi', 'Rekap'], 'pkg' => ['Laporan PKG', 'Laporan PKG']] as $rute => [$teks, $teksPendek])
            <a href="{{ route($rute) }}"
               class="whitespace-nowrap rounded-full border px-3 py-2 text-sm font-semibold sm:px-4
                      {{ request()->routeIs($rute) ? 'border-gray-900 bg-gray-900 text-white' : 'border-garis bg-white text-gray-700 hover:bg-gray-50' }}">
                <span class="sm:hidden">{{ $teksPendek }}</span>
                <span class="hidden sm:inline">{{ $teks }}</span>
            </a>
        @endforeach
    </div>

    @if ($unduh)
        <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
            @foreach ($unduh as [$label, $labelPendek, $url, $utama])
                <a href="{{ $url }}" title="{{ $label }}"
                   class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3 py-2 text-sm font-semibold sm:gap-2 sm:px-4
                          {{ $utama ? 'bg-merah text-white hover:bg-merah-tua' : 'border border-garis bg-white hover:bg-gray-50' }}">
                    <i data-lucide="download" class="h-4 w-4"></i>
                    <span class="sm:hidden">{{ $labelPendek }}</span>
                    <span class="hidden sm:inline">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
