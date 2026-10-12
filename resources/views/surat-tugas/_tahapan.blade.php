@php
    $tahap = collect([[
        'judul'  => 'Dibuat administrator',
        'ket'    => ($surat->pembuat?->nama_lengkap ?? '-').' · '.$surat->created_at->translatedFormat('j M Y'),
        'status' => 'Disetujui',
    ]]);

    if ($surat->persetujuan->isEmpty()) {
        $tahap->push(['judul' => 'Kepala Sekolah', 'ket' => 'Belum dikirim', 'status' => 'Draf']);
    }

    foreach ($surat->persetujuan->sortBy('urutan') as $p) {
        $tahap->push([
            'judul'  => $p->urutan === 1 ? 'Kepala Sekolah' : 'Ketua Yayasan',
            'ket'    => ($p->penyetuju?->nama_lengkap ?? '-').' · '.match ($p->status) {
                'Disetujui' => 'ditandatangani '.$p->ditandatangani_at?->translatedFormat('j M'),
                'Ditolak'   => 'ditolak',
                default     => 'menunggu',
            },
            'status' => $p->status,
        ]);
    }

    $kolom = $tahap->count() >= 3 ? 'sm:grid-cols-3' : 'sm:grid-cols-2';
@endphp

<div class="grid gap-4 rounded-2xl border border-garis bg-white p-4 {{ $kolom }}">
    @foreach ($tahap as $t)
        <div class="flex items-center gap-3">
            @if ($t['status'] === 'Disetujui')
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-800"><i data-lucide="check" class="h-4 w-4"></i></span>
            @elseif ($t['status'] === 'Ditolak')
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-800"><i data-lucide="x" class="h-4 w-4"></i></span>
            @elseif ($t['status'] === 'Menunggu')
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-kuning text-gray-900"><i data-lucide="pen-line" class="h-4 w-4"></i></span>
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500"><i data-lucide="circle-dashed" class="h-4 w-4"></i></span>
            @endif
            <div class="min-w-0 leading-tight">
                <div class="font-bold">{{ $t['judul'] }}</div>
                <div class="text-xs leading-snug text-gray-500">{{ $t['ket'] }}</div>
            </div>
        </div>
    @endforeach
</div>