@extends('layouts.app')

@section('konten')
@php
    $sebelum = $bulan->copy()->subMonth()->format('Y-m');
    $sesudah = $bulan->copy()->addMonth();
    $bisaMaju = $sesudah->lte(today());
    $kolomPresensi = 'md:grid md:grid-cols-[9rem_9rem_9rem_minmax(0,1fr)_11rem] md:items-center md:gap-4';
    $kolomArsip = 'md:grid md:grid-cols-[9rem_minmax(0,1fr)_8rem_12rem] md:items-start md:gap-4';
    $sorot = $sorot ?? null;
    $jenisKegiatan = fn ($s) => $s->jenis_kegiatan === 'Dalam' ? 'Kegiatan dalam' : 'Kegiatan luar';
@endphp

<h1 class="text-2xl font-extrabold lg:text-3xl">Riwayat</h1>

{{-- ================= Navigasi bulan (dipakai tab Presensi & arsip Surat Tugas) ================= --}}
@php
    $navBulan = function () use ($tab, $bulan, $sebelum, $sesudah, $bisaMaju) {
        return view()->make('pegawai._nav-bulan', compact('tab', 'bulan', 'sebelum', 'sesudah', 'bisaMaju'))->render();
    };
@endphp

{{-- ================= BARIS 1: tab (+ navigasi bulan untuk Presensi) ================= --}}
<div class="mt-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div class="grid grid-cols-2 rounded-2xl bg-krem p-1 md:w-80">
        @foreach (['presensi' => 'Presensi', 'surat-tugas' => 'Surat Tugas'] as $kunci => $teks)
            <a href="{{ route('pegawai.riwayat', ['tab' => $kunci, 'bulan' => $bulan->format('Y-m')]) }}"
               class="rounded-xl py-2.5 text-center font-semibold {{ $tab === $kunci ? 'bg-white text-merah shadow-sm' : 'text-gray-500' }}">
                {{ $teks }}
            </a>
        @endforeach
    </div>

    @if ($tab === 'presensi')
        {!! $navBulan() !!}
    @else
        <p class="text-sm text-gray-500">Diterbitkan administrator · tidak perlu presensi lokasi pada hari tugas</p>
    @endif
</div>

