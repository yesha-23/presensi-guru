@extends('layouts.app')

@section('konten')
<div class="flex items-center gap-3">
    <a href="{{ route('pegawai.beranda') }}" class="rounded-xl border border-garis bg-white p-2.5" aria-label="Kembali">
        <i data-lucide="chevron-left" class="h-5 w-5"></i>
    </a>
    <div>
        <h1 class="text-2xl font-extrabold">Surat Tugas Saya</h1>
        <p class="text-sm text-gray-500">Diterbitkan administrator, tidak perlu presensi lokasi</p>
    </div>
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-2">
    @forelse ($surat as $s)
        @php
            $hariIni = today()->between($s->tanggal_mulai, $s->tanggal_selesai);
            $jenis = $s->jenis_kegiatan === 'Dalam' ? 'Kegiatan dalam' : 'Kegiatan luar';
        @endphp
        <div class="rounded-3xl border-2 bg-white p-5 {{ $hariIni && $s->status === 'Disetujui' ? 'border-merah' : 'border-garis' }}">
            <div class="flex items-start justify-between gap-3">
                <span class="text-xs font-bold uppercase {{ $hariIni ? 'text-merah' : 'text-emerald-800' }}">
                    {{ $hariIni ? 'Hari ini · ' : '' }}{{ $jenis }}
                </span>
                <x-lencana :status="$s->labelStatus()" />
            </div>

            <h2 class="mt-2 text-lg font-extrabold">{{ $s->perihal }}</h2>

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
                <dd class="font-semibold">{{ $s->lokasi }}</dd>
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
            @endif
        </div>
    @empty
        <div class="rounded-3xl border border-dashed border-garis bg-white p-8 text-center text-sm text-gray-500 lg:col-span-2">
            Belum ada surat tugas untuk Anda.
        </div>
    @endforelse
</div>
@endsection