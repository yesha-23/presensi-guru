@php
    $jam = fn ($t) => str_replace(':', '.', substr($t, 0, 5));
@endphp

@if ($menungguLembur->isEmpty())
    @include('persetujuan._kosong', ['pesan' => 'Tidak ada pengajuan lembur yang menunggu keputusan Anda.'])
@else
    <div class="grid gap-5 lg:grid-cols-[22rem_minmax(0,1fr)] lg:items-start">
        {{-- Daftar --}}
        <div class="space-y-3">
            @foreach ($menungguLembur as $l)
                <a href="{{ route('persetujuan', ['tab' => 'lembur', 'id' => $l->id]) }}"
                   class="block rounded-2xl border-2 bg-white p-4 {{ $lemburDipilih?->id === $l->id ? 'border-merah' : 'border-transparent hover:border-garis' }}">
                    <div class="flex justify-between text-xs">
                        <span class="font-bold uppercase text-orange-800">Lembur</span>
                        <span class="text-gray-500">Diajukan {{ $l->created_at->translatedFormat('j M') }}</span>
                    </div>
                    <div class="mt-1 font-bold">{{ $l->user->nama_lengkap }}</div>
                    <div class="text-sm text-gray-500">{{ $l->tanggal->translatedFormat('j F Y') }} · {{ $jam($l->jam_mulai) }}–{{ $jam($l->jam_selesai) }}</div>
                </a>
            @endforeach
        </div>

        {{-- Detail --}}
        @if ($lemburDipilih)
            <div class="rounded-3xl border border-garis bg-white p-5 lg:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-bold uppercase text-orange-800">Pengajuan lembur</div>
                        <h2 class="text-2xl font-extrabold">{{ $lemburDipilih->user->nama_lengkap }}</h2>
                        <div class="text-sm text-gray-500">{{ $lemburDipilih->user->kategori }} · {{ $lemburDipilih->user->unit->nama }}</div>
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
        @endif
    </div>
@endif

@include('persetujuan._diproses', ['baris' => $diprosesLembur->map(fn ($l) => [
    'nama'    => $l->user?->nama_lengkap,
    'jenis'   => 'Lembur '.$jam($l->jam_mulai).'–'.$jam($l->jam_selesai),
    'tanggal' => $l->tanggal->translatedFormat('j F Y'),
    'status'  => $l->status,
    'waktu'   => $l->diproses_at,
    'catatan' => $l->alasan_penolakan,
])])