@if ($tab === 'presensi')
    {{-- ================= TAB PRESENSI ================= --}}
    <div class="mt-4 grid grid-cols-4 gap-2 md:gap-3">
        @foreach ($ringkas as $label => $jumlah)
            <div class="rounded-2xl bg-white p-3 text-center md:p-4">
                <div class="text-2xl font-extrabold md:text-3xl {{ $label === 'Terlambat' ? 'text-orange-800' : ($label === 'Alpa' && $jumlah ? 'text-merah' : '') }}">{{ $jumlah }}</div>
                <div class="text-xs text-gray-500 md:text-sm">{{ $label }}</div>
            </div>
        @endforeach
    </div>

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
                    // Buka arsip pada bulan presensi tsb, lalu sorot suratnya
                    $urlSurat = $h['surat_id']
                        ? route('pegawai.riwayat', ['tab' => 'surat-tugas', 'bulan' => $h['tanggal']->format('Y-m'), 'sorot' => 'surat-'.$h['surat_id']])
                        : null;
                @endphp
                <div class="flex items-center gap-4 px-4 py-3 md:px-5 {{ $kolomPresensi }}">
                    {{-- Tanggal --}}
                    <div class="w-10 shrink-0 text-center leading-tight md:w-auto md:text-left">
                        <span class="text-xl font-extrabold md:text-base md:font-semibold">{{ $h['tanggal']->day }}</span>
                        <span class="block text-xs text-gray-500 md:inline md:text-sm md:text-gray-700">
                            <span class="md:hidden">{{ $h['tanggal']->translatedFormat('D') }}</span>
                            <span class="hidden md:inline">{{ $h['tanggal']->translatedFormat('M') }} · {{ $h['tanggal']->translatedFormat('l') }}</span>
                        </span>
                    </div>

                    {{-- HP: jam, catatan, tautan surat --}}
                    <div class="min-w-0 flex-1 md:hidden">
                        <div class="font-semibold tabular-nums">{{ $p?->jam_datang ? $p->jam_datang.' – '.$pulang : '—' }}</div>
                        @foreach ($catatan as $c)
                            <div class="text-xs text-yellow-800">{{ $c }}</div>
                        @endforeach
                        @if ($urlSurat)
                            <a href="{{ $urlSurat }}" class="text-xs font-semibold text-navy underline">Lihat surat tugas</a>
                        @endif
                    </div>

                    {{-- Laptop: kolom terpisah --}}
                    <span class="hidden font-semibold tabular-nums md:block">{{ $p?->jam_datang ?? '—' }}</span>
                    <span class="hidden font-semibold tabular-nums md:block {{ $pulang === 'Tidak presensi' ? 'text-orange-800' : '' }}">{{ $pulang }}</span>
                    <span class="hidden text-sm text-gray-600 md:block">
                        {{ $catatan->join(' · ') }}
                        @if ($urlSurat)
                            @if ($catatan->isNotEmpty()) · @endif
                            <a href="{{ $urlSurat }}" class="inline-flex items-center gap-1 font-semibold text-navy underline">
                                <i data-lucide="briefcase" class="h-3.5 w-3.5"></i> Lihat surat tugas
                            </a>
                        @elseif ($catatan->isEmpty())
                            —
                        @endif
                    </span>

                    <div class="shrink-0 md:text-right">
                        <x-lencana :status="$h['status']"
                                   :teks="match ($h['status']) {
                                       'Terlambat'   => 'Terlambat '.\App\Models\Presensi::formatDetik($p->detik_terlambat),
                                       'Lewat Batas' => 'Lewat batas '.\App\Models\Presensi::formatDetik($p->detik_terlambat),
                                       default       => null,
                                   }" />
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
@else
    {{-- ================= TAB SURAT TUGAS ================= --}}

    {{-- Usulan surat tugas oleh pegawai --}}
    <div class="mt-6 flex flex-col gap-3 rounded-2xl border border-garis bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-krem text-merah"><i data-lucide="file-plus" class="h-5 w-5"></i></span>
            <div class="text-sm leading-snug">
                <div class="font-semibold">Menerima undangan atau tugas langsung?</div>
                <div class="text-gray-500">Usulkan surat tugas dengan melampirkan undangannya. Administrator akan memproses dan mengirimkannya ke kepala sekolah.</div>
            </div>
        </div>
        <a href="{{ route('pegawai.usulan-surat.baru') }}"
           class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">
            <i data-lucide="send" class="h-4 w-4"></i> Usulkan surat tugas
        </a>
    </div>

    {{-- ---------- Bagian 1: berlangsung & akan datang (kartu) ---------- --}}
    <h2 class="mt-6 font-bold">
        Berlangsung &amp; akan datang
        <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-sm text-gray-600">{{ $aktif->count() }}</span>
    </h2>

    @if ($aktif->isEmpty())
        <p class="mt-2 rounded-2xl border border-dashed border-garis bg-white px-4 py-4 text-sm text-gray-500">
            Tidak ada surat tugas yang sedang atau akan berlangsung.
        </p>
    @else
        <div class="mt-3 grid gap-4 lg:grid-cols-2">
            @foreach ($aktif as $s)
                @php
                    $hariIni = $s->tahap === 'Berlangsung';
                    $disorot = $sorot === 'surat-'.$s->id;
                @endphp
                <div id="surat-{{ $s->id }}"
                     class="rounded-3xl border-2 bg-white p-5 transition-shadow duration-1000 {{ $hariIni ? 'border-merah' : 'border-garis' }} {{ $disorot ? 'baris-sorot ring-4 ring-yellow-200' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-xs font-bold uppercase {{ $hariIni ? 'text-merah' : 'text-navy' }}">
                            {{ $hariIni ? 'Hari ini' : 'Akan datang' }} · {{ $jenisKegiatan($s) }}
                        </span>
                        <x-lencana :status="$s->labelStatus()" />
                    </div>

                    <h3 class="mt-2 text-lg font-extrabold">{{ $s->perihal }}</h3>

                    <dl class="mt-3 grid grid-cols-[6rem_1fr] gap-y-1 text-sm">
                        <dt class="text-gray-500">Nomor</dt>
                        <dd class="font-semibold">{{ $s->nomor_surat ?? 'Menunggu persetujuan' }}</dd>
                        <dt class="text-gray-500">Tanggal</dt>
                        <dd class="font-semibold">{{ $s->rentangTanggal() }}</dd>
                        @if ($s->jam_mulai)
                            <dt class="text-gray-500">Waktu</dt>
                            <dd class="font-semibold">{{ $s->rentangJam() }}</dd>
                        @endif
                        <dt class="text-gray-500">Pemberi</dt>
                        <dd class="font-semibold">{{ $s->pemberi_tugas }}</dd>
                        <dt class="text-gray-500">Lokasi</dt>
                        <dd class="font-semibold">{{ $s->lokasi ?: '—' }}</dd>
                    </dl>

                    @if ($s->poinKeterangan())
                        <div class="mt-3 rounded-xl bg-yellow-50 p-3 text-sm text-yellow-900">
                            <div class="font-semibold">Keterangan</div>
                            <ul class="mt-1 list-disc pl-5">
                                @foreach ($s->poinKeterangan() as $poin)
                                    <li>{{ $poin }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($s->status === 'Disetujui' && $s->pdf_path)
                        <a href="{{ route('surat-tugas.pdf', $s) }}"
                           class="mt-4 flex items-center justify-center gap-2 rounded-xl border border-merah py-2.5 text-sm font-bold text-merah hover:bg-merah-muda">
                            <i data-lucide="download" class="h-4 w-4"></i> Unduh surat tugas (PDF)
                        </a>
                    @elseif ($s->status === 'Menunggu')
                        <p class="mt-4 rounded-xl bg-krem p-3 text-center text-xs text-gray-600">
                            PDF dapat diunduh setelah surat disetujui dan ditandatangani.
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- ---------- Bagian 2: arsip per bulan (tabel ringkas) ---------- --}}
    <div class="mt-8 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <h2 class="font-bold">Arsip surat tugas</h2>
        {!! $navBulan() !!}
    </div>

    <div class="mt-3 overflow-hidden rounded-2xl border border-garis bg-white">
        <div class="hidden bg-krem px-5 py-3 text-sm font-semibold text-gray-600 {{ $kolomArsip }}">
            <span>Tanggal</span><span>Perihal</span><span>Pemberi</span><span class="text-right">Aksi</span>
        </div>

        <div class="divide-y divide-garis">
            @forelse ($arsip as $s)
                @php $disorot = $sorot === 'surat-'.$s->id; @endphp
                <div id="surat-{{ $s->id }}"
                     class="px-4 py-3 transition-colors duration-1000 md:px-5 {{ $kolomArsip }} {{ $disorot ? 'baris-sorot bg-yellow-50' : '' }}">

                    {{-- 1. Tanggal --}}
                    <div class="text-sm font-semibold text-gray-700">{{ $s->rentangTanggal() }}</div>

                    {{-- 2. Perihal + info ringkas (+ keterangan jika ada) --}}
                    <div class="mt-1 min-w-0 md:mt-0">
                        <div class="font-semibold">{{ $s->perihal }}</div>
                        <div class="text-xs text-gray-500">
                            {{ collect([
                                $s->nomor_surat ?? 'Belum bernomor',
                                $s->lokasi,
                                $jenisKegiatan($s),
                                $s->jam_mulai ? $s->rentangJam() : null,
                            ])->filter()->join(' · ') }}
                            <span class="md:hidden"> · {{ $s->pemberi_tugas }}</span>
                        </div>

                        @if ($poin = $s->poinKeterangan())
                            <details class="group mt-1">
                                <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-xs font-semibold text-navy">
                                    <i data-lucide="chevron-right" class="h-3.5 w-3.5 transition-transform group-open:rotate-90"></i>
                                    Lihat keterangan ({{ count($poin) }})
                                </summary>
                                <ul class="mt-2 list-disc rounded-xl bg-yellow-50 py-2 pl-7 pr-3 text-xs text-yellow-900">
                                    @foreach ($poin as $p)
                                        <li>{{ $p }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>

                    {{-- 3. Pemberi (laptop) --}}
                    <div class="hidden text-sm text-gray-600 md:block">{{ $s->pemberi_tugas }}</div>

                    {{-- 4. Aksi --}}
                    <div class="mt-2 flex md:mt-0 md:justify-end">
                        @if ($s->status === 'Disetujui' && $s->pdf_path)
                            <a href="{{ route('surat-tugas.pdf', $s) }}"
                               class="inline-flex items-center gap-1 whitespace-nowrap rounded-lg border border-merah/40 bg-white px-3 py-1.5 text-xs font-semibold text-merah hover:bg-merah-muda">
                                <i data-lucide="download" class="h-3.5 w-3.5"></i> Unduh PDF
                            </a>
                        @else
                            <x-lencana :status="$s->labelStatus()" />
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-sm text-gray-500">
                    Tidak ada surat tugas yang selesai pada {{ $bulan->translatedFormat('F Y') }}.
                </div>
            @endforelse
        </div>
    </div>
@endif
@endsection

@push('script')
<script>
    // Sorot sebentar surat yang dibuka dari tautan "Lihat surat tugas"
    const barisSorot = document.querySelector('.baris-sorot');
    if (barisSorot) {
        barisSorot.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setTimeout(() => barisSorot.classList.remove('bg-yellow-50', 'ring-4', 'ring-yellow-200'), 3500);
    }
</script>
@endpush