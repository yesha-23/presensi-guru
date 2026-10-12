@extends('layouts.app')

@section('konten')
@php
    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-navy';
    $kelasLabel = 'block text-sm font-semibold';
    $hariIni = today()->toDateString();
    $pegawaiLama = array_map('intval', old('pegawai', []));
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Surat Tugas</h1>
        <p class="text-sm text-gray-500">Penerbitan surat tugas dan tugas khusus pegawai</p>
    </div>
    <button type="button" data-tambah
            class="inline-flex items-center gap-2 rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">
        <i data-lucide="plus" class="h-4 w-4"></i> Terbitkan surat tugas
    </button>
</div>

{{-- ================= FILTER ================= --}}
<form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-garis bg-white p-4 md:grid-cols-[1fr_12rem_10rem_auto] md:items-end">
    <label class="{{ $kelasLabel }}">Cari
        <input name="cari" value="{{ $filter['cari'] ?? '' }}" placeholder="Perihal atau nama pegawai" class="{{ $kelasInput }}">
    </label>
    <label class="{{ $kelasLabel }}">Status
        <select name="status" onchange="this.form.submit()" class="{{ $kelasInput }}">
            <option value="">Semua status</option>
            @foreach (['Draf', 'Menunggu', 'Disetujui', 'Ditolak'] as $s)
                <option @selected(($filter['status'] ?? '') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </label>
    <label class="{{ $kelasLabel }}">Jenis kegiatan
        <select name="jenis" onchange="this.form.submit()" class="{{ $kelasInput }}">
            <option value="">Semua</option>
            <option value="Luar" @selected(($filter['jenis'] ?? '') === 'Luar')>Luar sekolah</option>
            <option value="Dalam" @selected(($filter['jenis'] ?? '') === 'Dalam')>Dalam sekolah</option>
        </select>
    </label>
    <a href="{{ route('admin.surat-tugas') }}"
       class="inline-flex items-center justify-center gap-2 rounded-xl border border-garis px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
        <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Reset
    </a>
</form>

{{-- ================= TABEL ================= --}}
<div class="mt-4 overflow-hidden rounded-2xl border border-garis bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="bg-krem text-left text-gray-600">
                <tr>
                    <th class="px-4 py-3 font-semibold">Perihal</th>
                    <th class="px-4 py-3 font-semibold">Pemberi</th>
                    <th class="px-4 py-3 font-semibold">Pegawai</th>
                    <th class="px-4 py-3 font-semibold">Tanggal</th>
                    <th class="px-4 py-3 font-semibold">Jenis</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @forelse ($surat as $s)
                    @php
                        $bisaUbah = in_array($s->status, ['Draf', 'Ditolak'], true);
                        $dataJs = [
                            'id' => $s->id, 'perihal' => $s->perihal, 'pemberi_tugas' => $s->pemberi_tugas,
                            'pegawai' => $s->pegawai->pluck('id')->values(),
                            'tanggal_mulai' => $s->tanggal_mulai->toDateString(),
                            'tanggal_selesai' => $s->tanggal_selesai->toDateString(),
                            'lokasi' => $s->lokasi, 'jenis_kegiatan' => $s->jenis_kegiatan, 'sifat' => $s->sifat,
                            'kasus_khusus' => $s->kasus_khusus, 'lampiran' => (bool) $s->lampiran_path,
                            'url_update' => route('admin.surat-tugas.update', $s),
                        ];
                        $namaPegawai = $s->pegawai->count() === 1
                            ? $s->pegawai->first()->nama_lengkap
                            : ($s->pegawai->count() === 2
                                ? $s->pegawai->first()->nama_lengkap.', +1'
                                : $s->pegawai->count().' pegawai');
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $s->perihal }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $s->nomor_surat ?? ($s->status === 'Draf' ? 'Draf' : 'Nomor setelah disetujui') }}
                                @if ($s->kasus_khusus) · <span class="font-semibold text-yellow-800">Kasus khusus</span> @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">{{ $s->pemberi_tugas }}</td>
                        <td class="px-4 py-3">{{ $namaPegawai }}</td>
                        <td class="px-4 py-3">{{ $s->rentangTanggal() }}</td>
                        <td class="px-4 py-3">{{ $s->jenis_kegiatan === 'Dalam' ? 'Dalam sekolah' : 'Luar sekolah' }}</td>
                        <td class="px-4 py-3"><x-lencana :status="$s->labelStatus()" /></td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('admin.surat-tugas.show', $s) }}" class="rounded-lg border border-garis p-2 hover:bg-gray-50" title="Lihat">
                                    <i data-lucide="eye" class="h-4 w-4"></i>
                                </a>
                                <button type="button" data-edit data-id="{{ $s->id }}" data-surat='@json($dataJs)' @disabled(! $bisaUbah)
                                        class="rounded-lg border border-garis p-2 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-30"
                                        title="{{ $bisaUbah ? 'Ubah' : 'Hanya draf atau surat ditolak yang dapat diubah' }}">
                                    <i data-lucide="pencil" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">Belum ada surat tugas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.paginasi', ['paginator' => $surat, 'satuan' => 'surat tugas'])
