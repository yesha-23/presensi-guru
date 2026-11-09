{{-- Detail koreksi presensi milik administrator (daftar ada di index) --}}
@php
    $label = ['status_harian' => 'Status harian', 'jam_datang' => 'Jam datang', 'jam_pulang' => 'Jam pulang'];
@endphp

<div class="max-w-4xl rounded-3xl border border-garis bg-white p-5 lg:p-6">
    <div class="text-sm font-bold uppercase text-navy">Koreksi presensi milik administrator</div>
    <h2 class="text-2xl font-extrabold">{{ $koreksiDipilih->target->user->nama_lengkap }}</h2>
    <p class="text-sm text-gray-500">
        {{ $koreksiDipilih->target->tanggal->translatedFormat('l, j F Y') }}
        · diajukan {{ $koreksiDipilih->pengubah?->nama_lengkap }}, {{ $koreksiDipilih->created_at->translatedFormat('j M Y H.i') }}
    </p>

    <div class="mt-5 overflow-hidden rounded-2xl border border-garis">
        <table class="w-full text-sm">
            <thead class="bg-krem text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2.5">Data</th>
                    <th class="px-4 py-2.5">Sebelum</th>
                    <th class="px-4 py-2.5">Sesudah</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @foreach ($label as $kunci => $teks)
                    @php
                        $lama = $koreksiDipilih->nilai_lama[$kunci] ?? null;
                        $baru = $koreksiDipilih->nilai_baru[$kunci] ?? null;
                    @endphp
                    <tr class="{{ $lama !== $baru ? 'bg-yellow-50' : '' }}">
                        <td class="px-4 py-2.5 text-gray-500">{{ $teks }}</td>
                        <td class="px-4 py-2.5 font-semibold">{{ $lama ?? '—' }}</td>
                        <td class="px-4 py-2.5 font-semibold">{{ $baru ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-5">
        <div class="text-sm font-bold">Alasan koreksi</div>
        <p class="mt-1 whitespace-pre-line text-gray-700">{{ $koreksiDipilih->alasan }}</p>
    </div>

    <form method="POST" action="{{ route('persetujuan.koreksi.tolak', $koreksiDipilih) }}" class="mt-5">
        @csrf
        <label class="block text-sm font-bold">Alasan penolakan <span class="font-normal text-gray-500">(wajib jika ditolak)</span>
            <textarea name="catatan" rows="3" maxlength="500"
                      class="mt-1 w-full rounded-xl border border-garis px-3 py-2.5 text-sm font-normal outline-none focus:border-navy">{{ old('catatan') }}</textarea>
        </label>
        @error('catatan') <p class="mt-1 text-xs text-merah">{{ $message }}</p> @enderror

        <div class="mt-4 flex justify-end gap-2">
            <button type="submit" formaction="{{ route('persetujuan.koreksi.tolak', $koreksiDipilih) }}"
                    class="rounded-xl border border-merah bg-white px-6 py-2.5 font-semibold text-merah hover:bg-merah-muda">
                Tolak
            </button>
            <button type="submit" formaction="{{ route('persetujuan.koreksi.setujui', $koreksiDipilih) }}"
                    class="rounded-xl bg-merah px-6 py-2.5 font-semibold text-white hover:bg-merah-tua">
                Setujui
            </button>
        </div>
    </form>
</div>
