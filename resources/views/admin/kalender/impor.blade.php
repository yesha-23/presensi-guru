@extends('layouts.app')

@section('konten')
@php
    $jumlahValid = $hasil ? collect($hasil['baris'])->where('galat', [])->count() : 0;
    $jumlahGagal = $hasil ? count($hasil['baris']) - $jumlahValid : 0;
    $langkahAktif = $hasil ? 3 : 1;
    $langkah = [
        ['Unduh template', 'Kolom sudah ditentukan'],
        ['Unggah file', $hasil['nama_file'] ?? 'Format .xlsx, maks. 2 MB'],
        ['Periksa & simpan', $hasil ? count($hasil['baris']).' baris terbaca' : 'Pratinjau per baris'],
    ];
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Impor Kalender Akademik</h1>
        <p class="text-sm text-gray-500">{{ $unit->label() }} · hanya baris yang valid yang disimpan</p>
    </div>
    <a href="{{ route('admin.kalender', ['unit' => $unit->id]) }}"
       class="rounded-xl border border-garis bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
        Kembali ke kalender
    </a>
</div>

<div class="mt-6 grid gap-4 rounded-2xl border border-garis bg-white p-4 sm:grid-cols-3">
    @foreach ($langkah as $i => [$judulLangkah, $keteranganLangkah])
        @php $nomor = $i + 1; @endphp
        <div class="flex items-center gap-3">
            @if ($nomor < $langkahAktif)
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-800"><i data-lucide="check" class="h-4 w-4"></i></span>
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $nomor === $langkahAktif ? 'bg-merah text-white' : 'bg-gray-100 text-gray-500' }}">{{ $nomor }}</span>
            @endif
            <div class="min-w-0 leading-tight">
                <div class="font-bold">{{ $judulLangkah }}</div>
                <div class="truncate text-xs text-gray-500">{{ $keteranganLangkah }}</div>
            </div>
        </div>
    @endforeach
</div>

@if (! $hasil)
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-garis bg-white p-5">
            <div class="font-bold">1. Unduh template</div>
            <p class="mt-1 text-sm text-gray-500">
                Satu baris per entri. Libur siswa dan libur guru-staf dicatat sebagai baris terpisah. Petunjuk lengkap ada di sheet "Petunjuk".
            </p>
            <a href="{{ route('admin.kalender.impor.template') }}"
               class="mt-4 inline-flex items-center gap-2 rounded-xl border border-garis px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
                <i data-lucide="download" class="h-4 w-4"></i> Unduh template Excel
            </a>
        </div>

        <form method="POST" action="{{ route('admin.kalender.impor.unggah') }}" enctype="multipart/form-data"
              class="rounded-2xl border border-garis bg-white p-5">
            @csrf
            <div class="font-bold">2. Unggah file</div>

            <label class="mt-3 block text-sm font-semibold">Unit kalender
                <select name="unit_id" class="mt-1 w-full rounded-xl border border-garis px-3 py-2.5 text-sm font-normal">
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" @selected($u->id === $unit->id)>{{ $u->label() }}</option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs font-normal text-gray-500">Dipakai untuk baris yang kolom unit-nya kosong.</span>
            </label>

            <input type="file" name="file" accept=".xlsx,.xls,.csv"
                   class="mt-4 block w-full text-sm file:mr-3 file:rounded-xl file:border-0 file:bg-krem file:px-4 file:py-2.5 file:font-semibold">
            @error('file') <p class="mt-2 text-sm text-merah">{{ $message }}</p> @enderror

            <button type="submit" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">
                <i data-lucide="upload" class="h-4 w-4"></i> Periksa file
            </button>
        </form>
    </div>
@else
    <div class="mt-4 flex gap-2">
        <span class="rounded-full bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-800">{{ $jumlahValid }} baris valid</span>
        @if ($jumlahGagal)
            <span class="rounded-full bg-merah-muda px-3 py-1.5 text-sm font-semibold text-merah">{{ $jumlahGagal }} baris perlu diperbaiki</span>
        @endif
    </div>

    <div class="mt-3 overflow-hidden rounded-2xl border border-garis bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[60rem] text-sm">
                <thead class="bg-krem text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-3">Baris</th>
                        <th class="px-4 py-3">Mulai</th>
                        <th class="px-4 py-3">Selesai</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Berlaku untuk</th>
                        <th class="px-4 py-3">Unit</th>
                        <th class="px-4 py-3">Hasil pemeriksaan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-garis">
                    @foreach ($hasil['baris'] as $baris)
                        @php $d = $baris['data']; @endphp
                        <tr class="{{ $baris['galat'] ? 'bg-red-50/60' : '' }}">
                            <td class="px-4 py-3 text-gray-500">{{ $baris['baris'] }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $d['tanggal_mulai'] ?? '(kosong)' }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $d['tanggal_selesai'] ?? '(kosong)' }}</td>
                            <td class="px-4 py-3">{{ $d['nama'] ?? '(kosong)' }}</td>
                            <td class="px-4 py-3">
                                {{ $d['kategori'] }}
                                @if ($d['kategori'] === \App\Models\KalenderEvent::HARI_KERJA_TAMBAHAN && $d['jam_masuk'])
                                    <span class="block text-xs text-gray-500">{{ $d['jam_masuk'] }}–{{ $d['jam_pulang'] }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $d['berlaku_untuk'] }}</td>
                            <td class="px-4 py-3">{{ $d['unit_id'] === null ? 'Semua' : ($labelUnit[$d['unit_id']] ?? 'Tidak valid') }}</td>
                            <td class="px-4 py-3">
                                @forelse ($baris['galat'] as $galat)
                                    <span class="mb-1 inline-block rounded-full bg-merah-muda px-2.5 py-1 text-xs font-semibold text-merah">{{ $galat }}</span>
                                @empty
                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">Valid</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-500">Tanggal yang sudah lewat akan dihitung ulang status presensinya, misalnya Alpa menjadi Libur.</p>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('admin.kalender.impor.batal') }}">
                @csrf
                <button class="inline-flex items-center gap-2 rounded-xl border border-garis bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
                    <i data-lucide="upload" class="h-4 w-4"></i> Unggah ulang
                </button>
            </form>
            <form method="POST" action="{{ route('admin.kalender.impor.simpan') }}">
                @csrf
                <button @disabled($jumlahValid === 0)
                        class="rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua disabled:opacity-40">
                    Simpan {{ $jumlahValid }} baris valid
                </button>
            </form>
        </div>
    </div>
@endif
@endsection