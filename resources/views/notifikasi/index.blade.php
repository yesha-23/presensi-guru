@extends('layouts.app')

@section('konten')
@php
    $warna = [
        'pengajuan' => 'bg-yellow-100 text-yellow-800',
        'berhasil'  => 'bg-emerald-50 text-emerald-700',
        'ditolak'   => 'bg-red-100 text-red-700',
        'pengingat' => 'bg-merah-muda text-merah',
        'info'      => 'bg-blue-50 text-blue-800',
    ];
    $waktu = fn ($t) => match (true) {
        $t->isToday()     => 'Hari ini · '.$t->format('H:i:s'),
        $t->isYesterday() => 'Kemarin · '.$t->format('H:i'),
        default           => $t->translatedFormat('j F Y'),
    };
@endphp

<div class="flex items-center gap-3">
    <a href="{{ route('dashboard') }}" class="rounded-xl border border-garis bg-white p-2.5" aria-label="Kembali">
        <i data-lucide="chevron-left" class="h-5 w-5"></i>
    </a>
    <h1 class="text-2xl font-extrabold">Notifikasi</h1>
</div>

<div class="mx-auto mt-5 max-w-3xl">
    <div class="flex items-center justify-between">
        <span class="text-sm text-gray-500">{{ $belumDibaca }} belum dibaca</span>
        @if ($belumDibaca > 0)
            <form method="POST" action="{{ route('notifikasi.baca-semua') }}">
                @csrf
                <button class="text-sm font-bold text-merah">Tandai semua dibaca</button>
            </form>
        @endif
    </div>

    <div class="mt-3 space-y-3">
        @forelse ($notifikasi as $n)
            @php $belum = is_null($n->read_at); @endphp
            <a href="{{ route('notifikasi.buka', $n->id) }}"
               class="flex items-start gap-4 rounded-2xl border border-garis p-4 hover:border-navy {{ $belum ? 'bg-white' : 'bg-krem/60' }}">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $warna[$n->data['jenis'] ?? 'info'] ?? $warna['info'] }}">
                    <i data-lucide="bell" class="h-5 w-5"></i>
                </span>
                <div class="min-w-0 flex-1 leading-snug">
                    <div class="{{ $belum ? 'font-bold' : 'font-medium text-gray-700' }}">{{ $n->data['judul'] ?? 'Notifikasi' }}</div>
                    <div class="text-sm text-gray-500">
                        @if (! empty($n->data['keterangan']))
                            {{ $n->data['keterangan'] }} ·
                        @endif
                        {{ $waktu($n->created_at) }}
                    </div>
                </div>
                @if ($belum)
                    <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full bg-merah" aria-label="Belum dibaca"></span>
                @endif
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-garis bg-white p-10 text-center text-gray-500">
                Belum ada notifikasi.
            </div>
        @endforelse
    </div>

    <div class="mt-4 overflow-hidden rounded-2xl border border-garis bg-white">
        @include('partials.paginasi', ['paginator' => $notifikasi, 'satuan' => 'notifikasi'])
    </div>
</div>
@endsection