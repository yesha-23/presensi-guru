@extends('layouts.app')

@section('konten')
@php
    $warnaStatus = [
        'Aktif'    => 'bg-emerald-50 text-emerald-800',
        'Nonaktif' => 'bg-gray-100 text-gray-700',
        'Keluar'   => 'bg-red-100 text-red-800',
    ];
    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-navy disabled:bg-gray-50';
    $kelasLabel = 'block text-sm font-semibold';
@endphp

{{-- ================= JUDUL ================= --}}
<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Data Pegawai</h1>
        <p class="text-sm text-gray-500">
            {{ $jumlahStatus->sum() }} pegawai · akun dan kata sandi hanya dibuat administrator
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.pegawai.impor') }}"
           class="inline-flex items-center gap-2 rounded-xl border border-garis bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
            <i data-lucide="upload" class="h-4 w-4"></i> Impor Excel
        </a>
        <button type="button" data-tambah
                class="inline-flex items-center gap-2 rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">
            <i data-lucide="plus" class="h-4 w-4"></i> Tambah pegawai
        </button>
    </div>
</div>

{{-- Tawaran daftarkan wajah setelah pegawai baru disimpan (activity diagram 5.5) --}}
@if (session('tawarkan_wajah') && ($baru = \App\Models\User::find(session('tawarkan_wajah'))))
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-yellow-200 bg-yellow-50 px-4 py-3">
        <span class="text-sm">Daftarkan data wajah <b>{{ $baru->nama_lengkap }}</b> sekarang? Pegawai belum bisa presensi sebelum wajahnya terdaftar.</span>
        <a href="{{ route('admin.pegawai.wajah', $baru) }}"
           class="inline-flex items-center gap-2 rounded-xl bg-navy px-4 py-2 text-sm font-semibold text-white">
            <i data-lucide="scan-face" class="h-4 w-4"></i> Daftarkan wajah
        </a>
    </div>
@endif

{{-- ================= FILTER ================= --}}
<form method="GET" class="mt-6 grid gap-3 rounded-2xl border border-garis bg-white p-4 md:grid-cols-[1fr_10rem_10rem_10rem_auto] md:items-end">
    <label class="{{ $kelasLabel }}">Cari
        <input name="cari" value="{{ $filter['cari'] ?? '' }}" placeholder="Nama, panggilan, atau NIP" class="{{ $kelasInput }}">
    </label>
    <label class="{{ $kelasLabel }}">Unit
        <select name="unit" onchange="this.form.submit()" class="{{ $kelasInput }}">
            <option value="">Semua unit</option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected(($filter['unit'] ?? '') == $unit->id)>{{ $unit->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="{{ $kelasLabel }}">Kategori
        <select name="kategori" onchange="this.form.submit()" class="{{ $kelasInput }}">
            <option value="">Semua kategori</option>
            @foreach ($daftarKategori as $kategori)
                <option @selected(($filter['kategori'] ?? '') === $kategori)>{{ $kategori }}</option>
            @endforeach
        </select>
    </label>
    <label class="{{ $kelasLabel }}">Status
        <select name="status" onchange="this.form.submit()" class="{{ $kelasInput }}">
            <option value="">Semua status</option>
            @foreach ($daftarStatus as $status)
                <option @selected(($filter['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </label>
    <a href="{{ route('admin.pegawai') }}"
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
                    <th class="px-4 py-3 font-semibold">Nama</th>
                    <th class="px-4 py-3 font-semibold">NIP</th>
                    <th class="px-4 py-3 font-semibold">Unit</th>
                    <th class="px-4 py-3 font-semibold">Kategori</th>
                    <th class="px-4 py-3 font-semibold">Peran</th>
                    <th class="px-4 py-3 font-semibold">Data wajah</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @forelse ($pegawai as $p)
                    @php
                        $peran = collect($daftarPeran)->intersect($p->roles->pluck('nama'))->values();
                        $wajibWajah = $peran->contains(\App\Models\Role::PEGAWAI);
                        $dataJs = [
                            'id' => $p->id, 'nip' => $p->nip, 'nama_lengkap' => $p->nama_lengkap,
                            'nama_panggilan' => $p->nama_panggilan, 'email' => $p->email,
                            'unit_id' => $p->unit_id, 'unit' => $p->unit->label(), 'kategori' => $p->kategori,
                            'jabatan' => $p->jabatan, 'status' => $p->status, 'peran' => $peran,
                            'wajah' => $p->face_descriptors_count, 'wajib_wajah' => $wajibWajah,
                            'url_update' => route('admin.pegawai.update', $p),
                            'url_reset'  => route('admin.pegawai.reset-sandi', $p),
                            'url_hapus'  => route('admin.pegawai.destroy', $p),
                            'url_wajah'  => route('admin.pegawai.wajah', $p),
                        ];
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $p->nama_lengkap }}</div>
                            <div class="text-xs text-gray-500">{{ $p->nama_panggilan ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $p->nip }}</td>
                        <td class="px-4 py-3">{{ $p->unit->label() }}</td>
                        <td class="px-4 py-3">{{ $p->kategori ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $peran->join(', ') }}</td>
                        <td class="px-4 py-3">
                            @if (! $wajibWajah)
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Tidak perlu</span>
                            @elseif ($p->face_descriptors_count >= 3)
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">Terdaftar</span>
                            @else
                                <span class="rounded-full bg-merah-muda px-2.5 py-1 text-xs font-semibold text-merah">Belum</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $warnaStatus[$p->status] }}">{{ $p->status }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" data-edit data-id="{{ $p->id }}" data-pegawai='@json($dataJs)'
                                        class="rounded-lg border border-garis p-2 hover:bg-gray-50" title="Edit">
                                    <i data-lucide="pencil" class="h-4 w-4"></i>
                                </button>
                                <button type="button" data-hapus data-pegawai='@json($dataJs)' @disabled($p->status !== 'Keluar')
                                        class="rounded-lg border border-red-200 p-2 text-merah hover:bg-merah-muda disabled:cursor-not-allowed disabled:opacity-30"
                                        title="{{ $p->status === 'Keluar' ? 'Hapus data pegawai' : 'Hanya untuk pegawai berstatus Keluar' }}">
                                    <i data-lucide="trash-2" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-500">Tidak ada pegawai yang cocok dengan filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.paginasi', [
        'paginator' => $pegawai,
        'satuan'    => 'pegawai',
        'tambahan'  => ' · '.($jumlahStatus['Aktif'] ?? 0).' aktif, '.($jumlahStatus['Keluar'] ?? 0).' keluar',
    ])
