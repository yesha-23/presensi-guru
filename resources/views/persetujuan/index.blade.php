@extends('layouts.app')

@section('konten')
@php
    $rentang = fn ($p) => $p->tanggal_mulai->equalTo($p->tanggal_selesai)
        ? $p->tanggal_mulai->translatedFormat('l, j F Y')
        : $p->tanggal_mulai->translatedFormat('j M').' – '.$p->tanggal_selesai->translatedFormat('j M Y');
    $jam = fn ($t) => str_replace(':', '.', substr($t, 0, 5));
@endphp

{{-- ================= JUDUL ================= --}}
<div class="flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Persetujuan</h1>
        <p class="text-sm text-gray-500">Pengajuan yang menunggu keputusan Anda</p>
    </div>
    <div class="hidden lg:block">@include('partials.lonceng')</div>
</div>

{{-- ================= TAB: HP 2×2, laptop 1 baris ================= --}}
<div class="mt-5 grid grid-cols-2 gap-2 md:flex md:flex-wrap">
    @foreach ($tabs as $kunci => [$label, $jumlah])
        <a href="{{ route('persetujuan', ['tab' => $kunci]) }}"
           class="truncate rounded-full border px-3 py-2 text-center text-sm font-semibold md:px-4
                  {{ $tab === $kunci ? 'tab-aktif' : 'tab-biasa' }}">
            {{ $label }} · {{ $jumlah }}
        </a>
    @endforeach
</div>

<div class="mt-5">
@if ($tab === 'lembur')
    {{-- ================= TAB LEMBUR ================= --}}
    @if ($lemburDipilih)
        @include('persetujuan._navigasi', ['koleksi' => $menungguLembur, 'aktif' => $lemburDipilih->id, 'tab' => 'lembur'])
        @include('persetujuan._lembur')
    @else
        @include('persetujuan._daftar', [
            'kosong' => 'Tidak ada pengajuan lembur yang menunggu keputusan Anda.',
            'urutan' => 'Diurutkan dari tanggal pelaksanaan terdekat',
            'baris'  => $menungguLembur->map(fn ($l) => [
                'url'      => route('persetujuan', ['tab' => 'lembur', 'id' => $l->id]),
                'jenis'    => 'Lembur',
                'warna'    => 'text-orange-800',
                'judul'    => $l->user->nama_lengkap,
                'ket'      => $l->tanggal->translatedFormat('l, j F Y').' · '.$jam($l->jam_mulai).'–'.$jam($l->jam_selesai),
                'diajukan' => $l->created_at,
                'penanda'  => $l->updated_at?->gt($l->created_at->copy()->addMinute()) ? 'Diubah pegawai' : null,
            ]),
        ])
        @include('persetujuan._diproses', ['baris' => $diprosesLembur->map(fn ($l) => [
            'nama'    => $l->user?->nama_lengkap,
            'jenis'   => 'Lembur '.$jam($l->jam_mulai).'–'.$jam($l->jam_selesai),
            'tanggal' => $l->tanggal->translatedFormat('j F Y'),
            'status'  => $l->status,
            'waktu'   => $l->diproses_at,
            'catatan' => $l->alasan_penolakan,
        ])])
    @endif

