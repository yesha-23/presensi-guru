{{-- Detail pengajuan izin/sakit: kiri data + keputusan, kanan bukti pendukung --}}
<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] xl:items-start">
    <div class="rounded-3xl border border-garis bg-white p-5 lg:p-6">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-sm font-bold uppercase text-cyan-800">Pengajuan {{ $dipilih->jenis }}{{ $dipilih->labelWaktu() }}</div>
                <h2 class="text-2xl font-extrabold">{{ $dipilih->user->nama_lengkap }}</h2>
                <div class="text-sm text-gray-500">{{ $dipilih->user->kategori }} · {{ $dipilih->user->unit->nama }}</div>
                @if ($dipilih->updated_at?->gt($dipilih->created_at->copy()->addMinute()))
                    <div class="mt-1 inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-800">
                        <i data-lucide="pencil" class="h-3 w-3"></i> Diubah pegawai {{ $dipilih->updated_at->translatedFormat('j M, H.i') }}
                    </div>
                @endif
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

        {{-- Satu form, dua tombol: Tolak & Setujui (formaction berbeda) --}}
        <form method="POST" action="{{ route('persetujuan.izin.tolak', $dipilih) }}" class="mt-5 border-t border-garis pt-5">
            @csrf
            <input type="hidden" name="versi" value="{{ $dipilih->updated_at?->timestamp }}">
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

    {{-- Bukti pendukung --}}
    <div class="rounded-3xl border border-garis bg-white p-5 lg:p-6">
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
                <iframe src="{{ route('pengajuan.bukti', $dipilih) }}" class="h-[32rem] w-full" title="Bukti pendukung"></iframe>
            @else
                <img src="{{ route('pengajuan.bukti', $dipilih) }}" alt="Bukti pendukung" class="max-h-[32rem] w-full object-contain">
            @endif
        </div>
    </div>
</div>
