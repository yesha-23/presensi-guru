@extends('layouts.app')

@section('konten')
<div id="pendaftaran-wajah"
     data-url-simpan="{{ route('admin.pegawai.wajah.simpan', $pegawai) }}"
     data-url-model="{{ asset('models') }}">

    {{-- Judul --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold lg:text-3xl">Pendaftaran Data Wajah</h1>
            <p class="text-sm text-gray-500">
                {{ $pegawai->nama_lengkap }} · {{ $pegawai->kategori }} · {{ $pegawai->unit->label() }}
                @if ($pegawai->face_descriptors_count >= 3)
                    · <span class="font-semibold text-blue-800">ambil ulang (data lama akan diganti)</span>
                @endif
            </p>
        </div>
        <a href="{{ route('admin.pegawai') }}"
           class="rounded-xl border border-garis bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
            Kembali ke data pegawai
        </a>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_22rem]">
        {{-- ============ Kamera & foto ============ --}}
        <div>
            <div class="relative aspect-[4/3] overflow-hidden rounded-3xl bg-gray-900">
                <video playsinline muted class="h-full w-full -scale-x-100 object-cover"></video>

                <span id="label-foto" class="absolute left-4 top-4 rounded-full bg-white px-3 py-1.5 text-sm font-semibold">
                    Foto 1: hadapkan wajah ke depan
                </span>

                <div id="oval"
                     class="pointer-events-none absolute left-1/2 top-1/2 aspect-[3/4] h-[72%] -translate-x-1/2 -translate-y-1/2 rounded-[50%] border-4 border-dashed border-kuning"></div>

                <span id="status-kamera"
                      class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-3 py-1.5 text-xs font-semibold text-white"></span>

                <div id="tirai-kamera" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-gray-900 p-6 text-center text-white/80">
                    <i data-lucide="camera-off" class="h-10 w-10"></i>
                    <p class="max-w-xs text-sm">Centang persetujuan data biometrik di samping untuk menyalakan kamera.</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-3">
                @foreach (['Wajah depan', 'Sedikit ke kiri', 'Sedikit ke kanan'] as $i => $namaFoto)
                    <div data-slot class="rounded-2xl border border-garis bg-white p-3">
                        <div class="flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl bg-krem text-xs text-gray-500">
                            <img alt="Foto {{ $i + 1 }}" class="hidden h-full w-full -scale-x-100 object-cover">
                            <span data-kosong>Belum diambil</span>
                        </div>
                        <div class="mt-2 text-sm font-bold">{{ $i + 1 }}. {{ $namaFoto }}</div>
                        <div data-status class="text-xs font-semibold text-gray-400">Belum</div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" id="tombol-ulangi" disabled
                        class="rounded-xl border border-garis bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50 disabled:opacity-40">
                    Ulangi foto
                </button>
                <button type="button" id="tombol-ambil" disabled
                        class="rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua disabled:opacity-40">
                    Ambil foto 1
                </button>
            </div>
        </div>

        {{-- ============ Persetujuan & panduan ============ --}}
        <div class="space-y-4">
            <div class="rounded-2xl border border-garis bg-white p-5">
                <h2 class="font-bold">Persetujuan data biometrik</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Data wajah termasuk data pribadi spesifik menurut UU No. 27 Tahun 2022.
                    Data hanya dipakai untuk verifikasi presensi dan hanya dapat diakses administrator.
                </p>
                <label class="mt-3 flex items-start gap-2 text-sm font-semibold">
                    <input type="checkbox" id="cek-setuju" class="mt-0.5 h-4 w-4 accent-merah">
                    Pegawai telah membaca dan menyetujui pemrosesan data wajahnya
                </label>
                @if ($pegawai->persetujuan_wajah_at)
                    <p class="mt-2 text-xs text-gray-500">
                        Persetujuan terakhir dicatat: {{ $pegawai->persetujuan_wajah_at->translatedFormat('j F Y, H.i') }}
                    </p>
                @endif
            </div>

            <div class="rounded-2xl border border-garis bg-white p-5">
                <h2 class="font-bold">Agar wajah mudah dikenali</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li>Pencahayaan cukup dari depan</li>
                    <li>Tanpa masker dan topi</li>
                    <li>Ekspresi netral, mata terbuka</li>
                    <li>Hanya satu wajah di depan kamera</li>
                </ul>
            </div>

            <p id="pesan-wajah" class="hidden rounded-xl px-4 py-3 text-sm"></p>

            <button type="button" id="tombol-simpan" disabled
                    class="w-full rounded-xl bg-merah py-3 text-sm font-bold text-white hover:bg-merah-tua disabled:opacity-40">
                Simpan data wajah (0 dari 3 foto)
            </button>
        </div>
    </div>
</div>
@endsection

@push('script')
    @vite('resources/js/wajah.js')
@endpush