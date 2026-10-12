@extends('layouts.app')

@section('konten')
@php
    $sebelum = $bulan->copy()->subMonth()->format('Y-m');
    $sesudah = $bulan->copy()->addMonth();
    $bisaMaju = $sesudah->lte(today());
    $ringkasPengajuan = [
        'Menunggu'  => $pengajuan->where('status', 'Menunggu')->count(),
        'Disetujui' => $pengajuan->where('status', 'Disetujui')->count(),
        'Ditolak'   => $pengajuan->where('status', 'Ditolak')->count(),
    ];

    // Kolom tabel laptop (HP memakai susunan ringkas)
    $kolomPresensi = 'md:grid md:grid-cols-[9rem_9rem_9rem_minmax(0,1fr)_11rem] md:items-center md:gap-4';
    $kolomPengajuan = 'md:grid md:grid-cols-[7rem_minmax(0,1fr)_10rem_8rem] md:items-start md:gap-4';
@endphp

<h1 class="text-2xl font-extrabold lg:text-3xl">Riwayat</h1>

{{-- ================= BARIS 1: tab + navigasi bulan / tombol ajukan ================= --}}
<div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div class="grid grid-cols-2 rounded-2xl bg-krem p-1 md:w-80">
        @foreach (['presensi' => 'Presensi', 'pengajuan' => 'Pengajuan'] as $kunci => $teks)
            <a href="{{ route('pegawai.riwayat', ['tab' => $kunci, 'bulan' => $bulan->format('Y-m')]) }}"
               class="rounded-xl py-2.5 text-center font-semibold {{ $tab === $kunci ? 'bg-white text-merah shadow-sm' : 'text-gray-500' }}">
                {{ $teks }}
            </a>
        @endforeach
    </div>

    @if ($tab === 'presensi')
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
    @else
        <div class="grid grid-cols-2 gap-2 md:flex">
            <a href="{{ route('pegawai.izin') }}" class="rounded-xl border border-garis bg-white px-4 py-2.5 text-center text-sm font-semibold hover:bg-gray-50">Ajukan izin / sakit</a>
            <a href="{{ route('pegawai.lembur') }}" class="rounded-xl border border-garis bg-white px-4 py-2.5 text-center text-sm font-semibold hover:bg-gray-50">Ajukan lembur</a>
        </div>
    @endif
</div>

@if ($tab === 'presensi')
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
        {{-- Judul kolom (laptop saja) --}}
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
                    {{-- Tanggal: HP bertumpuk, laptop satu baris --}}
                    <div class="w-10 shrink-0 text-center leading-tight md:w-auto md:text-left">
                        <span class="text-xl font-extrabold md:text-base md:font-semibold">{{ $h['tanggal']->day }}</span>
                        <span class="block text-xs text-gray-500 md:inline md:text-sm md:text-gray-700">
                            <span class="md:hidden">{{ $h['tanggal']->translatedFormat('D') }}</span>
                            <span class="hidden md:inline">{{ $h['tanggal']->translatedFormat('M') }} · {{ $h['tanggal']->translatedFormat('l') }}</span>
                        </span>
                    </div>

                    {{-- HP: jam & catatan dalam satu blok --}}
                    <div class="min-w-0 flex-1 md:hidden">
                        <div class="font-semibold tabular-nums">{{ $p?->jam_datang ? $p->jam_datang.' – '.$pulang : '—' }}</div>
                        @foreach ($catatan as $c)
                            <div class="text-xs text-yellow-800">{{ $c }}</div>
                        @endforeach
                    </div>

                    {{-- Laptop: kolom terpisah --}}
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
    </p>
@else
    {{-- ================= BARIS 2: angka ringkas pengajuan ================= --}}
    <div class="mt-4 grid grid-cols-3 gap-2 md:gap-3">
        @foreach ($ringkasPengajuan as $label => $jumlah)
            <div class="rounded-2xl bg-white p-3 text-center md:p-4">
                <div class="text-2xl font-extrabold md:text-3xl {{ $label === 'Ditolak' && $jumlah ? 'text-merah' : ($label === 'Menunggu' && $jumlah ? 'text-yellow-700' : '') }}">{{ $jumlah }}</div>
                <div class="text-xs text-gray-500 md:text-sm">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    {{-- ================= BARIS 3: daftar pengajuan ================= --}}
    <div class="mt-4 overflow-hidden rounded-2xl border border-garis bg-white">
        <div class="hidden bg-krem px-5 py-3 text-sm font-semibold text-gray-600 {{ $kolomPengajuan }}">
            <span>Jenis</span><span>Detail</span><span>Diajukan</span><span class="text-right">Status</span>
        </div>

        <div class="divide-y divide-garis">
            @forelse ($pengajuan as $a)
                <a href="{{ $a['url'] }}" class="block px-4 py-3 hover:bg-krem/50 md:px-5 {{ $kolomPengajuan }}">
                    {{-- HP: jenis + status sebaris --}}
                    <div class="flex items-center justify-between gap-3 md:block">
                        <span class="text-xs font-bold uppercase md:text-sm {{ $a['jenis'] === 'Lembur' ? 'text-orange-800' : 'text-cyan-800' }}">{{ $a['jenis'] }}</span>
                        <span class="md:hidden"><x-lencana :status="$a['status']" /></span>
                    </div>

                    <div class="min-w-0">
                        <div class="font-semibold">{{ $a['judul'] }}</div>
                        @if ($a['status'] === 'Ditolak' && $a['catatan'])
                            <p class="mt-1 rounded-lg bg-red-50 px-2 py-1 text-xs text-red-800">Alasan ditolak: {{ $a['catatan'] }}</p>
                        @endif
                    </div>

                    <div class="text-xs text-gray-500 md:text-sm">
                        <span class="md:hidden">Diajukan </span>{{ $a['waktu']->translatedFormat('j M Y, H.i') }}
                    </div>

                    <div class="hidden md:block md:text-right"><x-lencana :status="$a['status']" /></div>
                </a>
            @empty
                <div class="p-10 text-center text-sm text-gray-500">Belum ada pengajuan.</div>
            @endforelse
        </div>
    </div>
@endif
@endsection