@extends('layouts.app')

@section('konten')
@php
    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-3 text-sm outline-none focus:border-navy';
    $jam = fn ($t) => str_replace(':', '.', substr($t, 0, 5));
    $jamSelesaiAwal = \Carbon\Carbon::createFromFormat('H:i', $jamPulang)->addHours(2)->format('H:i');
@endphp

<div class="flex items-center gap-3">
    <a href="{{ route('pegawai.beranda') }}" class="rounded-xl border border-garis bg-white p-2.5" aria-label="Kembali">
        <i data-lucide="chevron-left" class="h-5 w-5"></i>
    </a>
    <h1 class="text-2xl font-extrabold">Ajukan lembur</h1>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_24rem] lg:items-start">
    {{-- ================= FORM ================= --}}
    <form method="POST" action="{{ route('pegawai.lembur.store') }}" id="form-lembur"
          class="space-y-5 rounded-3xl border border-garis bg-white p-5 lg:p-6">
        @csrf

        <label class="block text-sm font-semibold">Tanggal
            <input type="date" name="tanggal" value="{{ old('tanggal', today()->toDateString()) }}"
                   min="{{ today()->subDay()->toDateString() }}" max="{{ today()->addDays(30)->toDateString() }}"
                   class="{{ $kelasInput }}">
            @error('tanggal') <span class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
        </label>

        <div class="grid grid-cols-2 gap-3">
            <label class="block text-sm font-semibold">Jam mulai
                <input type="time" name="jam_mulai" value="{{ old('jam_mulai', $jamPulang) }}" class="{{ $kelasInput }}">
            </label>
            <label class="block text-sm font-semibold">Jam selesai
                <input type="time" name="jam_selesai" value="{{ old('jam_selesai', $jamSelesaiAwal) }}" class="{{ $kelasInput }}">
            </label>
            @error('jam_mulai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror
            @error('jam_selesai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror
        </div>

        <label class="block text-sm font-semibold">Uraian pekerjaan
            <textarea name="uraian" rows="4" maxlength="1000" class="{{ $kelasInput }}"
                      placeholder="Contoh: menyusun nilai rapor kelas 5">{{ old('uraian') }}</textarea>
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

        <button type="submit" class="w-full rounded-2xl bg-merah py-4 font-bold text-white hover:bg-merah-tua">
            Kirim ke {{ $tujuan }}
        </button>
    </form>

    {{-- ================= PENGAJUAN SAYA ================= --}}
    <div class="rounded-3xl border border-garis bg-white p-5">
        <h2 class="font-bold">Pengajuan lembur saya</h2>

        <div class="mt-3 divide-y divide-garis">
            @forelse ($riwayat as $l)
                <div class="py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="leading-tight">
                            <div class="font-semibold">{{ $l->tanggal->translatedFormat('j F Y') }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $jam($l->jam_mulai) }}–{{ $jam($l->jam_selesai) }}
                                · diajukan {{ \App\Services\LemburService::formatMenit(\App\Services\LemburService::menitDiajukan($l)) }}
                            </div>
                        </div>
                        <x-lencana :status="$l->status" />
                    </div>

                    @if ($l->status === 'Disetujui')
                        <p class="mt-2 rounded-xl bg-emerald-50 p-2 text-xs text-emerald-800">
                            @if ($l->durasi_disetujui_menit === null)
                                Durasi diakui dihitung setelah presensi pulang tercatat.
                            @else
                                Durasi diakui: <b>{{ \App\Services\LemburService::formatMenit($l->durasi_disetujui_menit) }}</b>
                            @endif
                        </p>
                    @elseif ($l->status === 'Ditolak' && $l->alasan_penolakan)
                        <p class="mt-2 rounded-xl bg-red-50 p-2 text-xs text-red-800">Alasan ditolak: {{ $l->alasan_penolakan }}</p>
                    @endif

                    @if ($l->status === 'Menunggu')
                        <form method="POST" action="{{ route('pegawai.lembur.batal', $l) }}" class="mt-2"
                              onsubmit="return confirm('Batalkan pengajuan lembur ini?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs font-semibold text-merah underline">Batalkan</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="py-3 text-sm text-gray-500">Belum ada pengajuan lembur.</p>
            @endforelse
        </div>
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