@extends('layouts.app')

@section('konten')
@php
    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-3 text-sm outline-none focus:border-navy';
    $rentang = fn ($p) => $p->tanggal_mulai->equalTo($p->tanggal_selesai)
        ? $p->tanggal_mulai->translatedFormat('j F Y')
        : $p->tanggal_mulai->translatedFormat('j M').' – '.$p->tanggal_selesai->translatedFormat('j M Y');
    $edit = $edit ?? null;
    $keRiwayat = route('pegawai.pengajuan');
@endphp

<div class="flex items-center gap-3">
    <a href="{{ $keRiwayat }}" class="rounded-xl border border-garis bg-white p-2.5" aria-label="Kembali">
        <i data-lucide="chevron-left" class="h-5 w-5"></i>
    </a>
    <h1 class="text-2xl font-extrabold">{{ $edit ? 'Edit pengajuan izin / sakit' : 'Ajukan izin / sakit' }}</h1>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_24rem] lg:items-start">
    {{-- ================= FORM (tambah & edit) ================= --}}
    <form method="POST" action="{{ $edit ? route('pegawai.izin.update', $edit) : route('pegawai.izin.store') }}"
          enctype="multipart/form-data" id="form-izin"
          data-besok="{{ today()->addDay()->toDateString() }}"
          data-hari-ini="{{ today()->toDateString() }}"
          data-kemarin="{{ today()->subDay()->toDateString() }}"
          class="space-y-5 rounded-3xl border bg-white p-5 lg:p-6 {{ $edit ? 'border-navy' : 'border-garis' }}">
        @csrf
        @if ($edit) @method('PUT') @endif

        @if ($edit)
            <div class="flex items-start gap-2 rounded-2xl bg-blue-50 p-3 text-sm text-blue-900">
                <i data-lucide="pencil" class="mt-0.5 h-4 w-4 shrink-0"></i>
                <span>Anda sedang mengubah pengajuan <b>{{ $edit->jenis }} · {{ $rentang($edit) }}</b>. Perubahan hanya bisa dilakukan selama status masih Menunggu.</span>
            </div>
        @endif

        <div>
            <span class="block text-sm font-semibold">Jenis pengajuan</span>
            <div class="mt-2 grid grid-cols-2 rounded-2xl bg-krem p-1">
                @foreach (['Izin', 'Sakit'] as $jenis)
                    <label class="cursor-pointer">
                        <input type="radio" name="jenis" value="{{ $jenis }}" class="peer sr-only"
                               @checked(old('jenis', $edit?->jenis ?? 'Izin') === $jenis)>
                        <span class="block rounded-xl py-2.5 text-center font-semibold text-gray-500 peer-checked:bg-white peer-checked:text-merah peer-checked:shadow-sm">
                            {{ $jenis }}
                        </span>
                    </label>
                @endforeach
            </div>
            @error('jenis') <p class="mt-1 text-xs text-merah">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <label class="block text-sm font-semibold">Tanggal mulai
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $edit?->tanggal_mulai?->toDateString()) }}" class="{{ $kelasInput }}">
            </label>
            <label class="block text-sm font-semibold">Tanggal selesai
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $edit?->tanggal_selesai?->toDateString()) }}" class="{{ $kelasInput }}">
            </label>
            @error('tanggal_mulai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror
            @error('tanggal_selesai') <p class="col-span-2 -mt-1 text-xs text-merah">{{ $message }}</p> @enderror
        </div>

        <label class="block text-sm font-semibold">Alasan
            <textarea name="alasan" rows="4" maxlength="1000" class="{{ $kelasInput }}"
                      placeholder="Tuliskan alasan secara singkat">{{ old('alasan', $edit?->alasan) }}</textarea>
            @error('alasan') <span class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
        </label>

        <div>
            <span class="block text-sm font-semibold">
                Bukti pendukung
                @if ($edit) <span class="font-normal text-gray-500">(opsional, kosongkan jika tidak diganti)</span> @endif
            </span>
            @if ($edit?->bukti_path)
                <p class="mt-1 text-xs text-gray-600">
                    Bukti saat ini: <a href="{{ route('pengajuan.bukti', $edit) }}" target="_blank" class="font-semibold text-navy underline">lihat bukti</a>
                </p>
            @endif
            <label class="mt-1 flex cursor-pointer flex-col items-center gap-1 rounded-2xl border-2 border-dashed border-garis bg-krem/60 px-4 py-6 text-center hover:bg-krem">
                <i data-lucide="upload" class="h-6 w-6 text-merah"></i>
                <span class="font-semibold">{{ $edit ? 'Ganti dengan foto atau PDF baru' : 'Unggah foto atau PDF' }}</span>
                <span id="info-bukti" class="text-xs text-gray-500">Maksimal 2 MB</span>
                <input type="file" name="bukti" id="input-bukti" accept="image/jpeg,image/png,application/pdf" class="sr-only">
            </label>
            @error('bukti') <p class="mt-1 text-xs text-merah">{{ $message }}</p> @enderror
        </div>

        <div class="rounded-2xl bg-yellow-50 p-4 text-sm text-yellow-900">
            Izin paling lambat H-1. Sakit di hari yang sama atau susulan paling lambat H+1.
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

    {{-- ================= PENGAJUAN TERAKHIR (ringkas, tanpa aksi) ================= --}}
    <div class="rounded-3xl border border-garis bg-white p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-bold">Pengajuan terakhir</h2>
            <a href="{{ $keRiwayat }}" class="text-xs font-semibold text-navy underline">Lihat semua</a>
        </div>

        <div class="mt-3 divide-y divide-garis">
            @forelse ($riwayat as $p)
                <div class="flex items-start justify-between gap-3 py-3">
                    <div class="leading-tight">
                        <div class="font-semibold">{{ $p->jenis }} · {{ $rentang($p) }}</div>
                        <div class="text-xs text-gray-500">Diajukan {{ $p->created_at->translatedFormat('j M Y, H.i') }}</div>
                    </div>
                    <x-lencana :status="$p->status" />
                </div>
            @empty
                <p class="py-3 text-sm text-gray-500">Belum ada pengajuan.</p>
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
    const formIzin = document.getElementById('form-izin');
    const inputBukti = document.getElementById('input-bukti');
    const infoBukti = document.getElementById('info-bukti');
    const tglMulai = formIzin.elements['tanggal_mulai'];
    const tglSelesai = formIzin.elements['tanggal_selesai'];

    // Batas tanggal di kalender mengikuti aturan H-1 (izin) dan H+1 (sakit)
    function aturBatasTanggal() {
        const sakit = formIzin.querySelector('input[name="jenis"]:checked')?.value === 'Sakit';
        tglMulai.min = sakit ? formIzin.dataset.kemarin : formIzin.dataset.besok;
        tglMulai.max = sakit ? formIzin.dataset.hariIni : '';
        if (!tglMulai.value || tglMulai.value < tglMulai.min || (tglMulai.max && tglMulai.value > tglMulai.max)) {
            tglMulai.value = sakit ? formIzin.dataset.hariIni : formIzin.dataset.besok;
        }
        tglSelesai.min = tglMulai.value;
        if (!tglSelesai.value || tglSelesai.value < tglMulai.value) tglSelesai.value = tglMulai.value;
    }

    formIzin.querySelectorAll('input[name="jenis"]').forEach((r) => r.addEventListener('change', aturBatasTanggal));
    tglMulai.addEventListener('change', aturBatasTanggal);
    aturBatasTanggal();

    // Foto kamera HP sering > 2 MB: perkecil otomatis sebelum diunggah
    async function perkecil(file) {
        if (!file.type.startsWith('image/') || file.size <= 1.5 * 1024 * 1024) return file;
        try {
            const gambar = await createImageBitmap(file);
            const skala = Math.min(1, 1600 / Math.max(gambar.width, gambar.height));
            const kanvas = document.createElement('canvas');
            kanvas.width = Math.round(gambar.width * skala);
            kanvas.height = Math.round(gambar.height * skala);
            kanvas.getContext('2d').drawImage(gambar, 0, 0, kanvas.width, kanvas.height);
            const blob = await new Promise((selesai) => kanvas.toBlob(selesai, 'image/jpeg', 0.8));
            return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
        } catch {
            return file;
        }
    }

    inputBukti.addEventListener('change', async () => {
        const asli = inputBukti.files[0];
        if (!asli) return;

        infoBukti.textContent = 'Memproses…';
        const hasil = await perkecil(asli);

        if (hasil !== asli) {
            const dt = new DataTransfer();
            dt.items.add(hasil);
            inputBukti.files = dt.files;
        }

        const mb = (hasil.size / 1024 / 1024).toFixed(2);
        infoBukti.textContent = `${hasil.name} · ${mb} MB`;
        infoBukti.className = `text-xs ${hasil.size > 2 * 1024 * 1024 ? 'font-semibold text-merah' : 'text-gray-600'}`;
        if (hasil.size > 2 * 1024 * 1024) infoBukti.textContent += ' (lebih dari 2 MB)';
    });
</script>
@endpush