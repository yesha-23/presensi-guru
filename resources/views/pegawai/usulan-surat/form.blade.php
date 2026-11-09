@extends('layouts.app')

@section('konten')
@php
    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-3 text-sm font-normal outline-none focus:border-navy';
    $kelasLabel = 'block text-sm font-semibold';
    $hariIni = today()->toDateString();
    $kembali = route('pegawai.usulan-surat');

    // Nilai awal form (tambah / edit / setelah galat validasi)
    $nilai = fn (string $kolom, $bawaan = null) => old($kolom, $edit ? $edit->{$kolom} : $bawaan);
    $tglMulai = old('tanggal_mulai', $edit?->tanggal_mulai?->toDateString() ?? $hariIni);
    $tglSelesai = old('tanggal_selesai', $edit?->tanggal_selesai?->toDateString() ?? $hariIni);
    $jamMulai = old('jam_mulai', $edit?->jam_mulai ? substr($edit->jam_mulai, 0, 5) : '');
    $jamSelesai = old('jam_selesai', $edit?->jam_selesai ? substr($edit->jam_selesai, 0, 5) : '');
@endphp

<div class="flex items-center gap-3">
    <a href="{{ $kembali }}" class="rounded-xl border border-garis bg-white p-2.5" aria-label="Kembali">
        <i data-lucide="chevron-left" class="h-5 w-5"></i>
    </a>
    <div>
        <h1 class="text-2xl font-extrabold">{{ $edit ? 'Ubah usulan surat tugas' : 'Usulkan surat tugas' }}</h1>
        <p class="text-sm text-gray-500">Untuk undangan atau tugas yang Anda terima langsung (lomba, rapat dinas, pelatihan, dll.)</p>
    </div>
</div>

