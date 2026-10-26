@extends('layouts.app')

@section('konten')
@php
    $sebelum = $bulan->copy()->subMonth()->format('Y-m');
    $sesudah = $bulan->copy()->addMonth();
    $bisaMaju = $sesudah->lte(today());
    $kolomPresensi = 'md:grid md:grid-cols-[9rem_9rem_9rem_minmax(0,1fr)_11rem] md:items-center md:gap-4';
@endphp

{{-- ================= BARIS 1: judul + navigasi bulan ================= --}}
<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <h1 class="text-2xl font-extrabold lg:text-3xl">Riwayat presensi</h1>

    <div class="flex items-center justify-between gap-3 md:justify-end">
        <a href="{{ route('pegawai.riwayat', ['bulan' => $sebelum]) }}" class="rounded-xl border border-garis bg-white p-2.5 hover:bg-gray-50" aria-label="Bulan sebelumnya">
            <i data-lucide="chevron-left" class="h-5 w-5"></i>
        </a>
        <span class="min-w-40 text-center text-lg font-extrabold">{{ $bulan->translatedFormat('F Y') }}</span>
        @if ($bisaMaju)
            <a href="{{ route('pegawai.riwayat', ['bulan' => $sesudah->format('Y-m')]) }}" class="rounded-xl border border-garis bg-white p-2.5 hover:bg-gray-50" aria-label="Bulan berikutnya">
                <i data-lucide="chevron-right" class="h-5 w-5"></i>
            </a>
        @else
            <span class="rounded-xl border border-garis bg-white p-2.5 opacity-30"><i data-lucide="chevron-right" class="h-5 w-5"></i></span>
        @endif
    </div>
</div>

{{-- ================= BARIS 2: angka ringkas ================= --}}
<div class="mt-4 grid grid-cols-4 gap-2 md:gap-3">
    @foreach ($ringkas as $label => $jumlah)
        <div class="rounded-2xl bg-white p-3 text-center md:p-4">
            <div class="text-2xl font-extrabold md:text-3xl {{ $label === 'Terlambat' ? 'text-orange-800' : ($label === 'Alpa' && $jumlah ? 'text-merah' : '') }}">{{ $jumlah }}</div>
            <div class="text-xs text-gray-500 md:text-sm">{{ $label }}</div>
        </div>
    @endforeach
</div>

{{-- ================= BARIS 3: daftar per hari ================= --}}
<div class="mt-4 overflow-hidden rounded-2xl border border-garis bg-white">
    <div class="hidden bg-krem px-5 py-3 text-sm font-semibold text-gray-600 {{ $kolomPresensi }}">
        <span>Tanggal</span><span>Datang</span><span>Pulang</span><span>Keterangan</span><span class="text-right">Status</span>
    </div>

    <div class="divide-y divide-garis">
        @forelse ($hari as $h)
            @php
                $p = $h['presensi'];
                $pulang = $p?->jam_pulang ?? ($p?->jam_datang ? ($h['tanggal']->isToday() ? '…' : 'Tidak presensi') : '—');
                $catatan = collect([
                    $h['hkt'] ? $h['hkt'].' (hari kerja tambahan)' : null,
                    $p?->pulang_cepat ? 'Pulang lebih awal '.\App\Models\Presensi::formatDetik($p->detik_pulang_cepat) : null,
                ])->filter();
            @endphp
            <div class="flex items-center gap-4 px-4 py-3 md:px-5 {{ $kolomPresensi }}">
                <div class="w-10 shrink-0 text-center leading-tight md:w-auto md:text-left">
                    <span class="text-xl font-extrabold md:text-base md:font-semibold">{{ $h['tanggal']->day }}</span>
                    <span class="block text-xs text-gray-500 md:inline md:text-sm md:text-gray-700">
                        <span class="md:hidden">{{ $h['tanggal']->translatedFormat('D') }}</span>
                        <span class="hidden md:inline">{{ $h['tanggal']->translatedFormat('M') }} · {{ $h['tanggal']->translatedFormat('l') }}</span>
                    </span>
                </div>

                <div class="min-w-0 flex-1 md:hidden">
                    <div class="font-semibold tabular-nums">{{ $p?->jam_datang ? $p->jam_datang.' – '.$pulang : '—' }}</div>
                    @foreach ($catatan as $c)
                        <div class="text-xs text-yellow-800">{{ $c }}</div>
                    @endforeach
                </div>

                <span class="hidden font-semibold tabular-nums md:block">{{ $p?->jam_datang ?? '—' }}</span>
                <span class="hidden font-semibold tabular-nums md:block {{ $pulang === 'Tidak presensi' ? 'text-orange-800' : '' }}">{{ $pulang }}</span>
                <span class="hidden text-sm text-gray-600 md:block">{{ $catatan->join(' · ') ?: '—' }}</span>

                <div class="shrink-0 md:text-right">
                    <x-lencana :status="$h['status']"
                               :teks="$h['status'] === 'Terlambat' ? 'Terlambat '.\App\Models\Presensi::formatDetik($p->detik_terlambat) : null" />
                </div>
            </div>
        @empty
            <div class="p-10 text-center text-sm text-gray-500">Belum ada riwayat presensi pada bulan ini.</div>
        @endforelse
    </div>
</div>

<p class="mt-3 text-xs text-gray-500">
    Hari libur dan hari nonkerja tanpa catatan tidak ditampilkan. Ada data yang keliru? Hubungi Kepala Bagian Administrasi Umum.
    Status izin, sakit, dan lembur dapat dilihat di menu <a href="{{ route('pegawai.pengajuan') }}" class="font-semibold text-navy underline">Pengajuan</a>.
</p>
@endsection