<div class="mt-5 grid gap-5 lg:grid-cols-[18rem_minmax(0,1fr)] lg:items-start">
    {{-- ================= DAFTAR & TANDA TANGAN ================= --}}
    <div class="space-y-3">
        @forelse ($menungguSurat as $s)
            <a href="{{ route('persetujuan', ['tab' => 'surat', 'id' => $s->id]) }}"
               class="block rounded-2xl border-2 bg-white p-4 {{ $suratDipilih?->id === $s->id ? 'border-merah' : 'border-transparent hover:border-garis' }}">
                <div class="text-xs font-bold uppercase text-emerald-800">
                    Surat tugas{{ $s->kasus_khusus ? ' · Kasus khusus' : '' }}
                </div>
                <div class="mt-1 font-bold">{{ $s->perihal }}</div>
                <div class="text-sm text-gray-500">{{ $s->rentangTanggal() }} · {{ $s->pegawai->count() }} pegawai</div>
            </a>
        @empty
            <div class="rounded-2xl border border-dashed border-garis bg-white p-6 text-center text-sm text-gray-500">
                Tidak ada surat tugas yang menunggu tanda tangan Anda.
            </div>
        @endforelse

        {{-- Tanda tangan saya --}}
        <div class="rounded-2xl border border-garis bg-white p-4">
            <div class="text-sm font-bold">Tanda tangan saya</div>
            @if ($ttdSaya)
                <div class="mt-2 flex h-24 items-center justify-center rounded-xl bg-krem">
                    <img src="{{ route('ttd.tampil') }}?v={{ md5($ttdSaya) }}" alt="Tanda tangan" class="max-h-20">
                </div>
            @else
                <p class="mt-1 text-xs text-merah">Belum ada. Unggah gambar tanda tangan sebelum menyetujui surat tugas.</p>
            @endif

            <form method="POST" action="{{ route('ttd.simpan') }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                @csrf
                <input type="file" name="ttd" accept="image/png,image/jpeg,image/webp"
                       class="block w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-krem file:px-3 file:py-1.5 file:font-semibold">
                @error('ttd') <p class="text-xs text-merah">{{ $message }}</p> @enderror
                <button class="w-full rounded-lg border border-garis py-1.5 text-xs font-semibold hover:bg-gray-50">
                    {{ $ttdSaya ? 'Ganti tanda tangan' : 'Unggah tanda tangan' }}
                </button>
                <p class="text-xs text-gray-500">
                    PNG, JPG, atau WEBP, maksimal 2 MB. Latar putih otomatis dihilangkan; foto tanda tangan di kertas putih juga bisa.
                </p>
            </form>
        </div>
    </div>

    {{-- ================= DETAIL ================= --}}
    @if ($suratDipilih)
        <div class="min-w-0 space-y-4">
            @include('surat-tugas._tahapan', ['surat' => $suratDipilih])

            <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_19rem] 2xl:items-start">
                {{-- Kertas surat --}}
                @include('surat-tugas._pratinjau')

                {{-- Rincian & keputusan --}}
                <div class="space-y-4">
                    <div class="rounded-2xl border border-garis bg-white p-5 text-sm">
                        <h2 class="font-bold">{{ $suratDipilih->perihal }}</h2>
                        <dl class="mt-3 space-y-2">
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Waktu</dt><dd class="text-right font-semibold">{{ $suratDipilih->rentangJam() }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Pemberi tugas</dt><dd class="text-right font-semibold">{{ $suratDipilih->pemberi_tugas }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Jenis</dt><dd class="text-right font-semibold">{{ $suratDipilih->jenis_kegiatan === 'Dalam' ? 'Dalam sekolah (KDLM)' : 'Luar sekolah (KKLR)' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Sifat</dt><dd class="text-right font-semibold">{{ $suratDipilih->sifat }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-gray-500">Pegawai</dt><dd class="text-right font-semibold">{{ $suratDipilih->pegawai->count() }} orang, {{ $suratDipilih->pegawai->pluck('unit_id')->unique()->count() }} unit</dd></div>
                        </dl>
                        @if ($suratDipilih->lampiran_path)
                            <a href="{{ route('surat-tugas.lampiran', $suratDipilih) }}" target="_blank"
                               class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-navy underline">
                                <i data-lucide="paperclip" class="h-3.5 w-3.5"></i> Lihat lampiran
                            </a>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('persetujuan.surat.tolak', $suratDipilih) }}" class="rounded-2xl border border-garis bg-white p-5">
                        @csrf
                        <label class="block text-sm font-bold">Catatan <span class="font-normal text-gray-500">(wajib jika ditolak)</span>
                            <textarea name="catatan" rows="3" maxlength="500"
                                      class="mt-1 w-full rounded-xl border border-garis px-3 py-2.5 text-sm font-normal outline-none focus:border-navy">{{ old('catatan') }}</textarea>
                        </label>
                        @error('catatan') <p class="mt-1 text-xs text-merah">{{ $message }}</p> @enderror

                        <p class="mt-2 text-xs text-gray-500">
                            Tanda tangan memakai gambar TTD yang tersimpan. Surat menjadi PDF final setelah semua persetujuan lengkap.
                        </p>

                        <div class="mt-4 grid gap-2">
                            <button type="submit" formaction="{{ route('persetujuan.surat.setujui', $suratDipilih) }}"
                                    class="flex items-center justify-center gap-2 rounded-xl bg-merah py-3 font-semibold text-white hover:bg-merah-tua">
                                <i data-lucide="pen-line" class="h-4 w-4"></i> Setujui & tanda tangani
                            </button>
                            <button type="submit" formaction="{{ route('persetujuan.surat.tolak', $suratDipilih) }}"
                                    class="rounded-xl border border-merah bg-white py-2.5 font-semibold text-merah hover:bg-merah-muda">
                                Tolak
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>