@elseif ($tab === 'surat')
    {{-- ================= TAB SURAT TUGAS ================= --}}
    @if ($suratDipilih)
        @include('persetujuan._navigasi', ['koleksi' => $menungguSurat, 'aktif' => $suratDipilih->id, 'tab' => 'surat'])
        @include('persetujuan._surat')
    @else
        @if (! $ttdSaya && $menungguSurat->isNotEmpty())
            <div class="mb-4 rounded-2xl bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
                Anda belum mengunggah gambar tanda tangan. Buka salah satu surat, lalu unggah tanda tangan di panel kanan sebelum menyetujui.
            </div>
        @endif
        @include('persetujuan._daftar', [
            'kosong' => 'Tidak ada surat tugas yang menunggu tanda tangan Anda.',
            'urutan' => 'Diurutkan dari tanggal pelaksanaan terdekat',
            'baris'  => $menungguSurat->map(fn ($s) => [
                'url'      => route('persetujuan', ['tab' => 'surat', 'id' => $s->id]),
                'jenis'    => 'Surat tugas'.($s->kasus_khusus ? ' · Luar kota / kasus khusus' : ''),
                'warna'    => 'text-emerald-800',
                'judul'    => $s->perihal,
                'ket'      => $s->rentangTanggal().' · '.$s->pegawai->count().' pegawai · '.$s->lokasi,
                'diajukan' => $s->updated_at,
                'penanda'  => $s->hasilRevisi() ? 'Hasil revisi' : ($s->diajukan_oleh ? 'Usulan pegawai' : null),
            ]),
        ])
        @include('persetujuan._diproses', ['kolomNama' => 'Perihal', 'baris' => $diprosesSurat->map(fn ($p) => [
            'nama'        => $p->suratTugas->perihal,
            'jenis'       => 'Surat tugas'.($p->suratTugas->nomor_surat ? ' · '.$p->suratTugas->nomor_surat : ''),
            'tanggal'     => $p->suratTugas->rentangTanggal(),
            'status'      => $p->status,
            'waktu'       => $p->ditandatangani_at ?? $p->updated_at,
            'catatan'     => $p->catatan,
            'url'         => $p->suratTugas->status === 'Disetujui' && $p->suratTugas->pdf_path
                ? route('surat-tugas.pdf', $p->suratTugas)
                : null,
            'ket_dokumen' => 'Surat '.mb_strtolower($p->suratTugas->labelStatus()),
        ])])
    @endif

@elseif ($tab === 'koreksi')
    {{-- ================= TAB KOREKSI PRESENSI ADMIN ================= --}}
    @if ($koreksiDipilih)
        @include('persetujuan._navigasi', ['koleksi' => $menungguKoreksi, 'aktif' => $koreksiDipilih->id, 'tab' => 'koreksi'])
        @include('persetujuan._koreksi')
    @else
        @include('persetujuan._daftar', [
            'kosong' => 'Tidak ada koreksi presensi administrator yang menunggu persetujuan Anda.',
            'urutan' => 'Diurutkan dari waktu diajukan (terlama di atas)',
            'baris'  => $menungguKoreksi->map(fn ($log) => [
                'url'      => route('persetujuan', ['tab' => 'koreksi', 'id' => $log->id]),
                'jenis'    => 'Koreksi presensi',
                'warna'    => 'text-navy',
                'judul'    => $log->target->user->nama_lengkap,
                'ket'      => $log->target->tanggal->translatedFormat('l, j F Y').' · oleh '.($log->pengubah?->nama_lengkap ?? '-'),
                'diajukan' => $log->created_at,
            ]),
        ])
    @endif

@else
    {{-- ================= TAB IZIN & SAKIT ================= --}}
    @if ($dipilih)
        @include('persetujuan._navigasi', ['koleksi' => $menunggu, 'aktif' => $dipilih->id, 'tab' => 'izin'])
        @include('persetujuan._izin')
    @else
        @include('persetujuan._daftar', [
            'kosong' => 'Tidak ada pengajuan izin atau sakit yang menunggu keputusan Anda.',
            'urutan' => 'Diurutkan dari tanggal pelaksanaan terdekat',
            'baris'  => $menunggu->map(fn ($p) => [
                'url'      => route('persetujuan', ['tab' => 'izin', 'id' => $p->id]),
                'jenis'    => $p->jenis.$p->labelWaktu(),
                'warna'    => 'text-cyan-800',
                'judul'    => $p->user->nama_lengkap,
                'ket'      => $rentang($p).' · '.$p->user->kategori.' '.$p->user->unit->label(),
                'diajukan' => $p->created_at,
                'penanda'  => $p->updated_at?->gt($p->created_at->copy()->addMinute()) ? 'Diubah pegawai' : null,
            ]),
        ])
        @include('persetujuan._diproses', ['baris' => $diproses->map(fn ($p) => [
            'nama'    => $p->user?->nama_lengkap,
            'jenis'   => $p->jenis,
            'tanggal' => $rentang($p),
            'status'  => $p->status,
            'waktu'   => $p->diproses_at,
            'catatan' => $p->alasan_penolakan,
        ])])
    @endif
@endif
</div>
@endsection
