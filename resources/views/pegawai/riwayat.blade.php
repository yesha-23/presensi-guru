@extends('layouts.app')

@section('konten')
@php
    $sebelum = $bulan->copy()->subMonth()->format('Y-m');
    $sesudah = $bulan->copy()->addMonth();
    $bisaMaju = $sesudah->lte(today());
    $kolomPresensi = 'md:grid md:grid-cols-[9rem_9rem_9rem_minmax(0,1fr)_11rem] md:items-center md:gap-4';
    $sorot = $sorot ?? null;
@endphp

<h1 class="text-2xl font-extrabold lg:text-3xl">Riwayat</h1>

{{-- ================= BARIS 1: tab + navigasi bulan ================= --}}
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
                    $urlSurat = $h['surat_id']
                        ? route('pegawai.riwayat', ['tab' => 'surat-tugas', 'sorot' => 'surat-'.$h['surat_id']])
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
@else
    {{-- ================= TAB SURAT TUGAS ================= --}}
    <div class="mt-4 grid grid-cols-3 gap-2 md:gap-3">
        @foreach ($ringkasSurat as $label => $jumlah)
            <div class="rounded-2xl bg-white p-3 text-center md:p-4">
                <div class="text-2xl font-extrabold md:text-3xl {{ $label === 'Berlangsung' && $jumlah ? 'text-merah' : ($label === 'Akan datang' && $jumlah ? 'text-navy' : '') }}">{{ $jumlah }}</div>
                <div class="text-xs text-gray-500 md:text-sm">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @forelse ($surat as $s)
            @php
                $berlangsung = $s->tahap === 'Berlangsung' && $s->status === 'Disetujui';
                $jenis = $s->jenis_kegiatan === 'Dalam' ? 'Kegiatan dalam' : 'Kegiatan luar';
                $warnaTahap = ['Berlangsung' => 'text-merah', 'Akan datang' => 'text-navy', 'Selesai' => 'text-gray-500'][$s->tahap];
                $disorot = $sorot === 'surat-'.$s->id;
            @endphp
            <div id="surat-{{ $s->id }}"
                 class="rounded-3xl border-2 p-5 transition-colors duration-1000 {{ $disorot ? 'baris-sorot bg-yellow-50' : 'bg-white' }} {{ $berlangsung || $disorot ? 'border-merah' : 'border-garis' }}">
                <div class="flex items-start justify-between gap-3">
                    <span class="text-xs font-bold uppercase {{ $warnaTahap }}">
                        {{ $s->tahap === 'Berlangsung' ? 'Hari ini' : $s->tahap }} · {{ $jenis }}
                    </span>
                    <x-lencana :status="$s->labelStatus()" />
                </div>

                <h2 class="mt-2 text-lg font-extrabold {{ $s->tahap === 'Selesai' ? 'text-gray-700' : '' }}">{{ $s->perihal }}</h2>

                <dl class="mt-3 grid grid-cols-[6rem_1fr] gap-y-1 text-sm">
                    <dt class="text-gray-500">Nomor</dt>
                    <dd class="font-semibold">{{ $s->nomor_surat ?? 'Menunggu persetujuan' }}</dd>
                    <dt class="text-gray-500">Tanggal</dt>
                    <dd class="font-semibold">{{ $s->rentangTanggal() }}</dd>
                    <dt class="text-gray-500">Waktu</dt>
                    <dd class="font-semibold">{{ $s->rentangJam() }}</dd>
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
                       class="mt-4 flex items-center justify-center gap-2 rounded-xl border border-merah bg-white py-2.5 text-sm font-bold text-merah hover:bg-merah-muda">
                        <i data-lucide="download" class="h-4 w-4"></i> Unduh surat tugas (PDF)
                    </a>
                @elseif ($s->status === 'Menunggu')
                    <p class="mt-4 rounded-xl bg-krem p-3 text-center text-xs text-gray-600">
                        PDF dapat diunduh setelah surat disetujui dan ditandatangani.
                    </p>
                @endif
            </div>
        @empty
            <div class="rounded-3xl border border-dashed border-garis bg-white p-8 text-center text-sm text-gray-500 lg:col-span-2">
                Belum ada surat tugas untuk Anda.
            </div>
        @endforelse
    </div>
@endif
@endsection

@push('script')
<script>
    // Sorot sebentar surat yang dibuka dari tautan "Lihat surat tugas"
    const barisSorot = document.querySelector('.baris-sorot');
    if (barisSorot) {
        barisSorot.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setTimeout(() => barisSorot.classList.remove('bg-yellow-50'), 3500);
        setTimeout(() => barisSorot.classList.add('bg-white'), 3500);
    }
</script>
@endpush