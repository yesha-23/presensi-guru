@extends('layouts.app')

@section('konten')
<div class="flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Ringkasan Yayasan</h1>
        <p class="text-sm text-gray-500">{{ now()->translatedFormat('l, j F Y') }} · diperbarui {{ now()->format('H.i') }} WIB · hanya baca</p>
    </div>
    <div class="hidden lg:block">@include('partials.lonceng')</div>
</div>

{{-- Kartu per unit --}}
<div class="mt-5 flex flex-col gap-4 lg:flex-row">
    @foreach ($perUnit as $x)
        @php $r = $x['ringkas']; @endphp
        <div class="min-w-0 rounded-2xl border border-garis bg-white p-5 lg:flex-1">
            <div class="flex items-center justify-between">
                <span class="text-lg font-bold">{{ $x['unit']->label() }}</span>
                <span class="text-sm text-gray-500">{{ $x['hariKerja'] ? $r['total'] : $x['jumlahPegawai'] }} pegawai</span>
            </div>

            @if ($x['hariKerja'])
                <div class="mt-2 text-4xl font-extrabold">{{ $r['hadir'] }} <span class="text-xl font-semibold text-gray-500">/ {{ $r['total'] }} hadir</span></div>
                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="flex justify-between rounded-lg bg-krem px-3 py-1.5"><span>Terlambat</span><b class="text-orange-800">{{ $r['terlambat'] }}</b></div>
                    <div class="flex justify-between rounded-lg bg-krem px-3 py-1.5"><span>Izin/Sakit</span><b>{{ $r['izin_sakit'] }}</b></div>
                    <div class="flex justify-between rounded-lg bg-krem px-3 py-1.5"><span>Tugas khusus</span><b>{{ $r['tugas'] }}</b></div>
                    <div class="flex justify-between rounded-lg bg-krem px-3 py-1.5"><span>Belum</span><b class="text-merah">{{ $r['belum'] }}</b></div>
                </div>
            @else
                {{-- C3: unit libur / bukan hari kerja --}}
                <div class="mt-2 flex items-center gap-2 text-2xl font-extrabold text-gray-400">
                    <i data-lucide="calendar-off" class="h-6 w-6"></i> Bukan hari kerja
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ $x['keterangan'] }}. Tidak ada kewajiban presensi.</p>
                @if ($r['tugas'] > 0 || $r['izin_sakit'] > 0)
                    <p class="mt-3 rounded-lg bg-krem px-3 py-1.5 text-sm">
                        @if ($r['tugas'] > 0) Tugas khusus: <b>{{ $r['tugas'] }}</b> @endif
                        @if ($r['tugas'] > 0 && $r['izin_sakit'] > 0) · @endif
                        @if ($r['izin_sakit'] > 0) Izin/Sakit: <b>{{ $r['izin_sakit'] }}</b> @endif
                    </p>
                @endif
            @endif
        </div>
    @endforeach
</div>

{{-- B4: kartu tanda tangan sejajar dan setinggi kartu kehadiran bulanan --}}
<div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem] xl:items-stretch">
    {{-- Kehadiran bulan ini per unit --}}
    <div class="rounded-2xl border border-garis bg-white p-5">
        <h2 class="font-bold">Kehadiran {{ $bulanLabel }} per unit</h2>
        <p class="text-xs text-gray-500">(Hadir + Tugas Khusus) ÷ hari efektif · data s.d. {{ $akhirBulanan->translatedFormat('j F') }}</p>
        <div class="mt-4 space-y-3">
            @forelse ($bulanan as $b)
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-20 shrink-0 font-semibold">{{ $b['unit']->label() }}</span>
                    <div class="h-2.5 min-w-0 flex-1 overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-merah" style="width: {{ $b['persen'] }}%"></div>
                    </div>
                    <span class="w-40 shrink-0 text-right">
                        <b>{{ number_format($b['persen'], 1, ',', '.') }}%</b>
                        <span class="text-gray-500">· alpa {{ number_format($b['alpa'], 1, ',', '.') }}%</span>
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">Belum ada hari efektif pada periode ini.</p>
            @endforelse
        </div>
        <a href="{{ route('pkg') }}" class="mt-4 inline-block text-sm font-semibold text-merah underline">Buka laporan PKG per guru</a>
    </div>

        {{-- Menunggu tanda tangan: kuning bila ada surat, putih bila kosong; setinggi kartu kehadiran --}}
    @php $adaSurat = $suratMenunggu->isNotEmpty(); @endphp
    <div class="flex flex-col rounded-2xl p-5 {{ $adaSurat ? 'bg-yellow-50' : 'border border-garis bg-white' }}">
        <div class="flex items-center justify-between gap-3">
            <div class="text-xs font-bold uppercase {{ $adaSurat ? 'text-yellow-900' : 'text-gray-500' }}">
                Menunggu tanda tangan Anda
            </div>
            <span class="rounded-full px-2.5 py-0.5 text-sm font-bold {{ $adaSurat ? 'bg-yellow-200 text-yellow-900' : 'bg-gray-100 text-gray-500' }}"
                  title="Jumlah surat yang menunggu tanda tangan Anda">
                {{ $suratMenunggu->count() }}
            </span>
        </div>

        @if ($adaSurat)
            @php $s = $suratMenunggu->first(); @endphp
            <div class="mt-1 text-lg font-extrabold">{{ $suratMenunggu->count() }} surat tugas luar kota / kasus khusus</div>
            <div class="text-sm text-gray-700">{{ $s->perihal }} · {{ $s->pegawai->count() }} pegawai · {{ $s->rentangTanggal() }}</div>
            @if ($suratMenunggu->count() > 1)
                <div class="mt-1 text-xs text-gray-500">Ditampilkan yang tanggal pelaksanaannya terdekat</div>
            @endif
            <div class="flex-1"></div>
            <a href="{{ route('persetujuan', ['tab' => 'surat', 'id' => $s->id]) }}"
               class="mt-3 block rounded-xl bg-merah py-2.5 text-center text-sm font-bold text-white hover:bg-merah-tua">
                Tinjau surat tugas
            </a>
        @else
            <div class="mt-1 text-lg font-extrabold">Tidak ada surat tugas</div>
            <div class="text-sm text-gray-500">Belum ada surat tugas luar kota / kasus khusus yang menunggu tanda tangan Anda.</div>
            <div class="flex-1"></div>
            <a href="{{ route('persetujuan', ['tab' => 'surat']) }}"
               class="mt-3 block rounded-xl border border-merah py-2.5 text-center text-sm font-bold text-merah hover:bg-merah-muda">
                Buka daftar persetujuan
            </a>
        @endif
    </div>
</div>

@include('dashboard._segarkan')
@endsection