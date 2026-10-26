@extends('layouts.app')

@section('konten')
@php
    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-3 text-sm outline-none focus:border-navy';
    $jam = fn ($t) => str_replace(':', '.', substr($t, 0, 5));
    $edit = $edit ?? null;
    $keRiwayat = route('pegawai.pengajuan');
    $jamSelesaiAwal = \Carbon\Carbon::createFromFormat('H:i', $jamPulang)->addHours(2)->format('H:i');
@endphp

<div class="flex items-center gap-3">
    <a href="{{ $keRiwayat }}" class="rounded-xl border border-garis bg-white p-2.5" aria-label="Kembali">
        <i data-lucide="chevron-left" class="h-5 w-5"></i>
    </a>
    <h1 class="text-2xl font-extrabold">{{ $edit ? 'Edit pengajuan lembur' : 'Ajukan lembur' }}</h1>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_24rem] lg:items-start">
    {{-- ================= FORM (tambah & edit) ================= --}}
    <form method="POST" action="{{ $edit ? route('pegawai.lembur.update', $edit) : route('pegawai.lembur.store') }}" id="form-lembur"
          class="space-y-5 rounded-3xl border bg-white p-5 lg:p-6 {{ $edit ? 'border-navy' : 'border-garis' }}">
        @csrf
        @if ($edit) @method('PUT') @endif

        @if ($edit)
            <div class="flex items-start gap-2 rounded-2xl bg-blue-50 p-3 text-sm text-blue-900">
                <i data-lucide="pencil" class="mt-0.5 h-4 w-4 shrink-0"></i>
                <span>Anda sedang mengubah pengajuan lembur <b>{{ $edit->tanggal->translatedFormat('j F Y') }}</b>. Perubahan hanya bisa dilakukan selama status masih Menunggu.</span>
            </div>
        @endif

        <label class="block text-sm font-semibold">Tanggal
            <input type="date" name="tanggal" value="{{ old('tanggal', $edit?->tanggal?->toDateString() ?? today()->toDateString()) }}"
                   min="{{ today()->subDay()->toDateString() }}" max="{{ today()->addDays(30)->toDateString() }}"
                   class="{{ $kelasInput }}">
            @error('tanggal') <span class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
        </label>

        <div class="grid grid-cols-2 gap-3">
            <label class="block text-sm font-semibold">Jam mulai
                <input type="time" name="jam_mulai" value="{{ old('jam_mulai', $edit ? substr($edit->jam_mulai, 0, 5) : $jamPulang) }}" class="{{ $kelasInput }}">
            </label>
            <label class="block text-sm font-semibold">Jam selesai
                <input type="time" name="jam_selesai" value="{{ old('jam_selesai', $edit ? substr($edit->jam_selesai, 0, 5) : $jamSelesaiAwal) }}" class="{{ $kelasInput }}">
            </label>
            @error('jam_mulai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror
            @error('jam_selesai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror
        </div>

        <label class="block text-sm font-semibold">Uraian pekerjaan
            <textarea name="uraian" rows="4" maxlength="1000" class="{{ $kelasInput }}"
                      placeholder="Contoh: menyusun nilai rapor kelas 5">{{ old('uraian', $edit?->uraian) }}</textarea>
            @error('uraian') <span class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
        </label>

        <div class="flex items-center justify-between rounded-2xl bg-krem px-4 py-3">
            <span class="font-semibold">Durasi diajukan</span>
            <span id="durasi" class="text-xl font-extrabold tabular-nums">--:--:--</span>
        </div>

        <div class="rounded-2xl bg-yellow-50 p-4 text-sm text-yellow-900">
            Dihitung hanya jika disetujui, dan dibatasi waktu presensi pulang.
            Jam mulai tidak boleh lebih awal dari jadwal pulang ({{ str_replace(':', '.', $jamPulang) }} hari ini).
        </div>

        @if ($edit)
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ $keRiwayat }}" class="rounded-2xl border border-garis bg-white py-4 text-center font-bold hover:bg-gray-50">Batal edit</a>
                <button type="submit" class="rounded-2xl bg-merah py-4 font-bold text-white hover:bg-merah-tua">Simpan perubahan</button>
            </div>
        @else
            <button type="submit" class="w-full rounded-2xl bg-merah py-4 font-bold text-white hover:bg-merah-tua">
                Kirim ke {{ $tujuan }}
            </button>
        @endif
    </form>

    {{-- ================= LEMBUR TERAKHIR (ringkas, tanpa aksi) ================= --}}
    <div class="rounded-3xl border border-garis bg-white p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-bold">Lembur terakhir</h2>
            <a href="{{ $keRiwayat }}" class="text-xs font-semibold text-navy underline">Lihat semua</a>
        </div>

        <div class="mt-3 divide-y divide-garis">
            @forelse ($riwayat as $l)
                <div class="flex items-start justify-between gap-3 py-3">
                    <div class="leading-tight">
                        <div class="font-semibold">{{ $l->tanggal->translatedFormat('j F Y') }}</div>
                        <div class="text-xs text-gray-500">{{ $jam($l->jam_mulai) }}–{{ $jam($l->jam_selesai) }}</div>
                    </div>
                    <x-lencana :status="$l->status" />
                </div>
            @empty
                <p class="py-3 text-sm text-gray-500">Belum ada pengajuan lembur.</p>
            @endforelse
        </div>

        <p class="mt-2 rounded-xl bg-krem p-3 text-xs text-gray-600">
            Pengajuan berstatus <b>Menunggu</b> masih dapat diedit atau dibatalkan dari menu
            <a href="{{ $keRiwayat }}" class="font-semibold text-navy underline">Pengajuan</a>.
        </p>
    </div>
</div>
@endsection

@push('script')
<script>
    const formLembur = document.getElementById('form-lembur');
    const teksDurasi = document.getElementById('durasi');

    const keMenit = (nilai) => {
        const [jam, menit] = (nilai || '').split(':').map(Number);
        return Number.isFinite(jam) && Number.isFinite(menit) ? jam * 60 + menit : null;
    };

    function hitungDurasi() {
        const mulai = keMenit(formLembur.elements['jam_mulai'].value);
        const selesai = keMenit(formLembur.elements['jam_selesai'].value);
        const selisih = mulai !== null && selesai !== null ? selesai - mulai : 0;

        teksDurasi.textContent = selisih > 0
            ? `${String(Math.floor(selisih / 60)).padStart(2, '0')}:${String(selisih % 60).padStart(2, '0')}:00`
            : '--:--:--';
    }

    ['jam_mulai', 'jam_selesai'].forEach((nama) => formLembur.elements[nama].addEventListener('input', hitungDurasi));
    hitungDurasi();
</script>
@endpush