</div>

{{-- ================= POPUP TAMBAH / EDIT ================= --}}
<dialog id="modal-pegawai" class="m-auto max-h-[92vh] w-[calc(100%-2rem)] max-w-2xl overflow-y-auto rounded-3xl p-0 backdrop:bg-gray-900/60">
    <form id="form-pegawai" method="POST" data-url-tambah="{{ route('admin.pegawai.store') }}" action="{{ route('admin.pegawai.store') }}">
        @csrf
        <input type="hidden" name="_method" value="POST">
        <input type="hidden" name="_id" value="{{ old('_id') }}">

        <div class="flex items-start justify-between border-b border-garis px-6 py-4">
            <div>
                <h2 id="judul-modal" class="text-xl font-extrabold">Tambah pegawai</h2>
                <p id="sub-modal" class="text-sm text-gray-500">Data pegawai sekaligus akun login</p>
            </div>
            <button type="button" data-tutup-modal class="rounded-lg border border-garis p-2"><i data-lucide="x" class="h-4 w-4"></i></button>
        </div>

        <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
            @foreach ([
                ['nip', 'NIP', '[NIP]'],
                ['email', 'Email', 'nama@contoh.sch.id'],
                ['nama_lengkap', 'Nama lengkap (dengan gelar)', 'Contoh: Kirana Sentani Putri, S.Pd'],
                ['nama_panggilan', 'Nama panggilan', 'Contoh: Bu Putri'],
            ] as [$nama, $label, $placeholder])
                <label class="{{ $kelasLabel }}">{{ $label }}
                    <input name="{{ $nama }}" value="{{ old($nama) }}" placeholder="{{ $placeholder }}" class="{{ $kelasInput }}">
                    @error($nama) <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                </label>
            @endforeach

            <label class="{{ $kelasLabel }}">Unit
                <select name="unit_id" class="{{ $kelasInput }}">
                    <option value="">Pilih unit</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>{{ $unit->label() }}</option>
                    @endforeach
                </select>
                @error('unit_id') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <label class="{{ $kelasLabel }}">Kategori
                <select name="kategori" class="{{ $kelasInput }}">
                    <option value="">— (tidak presensi)</option>
                    @foreach ($daftarKategori as $kategori)
                        <option @selected(old('kategori') === $kategori)>{{ $kategori }}</option>
                    @endforeach
                </select>
                @error('kategori') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <label class="{{ $kelasLabel }}">Jabatan
                <input name="jabatan" value="{{ old('jabatan') }}" placeholder="Contoh: Wali Kelas" class="{{ $kelasInput }}">
                @error('jabatan') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            {{-- Khusus tambah: kata sandi awal --}}
            <div data-hanya-tambah>
                <span class="{{ $kelasLabel }}">Kata sandi awal</span>
                <div class="flex gap-2">
                    <input name="kata_sandi" value="{{ old('kata_sandi') }}" class="{{ $kelasInput }} font-mono">
                    <button type="button" id="tombol-buat-sandi" class="mt-1 rounded-xl border border-garis px-3 text-sm font-semibold hover:bg-gray-50">Buat</button>
                </div>
                @error('kata_sandi') <span data-galat class="mt-1 block text-xs text-merah">{{ $message }}</span> @enderror
            </div>

            {{-- Khusus edit: status --}}
            <label data-hanya-edit class="{{ $kelasLabel }} hidden">Status
                <select name="status" class="{{ $kelasInput }}">
                    @foreach ($daftarStatus as $status)
                        <option @selected(old('status') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                @error('status') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            {{-- Khusus edit: reset kata sandi, administrator mengetik sandi baru --}}
            <div data-hanya-edit class="hidden sm:col-span-2">
                <span class="{{ $kelasLabel }}">Reset kata sandi</span>
                <div class="mt-1 flex gap-2">
                    <input type="text" name="kata_sandi_baru" form="form-reset-sandi" minlength="8" maxlength="64"
                           autocomplete="new-password" placeholder="Ketik kata sandi baru (min. 8 karakter)"
                           class="{{ $kelasInput }} mt-0 flex-1 font-mono">
                    <button type="submit" form="form-reset-sandi"
                            class="flex shrink-0 items-center gap-2 rounded-xl border border-garis px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
                        <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Simpan sandi
                    </button>
                </div>
                <p class="mt-1 text-xs font-normal text-gray-500">Sampaikan kata sandi baru kepada pegawai, lalu minta ia menggantinya sendiri melalui menu Ganti kata sandi. Pegawai akan keluar dari semua perangkat.</p>
                @error('kata_sandi_baru') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </div>

            {{-- Peran akses --}}
            <div class="sm:col-span-2">
                <span class="{{ $kelasLabel }}">Peran akses</span>
                <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($daftarPeran as $peran)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="peran[]" value="{{ $peran }}" class="h-4 w-4 accent-merah"
                                   @checked(in_array($peran, old('peran', []), true))>
                            {{ $peran }}
                        </label>
                    @endforeach
                </div>
                @error('peran') <span data-galat class="mt-1 block text-xs text-merah">{{ $message }}</span> @enderror
                @error('peran.*') <span data-galat class="mt-1 block text-xs text-merah">{{ $message }}</span> @enderror
            </div>

            {{-- Data wajah --}}
            <div class="sm:col-span-2">
                <span class="{{ $kelasLabel }}">Data wajah</span>
                <div class="mt-1 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-garis p-3">
                    <div id="info-wajah"></div>
                    <a id="tombol-wajah" href="#"
                       class="hidden items-center gap-2 rounded-xl border border-garis px-4 py-2 text-sm font-semibold hover:bg-gray-50">
                        <i data-lucide="scan-face" class="h-4 w-4"></i> <span>Daftarkan wajah</span>
                    </a>
                </div>
            </div>

            {{-- Hapus data pegawai: hanya untuk status Keluar --}}
            <div id="kotak-hapus" class="hidden sm:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 p-3">
                    <div>
                        <div class="font-bold text-red-900">Hapus data pegawai</div>
                        <div class="text-sm text-red-800">Tersedia karena status pegawai Keluar</div>
                    </div>
                    <button type="button" id="tombol-hapus-dari-edit"
                            class="inline-flex items-center gap-2 rounded-xl bg-merah-tua px-4 py-2 text-sm font-semibold text-white">
                        <i data-lucide="trash-2" class="h-4 w-4"></i> Hapus data
                    </button>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-garis bg-krem/50 px-6 py-4">
            <button type="button" data-tutup-modal class="rounded-xl border border-garis bg-white px-5 py-2.5 text-sm font-semibold">Batal</button>
            <button type="submit" class="rounded-xl bg-merah px-5 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">Simpan</button>
        </div>
    </form>
</dialog>

{{-- Form reset kata sandi (di luar form utama karena form tidak boleh bersarang) --}}
<form id="form-reset-sandi" method="POST" class="hidden">@csrf<input type="hidden" name="_id"></form>

{{-- ================= POPUP HAPUS ================= --}}
<dialog id="modal-hapus" class="m-auto w-[calc(100%-2rem)] max-w-xl rounded-3xl p-0 backdrop:bg-gray-900/60">
    <form id="form-hapus" method="POST">
        @csrf
        @method('DELETE')

        <div class="flex items-start justify-between border-b border-garis px-6 py-4">
            <div>
                <h2 class="text-xl font-extrabold">Hapus data pegawai?</h2>
                <p id="sub-hapus" class="text-sm text-gray-500"></p>
            </div>
            <button type="button" data-tutup-modal class="rounded-lg border border-garis p-2"><i data-lucide="x" class="h-4 w-4"></i></button>
        </div>

        <div class="grid gap-3 px-6 py-5 sm:grid-cols-2">
            <div class="rounded-2xl bg-red-50 p-4 text-sm text-red-900">
                <div class="font-bold">Dihapus permanen</div>
                <ul class="mt-1 list-disc pl-5">
                    <li>Akun login</li>
                    <li>Data dan foto wajah</li>
                    <li>NIP dan email</li>
                </ul>
            </div>
            <div class="rounded-2xl bg-krem p-4 text-sm text-gray-700">
                <div class="font-bold">Tetap disimpan sebagai arsip</div>
                <ul class="mt-1 list-disc pl-5">
                    <li>Nama pada riwayat presensi</li>
                    <li>Rekap dan laporan PKG periode lalu</li>
                </ul>
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold sm:col-span-2">
                <input type="checkbox" id="cek-paham" name="paham" value="1" class="h-4 w-4 accent-merah">
                Saya memahami tindakan ini tidak dapat dibatalkan
            </label>
        </div>

        <div class="flex justify-end gap-2 border-t border-garis bg-krem/50 px-6 py-4">
            <button type="button" data-tutup-modal class="rounded-xl border border-garis bg-white px-5 py-2.5 text-sm font-semibold">Batal</button>
            <button type="submit" id="tombol-hapus" disabled
                    class="inline-flex items-center gap-2 rounded-xl bg-merah-tua px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-40">
                <i data-lucide="trash-2" class="h-4 w-4"></i> Hapus data
            </button>
        </div>
    </form>
</dialog>
@endsection

@push('script')
<script>
    const modal = document.getElementById('modal-pegawai');
    const form = document.getElementById('form-pegawai');
    const formReset = document.getElementById('form-reset-sandi');
    const modalHapus = document.getElementById('modal-hapus');
    const formHapus = document.getElementById('form-hapus');
    const cekPaham = document.getElementById('cek-paham');
    const tombolHapus = document.getElementById('tombol-hapus');
    let dataAktif = null;

    const buatSandi = (panjang = 10) => {
        const huruf = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        return Array.from(crypto.getRandomValues(new Uint32Array(panjang)), (x) => huruf[x % huruf.length]).join('');
    };

    const lencana = (teks, warna) =>
        `<span class="rounded-full px-2.5 py-1 text-xs font-semibold ${warna}">${teks}</span>`;

    function aturMode(mode, data) {
        dataAktif = data;
        const tambah = mode === 'tambah';

        form.action = tambah ? form.dataset.urlTambah : data.url_update;
        form.elements['_method'].value = tambah ? 'POST' : 'PUT';
        form.elements['_id'].value = data?.id ?? '';

        document.getElementById('judul-modal').textContent = tambah ? 'Tambah pegawai' : 'Edit pegawai';
        document.getElementById('sub-modal').textContent = tambah
            ? 'Data pegawai sekaligus akun login'
            : [data.nama_lengkap, [data.kategori, data.unit].filter(Boolean).join(' ')].join(' · ');

        // Bagian yang hanya muncul saat tambah/edit; input yang disembunyikan ikut dinonaktifkan
        modal.querySelectorAll('[data-hanya-tambah]').forEach((el) => {
            el.classList.toggle('hidden', !tambah);
            el.querySelectorAll('input, select').forEach((i) => (i.disabled = !tambah));
        });
        modal.querySelectorAll('[data-hanya-edit]').forEach((el) => {
            el.classList.toggle('hidden', tambah);
            el.querySelectorAll('input, select').forEach((i) => (i.disabled = tambah));
        });

        // Data wajah
        const info = document.getElementById('info-wajah');
        const tombolWajah = document.getElementById('tombol-wajah');
        tombolWajah.classList.add('hidden');
        tombolWajah.classList.remove('inline-flex');

        if (tambah) {
            info.innerHTML = lencana('Belum terdaftar', 'bg-merah-muda text-merah') +
                '<p class="mt-1 text-xs text-gray-500">Simpan data pegawai dulu, lalu daftarkan wajah.</p>';
        } else if (!data.wajib_wajah) {
            info.innerHTML = lencana('Tidak perlu', 'bg-gray-100 text-gray-700') +
                '<p class="mt-1 text-xs text-gray-500">Akun ini tidak melakukan presensi.</p>';
        } else {
            const ada = data.wajah >= 3;
            info.innerHTML = ada
                ? lencana('Sudah terdaftar', 'bg-blue-50 text-blue-800') + `<p class="mt-1 text-xs text-gray-500">${data.wajah} foto referensi</p>`
                : lencana('Belum terdaftar', 'bg-merah-muda text-merah') + '<p class="mt-1 text-xs text-gray-500">Wajib didaftarkan sebelum pegawai bisa presensi.</p>';
            tombolWajah.href = data.url_wajah;
            tombolWajah.querySelector('span').textContent = ada ? 'Ambil ulang data wajah' : 'Daftarkan wajah';
            tombolWajah.classList.remove('hidden');
            tombolWajah.classList.add('inline-flex');
        }

        document.getElementById('kotak-hapus').classList.toggle('hidden', tambah || data.status !== 'Keluar');
        if (!tambah) {
            formReset.action = data.url_reset;
            formReset.elements['_id'].value = data.id;
            const isianSandi = document.querySelector('[name="kata_sandi_baru"]');
            if (!@json($errors->has('kata_sandi_baru'))) isianSandi.value = '';
        }
    }

    function isi(data) {
        ['nip', 'nama_lengkap', 'nama_panggilan', 'email', 'jabatan']
            .forEach((k) => (form.elements[k].value = data?.[k] ?? ''));
        form.elements['unit_id'].value = data?.unit_id ?? '';
        form.elements['kategori'].value = data?.kategori ?? '';
        form.elements['status'].value = data?.status ?? 'Aktif';
        form.elements['kata_sandi'].value = data ? '' : buatSandi();
        form.querySelectorAll('input[name="peran[]"]').forEach((cb) =>
            (cb.checked = data ? data.peran.includes(cb.value) : cb.value === 'Pegawai'));
        modal.querySelectorAll('[data-galat]').forEach((el) => el.remove());
    }

    function bukaHapus(data) {
        formHapus.action = data.url_hapus;
        document.getElementById('sub-hapus').textContent =
            [data.nama_lengkap, [data.kategori, data.unit].filter(Boolean).join(' '), 'status Keluar'].join(' · ');
        cekPaham.checked = false;
        tombolHapus.disabled = true;
        modal.close();
        modalHapus.showModal();
    }

    // ---------- Event ----------
    document.querySelector('[data-tambah]').addEventListener('click', () => {
        aturMode('tambah', null);
        isi(null);
        modal.showModal();
    });

    document.querySelectorAll('[data-edit]').forEach((tombol) =>
        tombol.addEventListener('click', () => {
            const data = JSON.parse(tombol.dataset.pegawai);
            aturMode('edit', data);
            isi(data);
            modal.showModal();
        })
    );

    document.querySelectorAll('[data-hapus]').forEach((tombol) =>
        tombol.addEventListener('click', () => bukaHapus(JSON.parse(tombol.dataset.pegawai)))
    );
    document.getElementById('tombol-hapus-dari-edit').addEventListener('click', () => bukaHapus(dataAktif));
    cekPaham.addEventListener('change', () => (tombolHapus.disabled = !cekPaham.checked));

    document.getElementById('tombol-buat-sandi').addEventListener('click', () =>
        (form.elements['kata_sandi'].value = buatSandi()));

    document.querySelectorAll('[data-tutup-modal]').forEach((tombol) =>
        tombol.addEventListener('click', () => tombol.closest('dialog').close()));

    formReset.addEventListener('submit', (e) => {
        if (!confirm(`Ganti kata sandi ${dataAktif?.nama_lengkap} dengan kata sandi yang Anda ketik?`)) {
            e.preventDefault();
        }
    });

    // ---------- Buka kembali popup jika validasi gagal ----------
    const modeAwal = @json(session('modal'));
    if (modeAwal) {
        const idAwal = @json(old('_id'));
        const tombolAwal = idAwal ? document.querySelector(`[data-edit][data-id="${idAwal}"]`) : null;

        if (modeAwal === 'tambah' || tombolAwal) {
            aturMode(modeAwal, tombolAwal ? JSON.parse(tombolAwal.dataset.pegawai) : null);
            modal.showModal();
        }
    }
</script>
@endpush