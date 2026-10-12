@php $jumlahNotif = auth()->user()->unreadNotifications()->count(); @endphp
<a href="{{ route('notifikasi') }}" aria-label="Notifikasi"
   class="relative inline-flex h-11 w-11 items-center justify-center rounded-xl border {{ ($terang ?? false) ? 'border-white/30 text-white' : 'border-garis bg-white text-gray-800' }}">
    <i data-lucide="bell" class="h-5 w-5"></i>
    @if ($jumlahNotif > 0)
        <span class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-kuning px-1 text-xs font-bold text-gray-900">
            {{ $jumlahNotif }}
        </span>
    @endif
</a>