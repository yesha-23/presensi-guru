@extends('layouts.app')

@section('konten')
@php
    // 5 kolom (Jenis | Detail | Diajukan | Status | Aksi) mulai layar xl; di bawahnya bertumpuk
    $kolomPengajuan = 'xl:grid xl:grid-cols-[6rem_minmax(0,1fr)_9rem_7rem_16rem] xl:items-start xl:gap-4';
    $tombolAksi = 'inline-flex items-center gap-1 rounded-lg border bg-white px-3 py-1.5 text-xs font-semibold whitespace-nowrap';
    $warnaAngka = ['Menunggu' => 'text-yellow-700', 'Disetujui' => 'text-emerald-700', 'Ditolak' => 'text-merah'];
@endphp

<h1 class="text-2xl font-extrabold lg:text-3xl">Pengajuan</h1>
<p class="mt-1 text-sm text-gray-500">Ajukan izin, sakit, atau lembur dan pantau statusnya di sini.</p>

{{-- ================= BARIS 1: tombol ajukan ================= --}}
<div class="mt-5 grid gap-3 sm:grid-cols-2">
    <a href="{{ route('pegawai.izin') }}" class="flex items-center gap-4 rounded-2xl border border-garis bg-white p-4 hover:border-merah/40 hover:bg-red-50/40">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-red-50 text-merah">
            <i data-lucide="file-text" class="h-6 w-6"></i>
        </span>
        <span class="min-w-0 flex-1 leading-tight">
            <span class="block font-bold">Ajukan izin / sakit</span>
            <span class="block text-sm text-gray-500">Izin paling lambat H-1, sakit paling lambat H+1, wajib bukti</span>
        </span>
        <i data-lucide="chevron-right" class="h-5 w-5 shrink-0 text-gray-400"></i>
    </a>

    <a href="{{ route('pegawai.lembur') }}" class="flex items-center gap-4 rounded-2xl border border-garis bg-white p-4 hover:border-merah/40 hover:bg-red-50/40">
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-red-50 text-merah">
            <i data-lucide="clock" class="h-6 w-6"></i>
        </span>
        <span class="min-w-0 flex-1 leading-tight">
            <span class="block font-bold">Ajukan lembur</span>
            <span class="block text-sm text-gray-500">Kemarin sampai 30 hari ke depan, setelah jadwal pulang</span>
        </span>
        <i data-lucide="chevron-right" class="h-5 w-5 shrink-0 text-gray-400"></i>
    </a>
</div>

{{-- ================= BARIS 2: ringkasan (klik = filter) ================= --}}
<div class="mt-4 grid grid-cols-3 gap-2 md:gap-3">
    @foreach ($ringkas as $label => $jumlah)
        @php $aktif = $status === $label; @endphp
        <a href="{{ $aktif ? route('pegawai.pengajuan') : route('pegawai.pengajuan', ['status' => $label]) }}"
           class="rounded-2xl border-2 bg-white p-3 text-center md:p-4 {{ $aktif ? 'border-merah' : 'border-transparent hover:border-garis' }}"
           title="{{ $aktif ? 'Tampilkan semua' : 'Tampilkan yang '.strtolower($label) }}">
            <div class="text-2xl font-extrabold md:text-3xl {{ $jumlah ? $warnaAngka[$label] : '' }}">{{ $jumlah }}</div>
            <div class="text-xs text-gray-500 md:text-sm">{{ $label }}</div>
        </a>
    @endforeach
</div>

@if ($status)
    <div class="mt-3 flex items-center gap-2 text-sm">
        <span class="text-gray-600">Menampilkan pengajuan <b>{{ strtolower($status) }}</b>.</span>
        <a href="{{ route('pegawai.pengajuan') }}" class="font-semibold text-navy underline">Tampilkan semua</a>
    </div>
@endif

