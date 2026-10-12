{{-- Kartu "semua sudah diproses" --}}
<div class="flex flex-col items-center rounded-3xl border border-dashed border-garis bg-white px-6 py-12 text-center">
    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
        <i data-lucide="check-check" class="h-7 w-7"></i>
    </span>
    <h2 class="mt-3 text-lg font-bold">Semua sudah diproses</h2>
    <p class="mt-1 max-w-md text-sm text-gray-500">{{ $pesan }}</p>
</div>