@extends('layouts.app')

@section('konten')
@php
    $bisaUbah = $surat->bisaDiubah();
    $ditolak = $surat->persetujuan->firstWhere('status', 'Ditolak');
    $revisi = $surat->status === 'Perlu Revisi' ? $surat->catatanRevisi() : null;
    $kelasTombolUtama = 'w-full rounded-xl bg-merah py-3 text-sm font-bold text-white hover:bg-merah-tua';
    $kelasTombolKedua = 'w-full rounded-xl border border-red-200 bg-white py-2.5 text-sm font-semibold text-merah hover:bg-merah-muda';
    $pesanHapus = 'Hapus surat tugas "'.$surat->perihal.'"?';
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

@if ($surat->pengusul)
    <div class="mt-4 rounded-2xl bg-blue-50 p-4 text-sm text-blue-900">
        <b>Usulan dari {{ $surat->pengusul->nama_lengkap }}</b> ({{ $surat->pengusul->unit?->label() }}),
        diajukan {{ $surat->created_at->translatedFormat('j F Y, H.i') }}.
        @if ($surat->status === 'Diajukan')
            <div class="mt-1 text-xs">Periksa data dan lampiran undangan. Klik <b>Proses usulan</b> untuk mulai melengkapi (pegawai lain, luar kota, dll.), atau tolak dengan alasan.</div>
        @endif
    </div>
@endif

@if ($surat->status === 'Ditolak' && $surat->catatan_usulan)
    <div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm text-red-800">
        <b>Usulan ditolak administrator ({{ $surat->pembuat?->nama_lengkap }}).</b> Alasan: {{ $surat->catatan_usulan }}
    </div>
@elseif ($ditolak)
    <div class="mt-4 rounded-2xl bg-red-50 p-4 text-sm text-red-800">
        <b>Ditolak oleh {{ $ditolak->penyetuju?->nama_lengkap }}.</b> Catatan: {{ $ditolak->catatan }}
        <div class="mt-1 text-xs">Penolakan bersifat final; surat ini tidak dapat dikirim ulang. Buat surat baru bila tugas tetap diperlukan.</div>
    </div>
@endif

@if ($surat->status === 'Perlu Revisi')
    <div class="mt-4 rounded-2xl bg-orange-50 p-4 text-sm text-orange-900">
        <b>Perlu revisi{{ $revisi?->penyetuju ? ' menurut '.$revisi->penyetuju->nama_lengkap : '' }}.</b>
        Catatan: {{ $surat->catatan_revisi ?? $revisi?->catatan }}
        <div class="mt-1 text-xs">Perbaiki surat lewat ikon pensil di daftar surat tugas, lalu tuliskan apa yang diperbaiki dan kirim ulang.</div>
    </div>
@elseif ($surat->hasilRevisi() || ($surat->status === 'Disetujui' && $surat->catatan_revisi))
    {{-- Riwayat revisi terakhir (B5) --}}
    <div class="mt-4 rounded-2xl bg-blue-50 p-4 text-sm text-blue-900">
        <b>Surat hasil revisi.</b>
        <div class="mt-1"><span class="font-semibold">Catatan revisi:</span> {{ $surat->catatan_revisi }}</div>
        <div><span class="font-semibold">Yang diperbaiki:</span> {{ $surat->catatan_perbaikan ?? '-' }}</div>
    </div>
@endif

<div class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem] xl:items-start">
    @include('surat-tugas._pratinjau')

    <div class="space-y-4">
        <div class="rounded-2xl border border-garis bg-white p-5 text-sm">
            <h2 class="font-bold">Rincian</h2>
            <dl class="mt-3 space-y-2">
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Waktu</dt><dd class="text-right font-semibold">{{ $surat->rentangJam() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Pemberi tugas</dt><dd class="font-semibold">{{ $surat->pemberi_tugas }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Jenis</dt><dd class="font-semibold">{{ $surat->jenis_kegiatan === 'Dalam' ? 'Dalam sekolah (KDLM)' : 'Luar sekolah (KKLR)' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Sifat</dt><dd class="font-semibold">{{ $surat->sifat }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-gray-500">Luar kota / kasus khusus</dt><dd class="font-semibold">{{ $surat->kasus_khusus ? 'Ya' : 'Tidak' }}</dd></div>
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

        {{-- Usulan pegawai: proses atau tolak --}}
        @if ($surat->status === 'Diajukan')
            <form method="POST" action="{{ route('admin.surat-tugas.proses', $surat) }}">
                @csrf
                <button class="{{ $kelasTombolUtama }}">Proses usulan</button>
            </form>
        @endif

        @if ($surat->pengusul && in_array($surat->status, ['Diajukan', 'Diproses'], true))
            <form method="POST" action="{{ route('admin.surat-tugas.tolak-usulan', $surat) }}" class="rounded-2xl border border-garis bg-white p-4">
                @csrf
                <label class="block text-sm font-bold">Tolak usulan
                    <textarea name="alasan" rows="2" maxlength="500" placeholder="Alasan penolakan (wajib)"
                              class="mt-1 w-full rounded-xl border border-garis px-3 py-2 text-sm font-normal outline-none focus:border-navy">{{ old('alasan') }}</textarea>
                </label>
                @error('alasan') <p class="mt-1 text-xs text-merah">{{ $message }}</p> @enderror
                <button class="mt-2 {{ $kelasTombolKedua }}">Tolak usulan</button>
            </form>
        @endif

        @if ($bisaUbah)
            <form method="POST" action="{{ route('admin.surat-tugas.kirim', $surat) }}"
                  @class(['rounded-2xl border border-garis bg-white p-4' => $surat->status === 'Perlu Revisi'])>
                @csrf
                @if ($surat->status === 'Perlu Revisi')
                    <label class="block text-sm font-bold">Apa yang diperbaiki
                        <textarea name="catatan_perbaikan" rows="3" maxlength="500"
                                  placeholder="Contoh: Lokasi diganti menjadi Aula Dinas Pendidikan Sleman; jam mulai 08.00."
                                  class="mt-1 w-full rounded-xl border border-garis px-3 py-2 text-sm font-normal outline-none focus:border-navy">{{ old('catatan_perbaikan', $surat->catatan_perbaikan) }}</textarea>
                    </label>
                    @error('catatan_perbaikan') <p class="mt-1 text-xs text-merah">{{ $message }}</p> @enderror
                    <p class="mb-3 mt-1 text-xs text-gray-500">Dibaca kepala sekolah dan Ketua Yayasan saat meninjau surat hasil revisi.</p>
                @endif
                <button class="{{ $kelasTombolUtama }}">
                    {{ $surat->status === 'Perlu Revisi' ? 'Kirim ulang ke Kepala Sekolah' : 'Kirim ke Kepala Sekolah' }}
                </button>
            </form>
            <a href="{{ route('admin.surat-tugas') }}" class="block text-center text-xs text-gray-500">
                Ubah isi surat lewat ikon pensil di daftar surat tugas.
            </a>
        @endif

        @if ($surat->bisaDihapus())
            <form method="POST" action="{{ route('admin.surat-tugas.destroy', $surat) }}"
                  data-konfirmasi="{{ $pesanHapus }}" onsubmit="return confirm(this.dataset.konfirmasi)">
                @csrf
                @method('DELETE')
                <button class="{{ $kelasTombolKedua }}">Hapus surat</button>
            </form>
        @endif
    </div>
</div>
@endsection