@extends('layouts.app')

@section('konten')
@php
    $bisaUbah = in_array($surat->status, ['Draf', 'Ditolak'], true);
    $ditolak = $surat->persetujuan->firstWhere('status', 'Ditolak');
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <a href="{{ route('admin.surat-tugas') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-navy">
            <i data-lucide="chevron-left" class="h-4 w-4"></i> Kembali ke daftar
        </a>
        <h1 class="mt-1 text-2xl font-extrabold lg:text-3xl">{{ $surat->perihal }}</h1>
        <p class="text-sm text-gray-500">{{ $surat->nomor_surat ?? 'Nomor dibuat setelah disetujui' }}</p>
    </div>
    <x-lencana :status="$surat->labelStatus()" />
</div>

<div class="mt-5">@include('surat-tugas._tahapan')</div>

@if ($ditolak)
    <div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm text-red-800">
        <b>Ditolak oleh {{ $ditolak->penyetuju?->nama_lengkap }}.</b> Catatan: {{ $ditolak->catatan }}
        <div class="mt-1 text-xs">Perbaiki surat lewat tombol Ubah di daftar surat tugas, lalu kirim ulang.</div>
    </div>
@endif

<div class="mt-5 grid gap-6 xl:grid-cols-[1fr_22rem] xl:items-start">
    <div class="overflow-x-auto rounded-3xl border border-garis bg-gray-100 p-3 sm:p-5">
        <div class="shadow-md">@include('surat-tugas._isi', $dataSurat)</div>
    </div>

    <div class="space-y-4">
        <div class="rounded-2xl border border-garis bg-white p-5 text-sm">
            <h2 class="font-bold">Rincian</h2>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Pemberi tugas</dt><dd class="font-semibold">{{ $surat->pemberi_tugas }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Jenis</dt><dd class="font-semibold">{{ $surat->jenis_kegiatan === 'Dalam' ? 'Dalam sekolah (KDLM)' : 'Luar sekolah (KKLR)' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Sifat</dt><dd class="font-semibold">{{ $surat->sifat }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Kasus khusus</dt><dd class="font-semibold">{{ $surat->kasus_khusus ? 'Ya' : 'Tidak' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Pegawai</dt><dd class="font-semibold">{{ $surat->pegawai->count() }} orang</dd></div>
            </dl>
        </div>

        @if ($surat->lampiran_path)
            <a href="{{ route('surat-tugas.lampiran', $surat) }}" target="_blank"
               class="flex items-center justify-center gap-2 rounded-xl border border-garis bg-white py-2.5 text-sm font-semibold hover:bg-gray-50">
                <i data-lucide="paperclip" class="h-4 w-4"></i> Lihat lampiran
            </a>
        @endif

        @if ($surat->status === 'Disetujui' && $surat->pdf_path)
            <a href="{{ route('surat-tugas.pdf', $surat) }}"
               class="flex items-center justify-center gap-2 rounded-xl bg-merah py-3 text-sm font-bold text-white hover:bg-merah-tua">
                <i data-lucide="download" class="h-4 w-4"></i> Unduh surat tugas (PDF)
            </a>
        @endif

        @if ($bisaUbah)
            <form method="POST" action="{{ route('admin.surat-tugas.kirim', $surat) }}">
                @csrf
                <button class="w-full rounded-xl bg-merah py-3 text-sm font-bold text-white hover:bg-merah-tua">
                    {{ $surat->status === 'Ditolak' ? 'Kirim ulang ke Kepala Sekolah' : 'Kirim ke Kepala Sekolah' }}
                </button>
            </form>
            <form method="POST" action="{{ route('admin.surat-tugas.destroy', $surat) }}"
                  onsubmit="return confirm('Hapus surat tugas ini?')">
                @csrf
                @method('DELETE')
                <button class="w-full rounded-xl border border-red-200 bg-white py-2.5 text-sm font-semibold text-merah hover:bg-merah-muda">
                    Hapus surat
                </button>
            </form>
        @endif
    </div>
</div>
@endsection