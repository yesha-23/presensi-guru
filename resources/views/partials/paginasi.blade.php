@if ($paginator->total() > 0)
<div class="flex flex-wrap items-center justify-between gap-3 border-t border-garis px-4 py-3 text-sm text-gray-500">
    <span>
        Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} {{ $satuan ?? 'data' }}{{ $tambahan ?? '' }}
    </span>

    @if ($paginator->hasPages())
        @php $kelas = 'flex h-9 min-w-9 items-center justify-center rounded-lg border px-2 font-semibold'; @endphp
        <div class="flex gap-1">
            <a href="{{ $paginator->previousPageUrl() ?? '#' }}"
               class="{{ $kelas }} border-garis bg-white text-gray-700 {{ $paginator->onFirstPage() ? 'pointer-events-none opacity-40' : '' }}">‹</a>

            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $halaman => $url)
                <a href="{{ $url }}"
                   class="{{ $kelas }} {{ $halaman == $paginator->currentPage() ? 'border-merah bg-merah-muda text-merah' : 'border-garis bg-white text-gray-700' }}">{{ $halaman }}</a>
            @endforeach

            <a href="{{ $paginator->nextPageUrl() ?? '#' }}"
               class="{{ $kelas }} border-garis bg-white text-gray-700 {{ $paginator->hasMorePages() ? '' : 'pointer-events-none opacity-40' }}">›</a>
        </div>
    @endif
</div>
@endif