{{-- ================= BARIS 3: daftar pengajuan ================= --}}
<div class="mt-4 overflow-hidden rounded-2xl border border-garis bg-white">
    <div class="hidden bg-krem px-5 py-3 text-sm font-semibold text-gray-600 {{ $kolomPengajuan }}">
        <span>Jenis</span><span>Detail</span><span>Diajukan</span><span>Status</span><span class="text-right">Aksi</span>
    </div>

    <div class="divide-y divide-garis">
        @forelse ($pengajuan as $a)
            @php $adaAksi = $a['bukti'] || $a['edit'] || $a['batal']; @endphp

            <div id="pengajuan-{{ $a['kunci'] }}"
                 class="px-4 py-3 transition-colors duration-1000 xl:px-5 {{ $kolomPengajuan }} {{ $sorot === $a['kunci'] ? 'baris-sorot bg-yellow-50' : '' }}">

                {{-- 1. Jenis (HP: + status sebaris) --}}
                <div class="flex items-center justify-between gap-3 xl:block">
                    <span class="text-xs font-bold uppercase xl:text-sm {{ $a['jenis'] === 'Lembur' ? 'text-orange-800' : 'text-cyan-800' }}">{{ $a['jenis'] }}</span>
                    <span class="xl:hidden"><x-lencana :status="$a['status']" /></span>
                </div>

                {{-- 2. Detail --}}
                <div class="mt-1 min-w-0 xl:mt-0">
                    <div class="font-semibold">{{ $a['judul'] }}</div>
                    @if ($a['uraian'])
                        <p class="mt-0.5 line-clamp-1 text-sm text-gray-600" title="{{ $a['uraian'] }}">{{ $a['uraian'] }}</p>
                    @endif

                    <div class="mt-0.5 text-xs text-gray-500 xl:hidden">
                        Diajukan {{ $a['waktu']->translatedFormat('j M Y, H.i') }}
                        @if ($a['diubah']) · diubah {{ $a['diubah']->translatedFormat('j M, H.i') }} @endif
                    </div>

                    @if ($a['status'] === 'Ditolak' && $a['catatan'])
                        <p class="mt-1 rounded-lg bg-red-50 px-2 py-1 text-xs text-red-800">Alasan ditolak: {{ $a['catatan'] }}</p>
                    @endif
                </div>

                {{-- 3. Diajukan --}}
                <div class="hidden text-sm text-gray-500 xl:block">
                    {{ $a['waktu']->translatedFormat('j M Y, H.i') }}
                    @if ($a['diubah'])
                        <span class="block text-xs text-blue-700">diubah {{ $a['diubah']->translatedFormat('j M, H.i') }}</span>
                    @endif
                </div>

                {{-- 4. Status --}}
                <div class="hidden xl:block"><x-lencana :status="$a['status']" /></div>

                {{-- 5. Aksi --}}
                @if ($adaAksi)
                    <div class="mt-2 flex flex-wrap items-center gap-2 xl:mt-0 xl:justify-end">
                        @if ($a['bukti'])
                            <a href="{{ $a['bukti'] }}" target="_blank" class="{{ $tombolAksi }} border-garis text-navy hover:bg-gray-50">
                                <i data-lucide="paperclip" class="h-3.5 w-3.5"></i> Lihat bukti
                            </a>
                        @endif
                        @if ($a['edit'])
                            <a href="{{ $a['edit'] }}" class="{{ $tombolAksi }} border-garis hover:bg-gray-50">
                                <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
                            </a>
                        @endif
                        @if ($a['batal'])
                            <form method="POST" action="{{ $a['batal'] }}"
                                  onsubmit="return confirm('Batalkan pengajuan {{ strtolower($a['jenis']) }} ini? Data akan dihapus dan penyetuju akan diberi tahu.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="{{ $tombolAksi }} border-red-200 text-merah hover:bg-red-50">
                                    <i data-lucide="x" class="h-3.5 w-3.5"></i> Batalkan
                                </button>
                            </form>
                        @endif
                    </div>
                @else
                    <div class="hidden text-right text-gray-400 xl:block">—</div>
                @endif
            </div>
        @empty
            <div class="p-10 text-center text-sm text-gray-500">
                {{ $status ? 'Tidak ada pengajuan '.strtolower($status).'.' : 'Belum ada pengajuan. Gunakan tombol di atas untuk mengajukan.' }}
            </div>
        @endforelse
    </div>
</div>

<p class="mt-3 text-xs text-gray-500">
    Pengajuan berstatus Menunggu masih dapat diedit atau dibatalkan. Pengajuan yang sudah disetujui atau ditolak tidak dapat diubah.
</p>
@endsection

@push('script')
<script>
    // Sorot sebentar baris yang baru dikirim / diedit
    const barisSorot = document.querySelector('.baris-sorot');
    if (barisSorot) {
        barisSorot.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setTimeout(() => barisSorot.classList.remove('bg-yellow-50'), 3500);
    }
</script>
@endpush