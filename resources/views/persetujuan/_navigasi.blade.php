{{--
    Baris navigasi di atas detail: kembali ke daftar, posisi "2 dari 5", sebelumnya/berikutnya.
    $koleksi: pengajuan yang menunggu di tab ini, $aktif: id yang sedang dibuka, $tab: kunci tab
--}}
@php
    $daftarId = $koleksi->pluck('id')->values();
    $posisi = $daftarId->search($aktif);
    $idSebelum = $posisi > 0 ? $daftarId[$posisi - 1] : null;
    $idBerikut = $daftarId[$posisi + 1] ?? null;
    $kelasPanah = 'flex h-9 w-9 items-center justify-center rounded-lg border border-garis bg-white';
@endphp

<div class="mb-4 flex items-center justify-between gap-3">
    <a href="{{ route('persetujuan', ['tab' => $tab]) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-navy">
        <i data-lucide="chevron-left" class="h-4 w-4"></i> Kembali ke daftar
    </a>

    @if ($daftarId->count() > 1)
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>{{ $posisi + 1 }} dari {{ $daftarId->count() }}</span>
            <a href="{{ $idSebelum ? route('persetujuan', ['tab' => $tab, 'id' => $idSebelum]) : '#' }}" aria-label="Sebelumnya"
               class="{{ $kelasPanah }} {{ $idSebelum ? 'hover:bg-gray-50' : 'pointer-events-none opacity-40' }}">
                <i data-lucide="chevron-left" class="h-4 w-4"></i>
            </a>
            <a href="{{ $idBerikut ? route('persetujuan', ['tab' => $tab, 'id' => $idBerikut]) : '#' }}" aria-label="Berikutnya"
               class="{{ $kelasPanah }} {{ $idBerikut ? 'hover:bg-gray-50' : 'pointer-events-none opacity-40' }}">
                <i data-lucide="chevron-right" class="h-4 w-4"></i>
            </a>
        </div>
    @endif
</div>