<div class="mt-6 max-w-3xl">
    {{-- ================= FORM (tambah & edit) ================= --}}
    <form method="POST" enctype="multipart/form-data"
          action="{{ $edit ? route('pegawai.usulan-surat.update', $edit) : route('pegawai.usulan-surat.store') }}"
          class="space-y-5 rounded-3xl border bg-white p-5 lg:p-6 {{ $edit ? 'border-navy' : 'border-garis' }}">
        @csrf
        @if ($edit) @method('PUT') @endif

        @if ($edit)
            <div class="flex items-start gap-2 rounded-2xl bg-blue-50 p-3 text-sm text-blue-900">
                <i data-lucide="pencil" class="mt-0.5 h-4 w-4 shrink-0"></i>
                <span>Anda sedang mengubah usulan <b>{{ $edit->perihal }}</b>. Perubahan hanya bisa dilakukan selama status masih Diajukan.</span>
            </div>
        @endif

        <label class="{{ $kelasLabel }}">Perihal
            <input name="perihal" value="{{ $nilai('perihal') }}" maxlength="200" placeholder="Contoh: Pendampingan lomba FLS2N tingkat kabupaten" class="{{ $kelasInput }}">
            @error('perihal') <span class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
        </label>

        <label class="{{ $kelasLabel }}">Pemberi tugas / pengundang
            <select name="pemberi_tugas" class="{{ $kelasInput }}">
                @foreach (['Dinas', 'Yayasan', 'Lainnya'] as $p)
                    <option @selected($nilai('pemberi_tugas', 'Dinas') === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </label>

        <div class="grid grid-cols-2 gap-3">
            <label class="{{ $kelasLabel }}">Tanggal mulai
                <input type="date" name="tanggal_mulai" value="{{ $tglMulai }}" min="{{ $hariIni }}" class="{{ $kelasInput }}">
            </label>
            <label class="{{ $kelasLabel }}">Tanggal selesai
                <input type="date" name="tanggal_selesai" value="{{ $tglSelesai }}" min="{{ $hariIni }}" class="{{ $kelasInput }}">
            </label>
            @error('tanggal_mulai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror
            @error('tanggal_selesai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror

            <label class="{{ $kelasLabel }}">Jam mulai <span class="font-normal text-gray-500">(opsional)</span>
                <input type="time" name="jam_mulai" value="{{ $jamMulai }}" class="{{ $kelasInput }}">
            </label>
            <label class="{{ $kelasLabel }}">Jam selesai <span class="font-normal text-gray-500">(opsional)</span>
                <input type="time" name="jam_selesai" value="{{ $jamSelesai }}" class="{{ $kelasInput }}">
            </label>
            @error('jam_mulai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror
            @error('jam_selesai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror
        </div>

        <label class="{{ $kelasLabel }}">Lokasi
            <input name="lokasi" value="{{ $nilai('lokasi') }}" maxlength="150" placeholder="Contoh: Gedung Dinas Pendidikan Sleman" class="{{ $kelasInput }}">
            @error('lokasi') <span class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
        </label>

        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <span class="{{ $kelasLabel }}">Jenis kegiatan</span>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    @foreach (['Luar' => 'Luar sekolah', 'Dalam' => 'Dalam sekolah'] as $isi => $label)
                        <label class="flex items-center gap-2 rounded-xl border border-garis px-3 py-2.5 text-sm">
                            <input type="radio" name="jenis_kegiatan" value="{{ $isi }}" class="accent-merah" @checked($nilai('jenis_kegiatan', 'Luar') === $isi)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div>
                <span class="{{ $kelasLabel }}">Sifat</span>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    @foreach (['Terencana', 'Mendadak'] as $sifat)
                        <label class="flex items-center gap-2 rounded-xl border border-garis px-3 py-2.5 text-sm">
                            <input type="radio" name="sifat" value="{{ $sifat }}" class="accent-merah" @checked($nilai('sifat', 'Terencana') === $sifat)>
                            {{ $sifat }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <label class="{{ $kelasLabel }}">Keterangan <span class="font-normal text-gray-500">(opsional · satu baris per poin)</span>
            <textarea name="keterangan" rows="3" maxlength="500" class="{{ $kelasInput }}"
                      placeholder="Contoh:&#10;Bersama Pak Daniel sebagai pendamping&#10;Berangkat dari sekolah pukul 11.00">{{ $nilai('keterangan') }}</textarea>
            @error('keterangan') <span class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
        </label>

        <div>
            <span class="{{ $kelasLabel }}">
                Undangan / surat dari pemberi tugas
                @if ($edit) <span class="font-normal text-gray-500">(kosongkan jika tidak diganti)</span> @endif
            </span>
            @if ($edit?->lampiran_path)
                <p class="mt-1 text-xs text-gray-600">
                    Lampiran saat ini: <a href="{{ route('surat-tugas.lampiran', $edit) }}" target="_blank" class="font-semibold text-navy underline">lihat lampiran</a>
                </p>
            @endif
            <label class="mt-2 flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-garis bg-krem/60 px-4 py-3 text-sm font-semibold hover:bg-krem">
                <i data-lucide="upload" class="h-4 w-4"></i> Pilih file (PDF/JPG/PNG)
                <input type="file" name="lampiran" accept="application/pdf,image/jpeg,image/png" class="sr-only"
                       onchange="document.getElementById('info-lampiran').textContent = this.files[0]?.name ?? ''">
            </label>
            <p id="info-lampiran" class="mt-1 text-center text-xs text-gray-500">Maksimal 2 MB</p>
            @error('lampiran') <span class="mt-1 block text-center text-xs text-merah">{{ $message }}</span> @enderror
        </div>

        <div class="rounded-2xl bg-yellow-50 p-4 text-sm text-yellow-900">
            Usulan dikirim ke administrator untuk diperiksa dan dilengkapi (misalnya rekan yang ikut bertugas),
            lalu ditandatangani kepala sekolah. Tanggal tugas tercatat sebagai Tugas Khusus setelah surat disetujui.
        </div>

        @if ($edit)
            <div class="grid grid-cols-2 gap-3">
                <a href="{{ $kembali }}" class="rounded-2xl border border-garis bg-white py-4 text-center font-bold hover:bg-gray-50">Batal edit</a>
                <button type="submit" class="rounded-2xl bg-merah py-4 font-bold text-white hover:bg-merah-tua">Simpan perubahan</button>
            </div>
        @else
            <button type="submit" class="w-full rounded-2xl bg-merah py-4 font-bold text-white hover:bg-merah-tua">Kirim usulan ke administrator</button>
        @endif
    </form>

</div>
@endsection
