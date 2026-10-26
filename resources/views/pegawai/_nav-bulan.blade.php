<div class="flex items-center justify-between gap-3 md:justify-end">
    <a href="{{ route('pegawai.riwayat', ['tab' => $tab, 'bulan' => $sebelum]) }}"
       class="rounded-xl border border-garis bg-white p-2.5 hover:bg-gray-50" aria-label="Bulan sebelumnya">
        <i data-lucide="chevron-left" class="h-5 w-5"></i>
    </a>
    <span class="min-w-40 text-center text-lg font-extrabold">{{ $bulan->translatedFormat('F Y') }}</span>
    @if ($bisaMaju)
        <a href="{{ route('pegawai.riwayat', ['tab' => $tab, 'bulan' => $sesudah->format('Y-m')]) }}"
           class="rounded-xl border border-garis bg-white p-2.5 hover:bg-gray-50" aria-label="Bulan berikutnya">
            <i data-lucide="chevron-right" class="h-5 w-5"></i>
        </a>
    @else
        <span class="rounded-xl border border-garis bg-white p-2.5 opacity-30"><i data-lucide="chevron-right" class="h-5 w-5"></i></span>
    @endif
</div>