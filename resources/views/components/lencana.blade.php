@props(['status', 'teks' => null])

@php
    $warna = config('lencana')[$status] ?? 'bg-gray-100 text-gray-700';
@endphp

<span {{ $attributes->merge(['class' => "inline-block rounded-full px-3 py-1 text-xs font-semibold {$warna}"]) }}>
    {{ $teks ?? $status }}
</span>