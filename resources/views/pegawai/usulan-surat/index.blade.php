@extends('layouts.app')

@section('konten')
@php
    // Keterangan tambahan hanya untuk status yang butuh penjelasan (status lain cukup lencana)
    $penjelasan = [
        'Diajukan'     => 'Masih dapat diubah atau dibatalkan sampai diproses administrator.',
        'Perlu Revisi' => 'Sedang diperbaiki administrator sesuai catatan kepala sekolah.',
    ];
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Usulan Surat Tugas</h1>
        <p class="text-sm text-gray-500">Usulkan surat tugas untuk undangan atau tugas yang Anda terima langsung</p>
    </div>
    <a href="{{ route('pegawai.usulan-surat.baru') }}"
       class="inline-flex items-center gap-2 rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">
        <i data-lucide="plus" class="h-4 w-4"></i> Usulkan surat tugas
    </a>
</div>

<div class="mt-6 overflow-hidden rounded-2xl border border-garis bg-white">
    <div class="divide-y divide-garis">
        @forelse ($usulan as $u)
            @php
                $catatan = $u->status === 'Ditolak'
                    ? ($u->catatan_usulan ?? $u->persetujuan->firstWhere('status', 'Ditolak')?->catatan)
                    : null;
                $pesanBatal = 'Batalkan usulan "'.$u->perihal.'"? Lampiran ikut dihapus.';
                $disorot = (int) $sorot === $u->id;
            @endphp
            <div class="flex flex-col gap-3 px-4 py-4 transition-colors duration-1000 md:flex-row md:items-start md:justify-between md:px-5 {{ $disorot ? 'baris-sorot bg-yellow-50' : '' }}">
                <div class="min-w-0">
                    <div class="font-semibold">{{ $u->perihal }}</div>
                    <div class="mt-0.5 text-sm text-gray-500">
                        {{ $u->rentangTanggal() }}{{ $u->jam_mulai ? ' · '.$u->rentangJam() : '' }} · {{ $u->lokasi }}
                    </div>
                    <div class="text-xs text-gray-500">
                        Diajukan {{ $u->created_at->translatedFormat('j M Y, H.i') }}
                        @if ($u->nomor_surat) · Nomor {{ $u->nomor_surat }} @endif
                    </div>
                    @if (isset($penjelasan[$u->status]))
                        <p class="mt-1 text-xs text-gray-600">{{ $penjelasan[$u->status] }}</p>
                    @endif
                    @if ($catatan)
                        <p class="mt-1 inline-block rounded-lg bg-red-50 px-2 py-1 text-xs text-red-800">Alasan: {{ $catatan }}</p>
                    @endif
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2 md:justify-end">
                    <x-lencana :status="$u->labelStatus()" />

                    @if ($u->status === 'Diajukan')
                        <a href="{{ route('pegawai.usulan-surat.edit', $u) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-garis px-3 py-1.5 text-xs font-semibold hover:bg-gray-50">
                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Ubah
                        </a>
                        <form method="POST" action="{{ route('pegawai.usulan-surat.batal', $u) }}"
                              data-konfirmasi="{{ $pesanBatal }}" onsubmit="return confirm(this.dataset.konfirmasi)">
                            @csrf @method('DELETE')
                            <button class="inline-flex items-center gap-1 rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-merah hover:bg-merah-muda">
                                <i data-lucide="x" class="h-3.5 w-3.5"></i> Batalkan
                            </button>
                        </form>
                    @endif

                    @if ($u->lampiran_path)
                        <a href="{{ route('surat-tugas.lampiran', $u) }}" target="_blank" title="Lihat undangan"
                           class="inline-flex items-center gap-1 rounded-lg border border-garis px-3 py-1.5 text-xs font-semibold hover:bg-gray-50">
                            <i data-lucide="paperclip" class="h-3.5 w-3.5"></i> Undangan
                        </a>
                    @endif

                    @if ($u->status === 'Disetujui' && $u->pdf_path)
                        <a href="{{ route('surat-tugas.pdf', $u) }}"
                           class="inline-flex items-center gap-1 rounded-lg border border-merah px-3 py-1.5 text-xs font-semibold text-merah hover:bg-merah-muda">
                            <i data-lucide="download" class="h-3.5 w-3.5"></i> Unduh PDF
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-6 py-12 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-krem text-merah"><i data-lucide="send" class="h-6 w-6"></i></span>
                <p class="mt-3 font-semibold">Belum ada usulan surat tugas</p>
                <p class="mt-1 text-sm text-gray-500">Menerima undangan lomba, rapat dinas, atau pelatihan? Klik <b>Usulkan surat tugas</b>.</p>
            </div>
        @endforelse
    </div>

    @include('partials.paginasi', ['paginator' => $usulan, 'satuan' => 'usulan'])
</div>

<p class="mt-3 text-xs text-gray-500">
    Surat tugas yang sudah terbit juga tampil di menu <a href="{{ route('pegawai.riwayat', ['tab' => 'surat-tugas']) }}" class="font-semibold text-navy underline">Riwayat › Surat Tugas</a>.
</p>
@endsection

@push('script')
<script>
    // Sorot sebentar usulan yang baru dikirim / diubah
    const barisSorot = document.querySelector('.baris-sorot');
    if (barisSorot) {
        barisSorot.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setTimeout(() => barisSorot.classList.remove('bg-yellow-50'), 3500);
    }
</script>
@endpush
