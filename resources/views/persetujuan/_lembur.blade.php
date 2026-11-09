{{-- Detail pengajuan lembur (daftar ada di index) --}}
@php
    $jam = fn ($t) => str_replace(':', '.', substr($t, 0, 5));
@endphp

<div class="max-w-4xl rounded-3xl border border-garis bg-white p-5 lg:p-6">
    <div class="flex items-start justify-between gap-3">
        <div>
            <div class="text-sm font-bold uppercase text-orange-800">Pengajuan lembur</div>
            <h2 class="text-2xl font-extrabold">{{ $lemburDipilih->user->nama_lengkap }}</h2>
            <div class="text-sm text-gray-500">{{ $lemburDipilih->user->kategori }} · {{ $lemburDipilih->user->unit->nama }}</div>
            @if ($lemburDipilih->updated_at?->gt($lemburDipilih->created_at->copy()->addMinute()))
                <div class="mt-1 inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-800">
                    <i data-lucide="pencil" class="h-3 w-3"></i> Diubah pegawai {{ $lemburDipilih->updated_at->translatedFormat('j M, H.i') }}
                </div>
            @endif
        </div>
        <x-lencana :status="$lemburDipilih->status" />
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-krem p-3">
            <div class="text-xs text-gray-500">Tanggal</div>
            <div class="font-bold">{{ $lemburDipilih->tanggal->translatedFormat('l, j M Y') }}</div>
        </div>
        <div class="rounded-2xl bg-krem p-3">
            <div class="text-xs text-gray-500">Jam diajukan</div>
            <div class="font-bold">{{ $jam($lemburDipilih->jam_mulai) }}–{{ $jam($lemburDipilih->jam_selesai) }}</div>
            <div class="text-xs text-gray-500">Durasi {{ $infoLembur['diajukan'] }}</div>
        </div>
        <div class="rounded-2xl bg-krem p-3">
            <div class="text-xs text-gray-500">Jadwal pulang</div>
            <div class="font-bold">{{ $infoLembur['jadwalPulang'] }}</div>
        </div>
        <div class="rounded-2xl bg-krem p-3">
            <div class="text-xs text-gray-500">Presensi pulang aktual</div>
            <div class="font-bold">{{ $infoLembur['pulangAktual'] ?? 'Belum ada' }}</div>
            @if ($infoLembur['perkiraan'] !== null)
                <div class="text-xs text-emerald-700">Diakui {{ \App\Services\LemburService::formatMenit($infoLembur['perkiraan']) }}</div>
            @endif
        </div>
    </div>

    <div class="mt-5">
        <div class="text-sm font-bold">Uraian pekerjaan</div>
        <p class="mt-1 whitespace-pre-line text-gray-700">{{ $lemburDipilih->uraian }}</p>
    </div>

    <p class="mt-4 rounded-2xl bg-yellow-50 p-3 text-xs text-yellow-900">
        Durasi yang diakui dibatasi jam presensi pulang aktual. Jika presensi pulang belum ada, durasi dihitung otomatis saat presensi pulang tercatat.
    </p>

    <form method="POST" action="{{ route('persetujuan.lembur.tolak', $lemburDipilih) }}" class="mt-5">
        @csrf
        <input type="hidden" name="versi" value="{{ $lemburDipilih->updated_at?->timestamp }}">
        <label class="block text-sm font-bold">Alasan penolakan <span class="font-normal text-gray-500">(wajib jika ditolak)</span>
            <textarea name="alasan_penolakan" rows="3" maxlength="500"
                      class="mt-1 w-full rounded-xl border border-garis px-3 py-2.5 text-sm font-normal outline-none focus:border-navy">{{ old('alasan_penolakan') }}</textarea>
        </label>
        @error('alasan_penolakan') <p class="mt-1 text-xs text-merah">{{ $message }}</p> @enderror

        <div class="mt-4 flex justify-end gap-2">
            <button type="submit" formaction="{{ route('persetujuan.lembur.tolak', $lemburDipilih) }}"
                    class="rounded-xl border border-merah bg-white px-6 py-2.5 font-semibold text-merah hover:bg-merah-muda">
                Tolak
            </button>
            <button type="submit" formaction="{{ route('persetujuan.lembur.setujui', $lemburDipilih) }}"
                    class="rounded-xl bg-merah px-6 py-2.5 font-semibold text-white hover:bg-merah-tua">
                Setujui
            </button>
        </div>
    </form>
</div>
