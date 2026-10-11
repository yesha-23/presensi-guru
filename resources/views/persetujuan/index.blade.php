@extends('layouts.app')

@section('konten')
@php
    $rentang = fn ($p) => $p->tanggal_mulai->equalTo($p->tanggal_selesai)
        ? $p->tanggal_mulai->translatedFormat('l, j F Y')
        : $p->tanggal_mulai->translatedFormat('j M').' – '.$p->tanggal_selesai->translatedFormat('j M Y');
@endphp

{{-- ================= JUDUL ================= --}}
<div class="flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Persetujuan</h1>
        <p class="text-sm text-gray-500">Pengajuan yang menunggu keputusan Anda</p>
    </div>
    <div class="hidden lg:block">@include('partials.lonceng')</div>
</div>

{{-- ================= TAB (sesuai peran, dari controller) ================= --}}
<div class="mt-5 flex flex-wrap gap-2">
    @foreach ($tabs as $kunci => [$label, $jumlah])
        <a href="{{ route('persetujuan', ['tab' => $kunci]) }}"
           class="rounded-full border px-4 py-2 text-sm font-semibold {{ $tab === $kunci ? 'border-gray-900 bg-gray-900 text-white' : 'border-garis bg-white text-gray-700 hover:bg-gray-50' }}">
            {{ $label }} · {{ $jumlah }}
        </a>
    @endforeach
</div>

@if ($tab === 'lembur')
    {{-- ================= TAB LEMBUR ================= --}}
    @include('persetujuan._lembur')

@elseif ($tab !== 'izin')
    {{-- ================= TAB LAIN (modul berikutnya) ================= --}}
    <div class="mt-6 rounded-2xl border border-dashed border-garis bg-white p-10 text-center text-gray-500">
        Persetujuan {{ strtolower($tabs[$tab][0] ?? '') }} dibangun pada modul berikutnya.
    </div>

@else
    {{-- ================= TAB IZIN & SAKIT ================= --}}
    <div class="mt-5 grid gap-5 lg:grid-cols-[22rem_1fr] lg:items-start">
        {{-- Daftar --}}
        <div class="space-y-3">
            @forelse ($menunggu as $p)
                <a href="{{ route('persetujuan', ['tab' => 'izin', 'id' => $p->id]) }}"
                   class="block rounded-2xl border-2 bg-white p-4 {{ $dipilih?->id === $p->id ? 'border-merah' : 'border-transparent hover:border-garis' }}">
                    <div class="flex justify-between text-xs">
                        <span class="font-bold uppercase text-cyan-800">
                            {{ $p->jenis }}{{ $p->jenis === 'Sakit' && $p->tanggal_mulai->lt($p->created_at->copy()->startOfDay()) ? ' · Susulan' : '' }}
                        </span>
                        <span class="text-gray-500">Diajukan {{ $p->created_at->translatedFormat('j M') }}</span>
                    </div>
                    <div class="mt-1 font-bold">{{ $p->user->nama_lengkap }}</div>
                    <div class="text-sm text-gray-500">{{ $rentang($p) }}</div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-garis bg-white p-6 text-center text-sm text-gray-500">
                    Tidak ada pengajuan yang menunggu.
                </div>
            @endforelse

            @if ($diproses->isNotEmpty())
                <div class="rounded-2xl border border-garis bg-white p-4">
                    <div class="text-sm font-bold">Terakhir diproses</div>
                    <div class="mt-2 space-y-2">
                        @foreach ($diproses as $p)
                            <div class="flex items-center justify-between gap-2 text-sm">
                                <span class="truncate">{{ $p->user?->nama_lengkap }} · {{ $p->jenis }}</span>
                                <x-lencana :status="$p->status" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Detail --}}
        @if ($dipilih)
            <div class="rounded-3xl border border-garis bg-white p-5 lg:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-bold uppercase text-cyan-800">Pengajuan {{ $dipilih->jenis }}</div>
                        <h2 class="text-2xl font-extrabold">{{ $dipilih->user->nama_lengkap }}</h2>
                        <div class="text-sm text-gray-500">{{ $dipilih->user->kategori }} · {{ $dipilih->user->unit->nama }}</div>
                    </div>
                    <x-lencana :status="$dipilih->status" />
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl bg-krem p-3">
                        <div class="text-xs text-gray-500">Tanggal</div>
                        <div class="font-bold">{{ $rentang($dipilih) }}</div>
                        <div class="text-xs text-gray-500">{{ $info['hari'] }} hari</div>
                    </div>
                    <div class="rounded-2xl bg-krem p-3">
                        <div class="text-xs text-gray-500">{{ $info['batasLabel'] }}</div>
                        <div class="font-bold {{ $info['batasOk'] ? 'text-emerald-700' : 'text-merah' }}">
                            {{ $info['batasOk'] ? 'Terpenuhi' : 'Tidak terpenuhi' }}
                        </div>
                    </div>
                    <div class="rounded-2xl bg-krem p-3">
                        <div class="text-xs text-gray-500">{{ $dipilih->jenis }} semester ini</div>
                        <div class="font-bold">{{ $info['jumlahSemester'] === null ? '–' : $info['jumlahSemester'].' kali' }}</div>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="text-sm font-bold">Alasan</div>
                    <p class="mt-1 whitespace-pre-line text-gray-700">{{ $dipilih->alasan }}</p>
                </div>

                <div class="mt-5">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-bold">Bukti pendukung</div>
                        @if ($dipilih->bukti_path)
                            <a href="{{ route('pengajuan.bukti', $dipilih) }}" target="_blank" class="text-xs font-semibold text-navy underline">Buka di tab baru</a>
                        @endif
                    </div>
                    <div class="mt-2 overflow-hidden rounded-2xl border border-garis bg-krem">
                        @if (! $dipilih->bukti_path)
                            <p class="p-6 text-center text-sm text-gray-500">Tidak ada bukti.</p>
                        @elseif ($info['pdf'])
                            <iframe src="{{ route('pengajuan.bukti', $dipilih) }}" class="h-96 w-full" title="Bukti pendukung"></iframe>
                        @else
                            <img src="{{ route('pengajuan.bukti', $dipilih) }}" alt="Bukti pendukung" class="max-h-96 w-full object-contain">
                        @endif
                    </div>
                </div>

                {{-- Satu form, dua tombol: Tolak & Setujui (formaction berbeda) --}}
                <form method="POST" action="{{ route('persetujuan.izin.tolak', $dipilih) }}" class="mt-5">
                    @csrf
                    <label class="block text-sm font-bold">Alasan penolakan <span class="font-normal text-gray-500">(wajib jika ditolak)</span>
                        <textarea name="alasan_penolakan" rows="3" maxlength="500"
                                  class="mt-1 w-full rounded-xl border border-garis px-3 py-2.5 text-sm font-normal outline-none focus:border-navy">{{ old('alasan_penolakan') }}</textarea>
                    </label>
                    @error('alasan_penolakan') <p class="mt-1 text-xs text-merah">{{ $message }}</p> @enderror

                    <div class="mt-4 flex justify-end gap-2">
                        <button type="submit" formaction="{{ route('persetujuan.izin.tolak', $dipilih) }}"
                                class="rounded-xl border border-merah bg-white px-6 py-2.5 font-semibold text-merah hover:bg-merah-muda">
                            Tolak
                        </button>
                        <button type="submit" formaction="{{ route('persetujuan.izin.setujui', $dipilih) }}"
                                class="rounded-xl bg-merah px-6 py-2.5 font-semibold text-white hover:bg-merah-tua">
                            Setujui
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
@endif
@endsection