</div>

{{-- ================= POPUP TERBITKAN / UBAH ================= --}}
<dialog id="modal-surat" class="m-auto max-h-[92vh] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-3xl p-0 backdrop:bg-gray-900/60">
    <form id="form-surat" method="POST" enctype="multipart/form-data"
          action="{{ route('admin.surat-tugas.store') }}" data-url-tambah="{{ route('admin.surat-tugas.store') }}">
        @csrf
        <input type="hidden" name="_method" value="POST">
        <input type="hidden" name="_id" value="{{ old('_id') }}">

        <div class="flex items-start justify-between border-b border-garis px-6 py-4">
            <div>
                <h2 id="judul-modal" class="text-xl font-extrabold">Terbitkan surat tugas</h2>
                <p class="text-sm text-gray-500">Nomor surat dibuat otomatis setelah disetujui</p>
            </div>
            <button type="button" data-tutup-modal class="rounded-lg border border-garis p-2"><i data-lucide="x" class="h-4 w-4"></i></button>
        </div>

        <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
            <label class="{{ $kelasLabel }}">Perihal
                <input name="perihal" value="{{ old('perihal') }}" placeholder="Contoh: Rapat koordinasi dinas" class="{{ $kelasInput }}">
                @error('perihal') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <label class="{{ $kelasLabel }}">Pemberi tugas
                <select name="pemberi_tugas" class="{{ $kelasInput }}">
                    @foreach (['Dinas', 'Yayasan', 'Lainnya'] as $p)
                        <option @selected(old('pemberi_tugas') === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </label>

            {{-- Pemilih pegawai (chip) --}}
            <div id="pemilih-pegawai" class="relative sm:col-span-2">
                <span class="{{ $kelasLabel }}">Pegawai yang ditugaskan</span>
                <div id="chip-pegawai" class="mt-1 flex min-h-11 flex-wrap items-center gap-2 rounded-xl border border-garis bg-white p-2">
                    <input id="cari-pegawai" autocomplete="off" placeholder="Ketik nama untuk menambah" class="min-w-40 flex-1 px-1 text-sm outline-none">
                </div>
                <div id="daftar-pegawai" class="absolute z-10 mt-1 hidden max-h-60 w-full overflow-y-auto rounded-xl border border-garis bg-white shadow-lg"></div>
                @error('pegawai') <span data-galat class="mt-1 block text-xs text-merah">{{ $message }}</span> @enderror
                @error('pegawai.*') <span data-galat class="mt-1 block text-xs text-merah">{{ $message }}</span> @enderror
            </div>

            <label class="{{ $kelasLabel }}">Tanggal mulai
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" class="{{ $kelasInput }}">
                @error('tanggal_mulai') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>
            <label class="{{ $kelasLabel }}">Tanggal selesai
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" class="{{ $kelasInput }}">
                @error('tanggal_selesai') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <label class="{{ $kelasLabel }} sm:col-span-2">Lokasi
                <input name="lokasi" value="{{ old('lokasi') }}" placeholder="Contoh: Kantor Dinas Pendidikan Sleman" class="{{ $kelasInput }}">
                @error('lokasi') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <div>
                <span class="{{ $kelasLabel }}">Jenis kegiatan</span>
                <div class="mt-1 grid grid-cols-2 gap-2">
                    @foreach (['Luar' => 'Luar sekolah', 'Dalam' => 'Dalam sekolah'] as $nilai => $label)
                        <label class="flex items-center gap-2 rounded-xl border border-garis px-3 py-2.5 text-sm">
                            <input type="radio" name="jenis_kegiatan" value="{{ $nilai }}" class="accent-merah" @checked(old('jenis_kegiatan', 'Luar') === $nilai)>
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
                            <input type="radio" name="sifat" value="{{ $sifat }}" class="accent-merah" @checked(old('sifat', 'Terencana') === $sifat)>
                            {{ $sifat }}
                        </label>
                    @endforeach
                </div>
            </div>

            <label class="flex items-center gap-3 rounded-xl bg-yellow-50 px-4 py-3 text-sm font-semibold text-yellow-900 sm:col-span-2">
                <input type="checkbox" name="kasus_khusus" value="1" class="h-4 w-4 accent-merah" @checked(old('kasus_khusus'))>
                Kasus khusus: perlu persetujuan dan tanda tangan Ketua Yayasan
            </label>

            <div class="sm:col-span-2">
                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-garis bg-krem/60 px-4 py-3 text-sm font-semibold hover:bg-krem">
                    <i data-lucide="upload" class="h-4 w-4"></i> Lampirkan surat dari pemberi tugas (PDF/JPG)
                    <input type="file" name="lampiran" accept="application/pdf,image/jpeg,image/png" class="sr-only"
                           onchange="document.getElementById('info-lampiran').textContent = this.files[0]?.name ?? ''">
                </label>
                <p id="info-lampiran" class="mt-1 text-center text-xs text-gray-500">Opsional · maksimal 2 MB</p>
                @error('lampiran') <span data-galat class="mt-1 block text-center text-xs text-merah">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2 border-t border-garis bg-krem/50 px-6 py-4">
            <button type="button" data-tutup-modal class="rounded-xl border border-garis bg-white px-5 py-2.5 text-sm font-semibold">Batal</button>
            <button type="submit" name="aksi" value="draf" class="rounded-xl border border-garis bg-white px-5 py-2.5 text-sm font-semibold">Simpan draf</button>
            <button type="submit" name="aksi" value="kirim" class="rounded-xl bg-merah px-5 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">Kirim ke Kepala Sekolah</button>
        </div>
    </form>
</dialog>
@endsection

@push('script')
<script>
    const PEGAWAI = @json($pilihanPegawai);
    const modal = document.getElementById('modal-surat');
    const form = document.getElementById('form-surat');
    const kotakChip = document.getElementById('chip-pegawai');
    const inputCari = document.getElementById('cari-pegawai');
    const daftar = document.getElementById('daftar-pegawai');
    let terpilih = [];

    // ---------- Pemilih pegawai (chip) ----------
    function gambarChip() {
        kotakChip.querySelectorAll('[data-chip]').forEach((el) => el.remove());

        terpilih.forEach((id) => {
            const p = PEGAWAI.find((x) => x.id === id);
            if (!p) return;

            const chip = document.createElement('span');
            chip.dataset.chip = '';
            chip.className = 'inline-flex items-center gap-1 rounded-lg bg-merah-muda px-2 py-1 text-sm font-semibold text-merah';
            chip.append(p.nama);

            const hapus = document.createElement('button');
            hapus.type = 'button';
            hapus.className = 'ml-1 leading-none';
            hapus.textContent = '×';
            hapus.onclick = () => { terpilih = terpilih.filter((x) => x !== id); gambarChip(); };
            chip.append(hapus);

            const tersembunyi = document.createElement('input');
            tersembunyi.type = 'hidden';
            tersembunyi.name = 'pegawai[]';
            tersembunyi.value = id;
            chip.append(tersembunyi);

            kotakChip.insertBefore(chip, inputCari);
        });
    }

    function tampilDaftar() {
        const kata = inputCari.value.toLowerCase().trim();
        const hasil = PEGAWAI.filter((p) => !terpilih.includes(p.id) && p.nama.toLowerCase().includes(kata)).slice(0, 8);

        daftar.innerHTML = '';
        hasil.forEach((p) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-krem';
            b.textContent = `${p.nama} · ${p.unit}`;
            b.onclick = () => {
                terpilih.push(p.id);
                inputCari.value = '';
                gambarChip();
                daftar.classList.add('hidden');
                inputCari.focus();
            };
            daftar.append(b);
        });
        daftar.classList.toggle('hidden', hasil.length === 0);
    }

    inputCari.addEventListener('input', tampilDaftar);
    inputCari.addEventListener('focus', tampilDaftar);
    inputCari.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            daftar.querySelector('button')?.click();
        }
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#pemilih-pegawai')) daftar.classList.add('hidden');
    });

    // ---------- Popup ----------
    function aturMode(mode, data) {
        const tambah = mode === 'tambah';
        form.action = tambah ? form.dataset.urlTambah : data.url_update;
        form.elements['_method'].value = tambah ? 'POST' : 'PUT';
        form.elements['_id'].value = data?.id ?? '';
        document.getElementById('judul-modal').textContent = tambah ? 'Terbitkan surat tugas' : 'Ubah surat tugas';
    }

    function isi(data) {
        const hariIni = @json($hariIni);
        form.elements['perihal'].value = data?.perihal ?? '';
        form.elements['pemberi_tugas'].value = data?.pemberi_tugas ?? 'Dinas';
        form.elements['tanggal_mulai'].value = data?.tanggal_mulai ?? hariIni;
        form.elements['tanggal_selesai'].value = data?.tanggal_selesai ?? hariIni;
        form.elements['lokasi'].value = data?.lokasi ?? '';
        form.elements['jenis_kegiatan'].value = data?.jenis_kegiatan ?? 'Luar';
        form.elements['sifat'].value = data?.sifat ?? 'Terencana';
        form.elements['kasus_khusus'].checked = !!data?.kasus_khusus;
        form.elements['lampiran'].value = '';
        document.getElementById('info-lampiran').textContent = data?.lampiran
            ? 'Lampiran sudah ada · unggah file baru untuk mengganti'
            : 'Opsional · maksimal 2 MB';
        terpilih = data ? [...data.pegawai] : [];
        gambarChip();
        modal.querySelectorAll('[data-galat]').forEach((el) => el.remove());
    }

    document.querySelector('[data-tambah]').addEventListener('click', () => {
        aturMode('tambah', null);
        isi(null);
        modal.showModal();
    });

    document.querySelectorAll('[data-edit]').forEach((tombol) =>
        tombol.addEventListener('click', () => {
            const data = JSON.parse(tombol.dataset.surat);
            aturMode('edit', data);
            isi(data);
            modal.showModal();
        }));

    document.querySelectorAll('[data-tutup-modal]').forEach((tombol) =>
        tombol.addEventListener('click', () => tombol.closest('dialog').close()));

    // ---------- Buka kembali popup jika validasi gagal ----------
    const modeAwal = @json(session('modal'));
    if (modeAwal) {
        const idAwal = @json(old('_id'));
        const tombolAwal = idAwal ? document.querySelector(`[data-edit][data-id="${idAwal}"]`) : null;
        aturMode(tombolAwal ? 'edit' : 'tambah', tombolAwal ? JSON.parse(tombolAwal.dataset.surat) : null);
        terpilih = @json($pegawaiLama);
        gambarChip();
        modal.showModal();
    }
</script>
@endpush