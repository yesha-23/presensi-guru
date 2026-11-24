@extends('layouts.app')

@section('konten')
@php
    use App\Http\Controllers\Admin\DataPresensiController as DP;
    use App\Models\Presensi;
    use App\Services\KoreksiPresensiService;

    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-navy';
    $kelasLabel = 'block text-sm font-semibold';
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Data Presensi</h1>
        <p class="text-sm text-gray-500">
            Presensi semua pegawai per tanggal · {{ $tanggal->translatedFormat('l, j F Y') }} · waktu tercatat sampai detik
        </p>
    </div>
    <a href="{{ route('admin.presensi.ekspor', request()->query()) }}"
       class="inline-flex items-center gap-2 rounded-xl border border-garis bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
        <i data-lucide="download" class="h-4 w-4"></i> Unduh Excel
    </a>
</div>

{{-- ================= FILTER: Cari · Tanggal · Unit · Status · Reset =================
     Laptop: 1 baris. HP: baris 1 = Cari + Reset, baris 2 = Tanggal, Unit, Status --}}
@php
    $kelasKotak = 'flex h-10 min-w-0 items-center gap-2 rounded-xl border border-garis bg-white px-2.5 text-sm focus-within:border-navy md:px-3';
    $kelasIsi = 'h-full w-full min-w-0 border-0 bg-transparent p-0 text-sm outline-none focus:ring-0';
    $kelasIkon = 'hidden h-4 w-4 shrink-0 text-gray-400 md:block';
@endphp
<form method="GET"
      class="mt-6 grid grid-cols-6 gap-2 rounded-2xl border border-garis bg-white p-3 md:flex md:items-center">

    {{-- 1. Cari --}}
    <label class="{{ $kelasKotak }} order-1 col-span-5 md:order-none md:flex-1">
        <i data-lucide="search" class="h-4 w-4 shrink-0 text-gray-400"></i>
        <span class="sr-only">Cari pegawai</span>
        <input name="cari" value="{{ $filter['cari'] ?? '' }}" placeholder="Cari nama atau NIP, tekan Enter"
               class="{{ $kelasIsi }}">
    </label>

    {{-- 2. Tanggal --}}
    <label class="{{ $kelasKotak }} order-3 col-span-2 md:order-none md:w-44 md:shrink-0">
        <span class="sr-only">Tanggal</span>
        <input type="date" name="tanggal" value="{{ $tanggal->toDateString() }}" max="{{ today()->toDateString() }}"
               onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
    </label>

    {{-- 3. Unit --}}
    <label class="{{ $kelasKotak }} order-3 col-span-2 md:order-none md:w-40 md:shrink-0">
        <i data-lucide="building-2" class="{{ $kelasIkon }}"></i>
        <span class="sr-only">Unit</span>
        <select name="unit" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
            <option value="">Semua unit</option>
            @foreach ($units as $u)
                <option value="{{ $u->id }}" @selected(($filter['unit'] ?? '') == $u->id)>{{ $u->label() }}</option>
            @endforeach
        </select>
    </label>

    {{-- 4. Status --}}
    <label class="{{ $kelasKotak }} order-3 col-span-2 md:order-none md:w-52 md:shrink-0">
        <i data-lucide="list-filter" class="{{ $kelasIkon }}"></i>
        <span class="sr-only">Status</span>
        <select name="status" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
            <option value="">Semua status</option>
            @foreach ($daftarStatus as $s)
                <option @selected(($filter['status'] ?? '') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </label>

    {{-- 5. Reset (HP: di samping Cari) --}}
    <a href="{{ route('admin.presensi') }}" aria-label="Reset filter" title="Reset filter"
       class="order-2 col-span-1 inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-garis text-sm font-semibold hover:bg-gray-50 md:order-none md:shrink-0 md:px-4">
        <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
        <span class="hidden md:inline">Reset</span>
    </a>
</form>

{{-- B6: keterangan bila ada pegawai yang tanggal ini bukan hari kerja --}}
@php $adaLibur = in_array(false, $hariKerja, true); @endphp
@if ($adaLibur)
    <div class="mt-4 flex items-start gap-2 rounded-2xl bg-gray-100 px-4 py-3 text-sm text-gray-700">
        <i data-lucide="calendar-off" class="mt-0.5 h-4 w-4 shrink-0"></i>
        <span>
            {{ $tanggal->translatedFormat('l, j F Y') }} {{ in_array(true, $hariKerja, true) ? 'bukan hari kerja bagi sebagian pegawai' : 'bukan hari kerja' }}.
            Pegawai yang tidak bertugas berstatus <b>Libur</b>, dan presensinya tidak dapat diedit kecuali tanggal ini
            ditetapkan sebagai <b>Hari Kerja Tambahan</b> di Kalender Akademik.
        </span>
    </div>
@endif

{{-- ================= TABEL ================= --}}
<div class="mt-4 overflow-hidden rounded-2xl border border-garis bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="bg-krem text-left text-gray-600">
                <tr>
                    <th class="px-4 py-3 font-semibold">Nama</th>
                    <th class="px-4 py-3 font-semibold">Unit</th>
                    <th class="px-4 py-3 font-semibold">Datang</th>
                    <th class="px-4 py-3 font-semibold">Status datang</th>
                    <th class="px-4 py-3 font-semibold">Pulang</th>
                    <th class="px-4 py-3 font-semibold">Status pulang</th>
                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @forelse ($pegawai as $u)
                    @php
                        $p = $u->presensi->first();
                        $statusDatang = DP::statusDatang($p, $tanggal);
                        $statusPulang = DP::statusPulang($p, $tanggal);
                        $sedangMenunggu = $p && in_array($p->id, $menunggu, true);
                        $bukanHariKerja = ! ($hariKerja[$u->id] ?? true);
                        if ($bukanHariKerja && in_array($statusDatang, ['—', 'Belum Presensi'], true)) {
                            $statusDatang = 'Libur';
                        }
                        $judulEdit = match (true) {
                            $bukanHariKerja => 'Bukan hari kerja: presensi tidak dapat diedit',
                            $sedangMenunggu => 'Menunggu persetujuan kepala sekolah',
                            default         => 'Edit presensi',
                        };
                        $dataJs = [
                            'user_id'         => $u->id,
                            'nama'            => $u->nama_lengkap,
                            'keterangan'      => $u->kategori.' '.$u->unit->label().' · '.$tanggal->translatedFormat('l, j F Y'),
                            'tercatat_datang' => $p?->jam_datang ? $p->jam_datang.' · '.$p->status_datang.($p->detik_terlambat ? ' '.Presensi::formatDetik($p->detik_terlambat) : '') : '—',
                            'tercatat_pulang' => $p?->jam_pulang ? $p->jam_pulang.' · '.$statusPulang : '—',
                            'status_harian'   => $p?->status_harian ?? ($p?->jam_datang ? 'Hadir' : 'Alpa'),
                            'jam_datang'      => $p?->jam_datang ?? '',
                            'jam_pulang'      => $p?->jam_pulang ?? '',
                        ];
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $u->nama_lengkap }}</div>
                            <div class="text-xs text-gray-500">{{ $u->kategori }}{{ $u->jabatan ? ' · '.$u->jabatan : '' }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $u->unit->label() }}</td>
                        <td class="px-4 py-3 font-semibold tabular-nums">{{ $p?->jam_datang ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($statusDatang !== '—')
                                <x-lencana :status="$statusDatang"
                                           :teks="match ($statusDatang) {
                                               'Terlambat'           => 'Terlambat '.Presensi::formatDetik($p->detik_terlambat),
                                               Presensi::LEWAT_BATAS => 'Lewat batas '.Presensi::formatDetik($p->detik_terlambat),
                                               default               => null,
                                           }" />
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-semibold tabular-nums">{{ $p?->jam_pulang ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($statusPulang !== '—')
                                <x-lencana :status="$statusPulang"
                                           :teks="$statusPulang === 'Pulang Cepat' ? 'Pulang Cepat '.Presensi::formatDetik($p->detik_pulang_cepat) : null" />
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" data-edit data-id="{{ $u->id }}" data-presensi='@json($dataJs)' @disabled($sedangMenunggu || $bukanHariKerja)
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-garis px-3 py-1.5 text-sm font-semibold hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                                    title="{{ $judulEdit }}">
                                <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">Tidak ada pegawai yang cocok dengan filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @include('partials.paginasi', ['paginator' => $pegawai, 'satuan' => 'pegawai'])
</div>

{{-- ================= LOG EDIT ================= --}}
<div class="mt-6 overflow-hidden rounded-2xl border border-garis bg-white">
    <div class="flex items-center justify-between border-b border-garis px-4 py-3">
        <h2 class="font-bold">Log edit presensi</h2>
        <span class="text-sm text-gray-500">{{ $log->count() }} perubahan terbaru</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="bg-krem text-left text-gray-600">
                <tr>
                    <th class="px-4 py-2.5 font-semibold">Waktu</th>
                    <th class="px-4 py-2.5 font-semibold">Oleh</th>
                    <th class="px-4 py-2.5 font-semibold">Pegawai</th>
                    <th class="px-4 py-2.5 font-semibold">Tanggal presensi</th>
                    <th class="px-4 py-2.5 font-semibold">Perubahan</th>
                    <th class="px-4 py-2.5 font-semibold">Alasan</th>
                    <th class="px-4 py-2.5 font-semibold">Persetujuan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @forelse ($log as $l)
                    <tr>
                        <td class="px-4 py-2.5 whitespace-nowrap">{{ $l->created_at->translatedFormat('j M Y H.i') }}</td>
                        <td class="px-4 py-2.5">{{ $l->pengubah?->nama_lengkap }}</td>
                        <td class="px-4 py-2.5 font-semibold">{{ $l->target?->user?->nama_lengkap }}</td>
                        <td class="px-4 py-2.5 whitespace-nowrap">{{ $l->target?->tanggal?->translatedFormat('j M Y') }}</td>
                        <td class="px-4 py-2.5">{{ KoreksiPresensiService::ringkasan($l->nilai_lama, $l->nilai_baru) }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $l->alasan }}</td>
                        <td class="px-4 py-2.5">
                            <x-lencana :status="$l->status_persetujuan"
                                       :teks="$l->status_persetujuan === 'Menunggu' ? 'Menunggu kepala sekolah' : null" />
                            @if ($l->status_persetujuan === 'Ditolak' && $l->catatan_penyetuju)
                                <div class="mt-1 text-xs text-red-700">{{ $l->catatan_penyetuju }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Belum ada perubahan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ================= POPUP EDIT ================= --}}
<dialog id="modal-edit" class="m-auto max-h-[92vh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-3xl p-0 backdrop:bg-gray-900/60">
    <form id="form-edit" method="POST" action="{{ route('admin.presensi.koreksi') }}">
        @csrf
        <input type="hidden" name="user_id" value="{{ old('user_id') }}">
        <input type="hidden" name="tanggal" value="{{ old('tanggal', $tanggal->toDateString()) }}">

        <div class="flex items-start justify-between border-b border-garis px-6 py-4">
            <div>
                <h2 class="text-xl font-extrabold">Edit presensi</h2>
                <p id="sub-edit" class="text-sm text-gray-500"></p>
            </div>
            <button type="button" data-tutup-modal class="rounded-lg border border-garis p-2"><i data-lucide="x" class="h-4 w-4"></i></button>
        </div>

        <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
            <div>
                <div class="text-sm font-semibold">Tercatat datang</div>
                <div id="tercatat-datang" class="mt-1 rounded-xl bg-krem px-3 py-2.5 text-sm font-bold"></div>
            </div>
            <div>
                <div class="text-sm font-semibold">Tercatat pulang</div>
                <div id="tercatat-pulang" class="mt-1 rounded-xl bg-krem px-3 py-2.5 text-sm font-bold"></div>
            </div>

            <label class="{{ $kelasLabel }} sm:col-span-2">Status harian
                <select name="status_harian" class="{{ $kelasInput }}">
                    @foreach ($statusEdit as $s)
                        <option @selected(old('status_harian') === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                @error('status_harian') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <div id="kotak-jam" class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                <label class="{{ $kelasLabel }}">Jam datang
                    <input type="time" step="1" name="jam_datang" value="{{ old('jam_datang') }}" class="{{ $kelasInput }}">
                    @error('jam_datang') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                </label>
                <label class="{{ $kelasLabel }}">Jam pulang
                    <input type="time" step="1" name="jam_pulang" value="{{ old('jam_pulang') }}" class="{{ $kelasInput }}">
                    @error('jam_pulang') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="{{ $kelasLabel }} sm:col-span-2">Alasan edit <span class="text-merah">(wajib)</span>
                <textarea name="alasan" rows="3" maxlength="500" class="{{ $kelasInput }}"
                          placeholder="Contoh: HP pegawai rusak, hadir sesuai konfirmasi kepala sekolah">{{ old('alasan') }}</textarea>
                @error('alasan') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <p class="rounded-xl bg-krem p-3 text-xs text-gray-600 sm:col-span-2">
                Perubahan dicatat di log edit presensi. Status Tepat Waktu/Terlambat/Lewat Batas dan Pulang Cepat dihitung ulang dari jadwal hari itu.
                Edit presensi milik administrator sendiri harus disetujui kepala sekolah.
            </p>
        </div>

        <div class="flex justify-end gap-2 border-t border-garis bg-krem/50 px-6 py-4">
            <button type="button" data-tutup-modal class="rounded-xl border border-garis bg-white px-5 py-2.5 text-sm font-semibold">Batal</button>
            <button type="submit" class="rounded-xl bg-merah px-5 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">Simpan perubahan</button>
        </div>
    </form>
</dialog>
@endsection

@push('script')
<script>
    const modal = document.getElementById('modal-edit');
    const form = document.getElementById('form-edit');
    const kotakJam = document.getElementById('kotak-jam');

    // Jam hanya relevan untuk status Hadir
    function aturJam() {
        const hadir = form.elements['status_harian'].value === 'Hadir';
        kotakJam.classList.toggle('opacity-40', !hadir);
        kotakJam.querySelectorAll('input').forEach((i) => {
            i.disabled = !hadir;
            if (!hadir) i.value = '';
        });
    }
    form.elements['status_harian'].addEventListener('change', aturJam);

    function buka(data, isiUlang = true) {
        document.getElementById('sub-edit').textContent = `${data.nama} · ${data.keterangan}`;
        document.getElementById('tercatat-datang').textContent = data.tercatat_datang;
        document.getElementById('tercatat-pulang').textContent = data.tercatat_pulang;
        form.elements['user_id'].value = data.user_id;

        if (isiUlang) {
            form.elements['status_harian'].value = data.status_harian;
            form.elements['jam_datang'].value = data.jam_datang;
            form.elements['jam_pulang'].value = data.jam_pulang;
            form.elements['alasan'].value = '';
            modal.querySelectorAll('[data-galat]').forEach((el) => el.remove());
        }
        aturJam();
        modal.showModal();
    }

    document.querySelectorAll('[data-edit]').forEach((tombol) =>
        tombol.addEventListener('click', () => buka(JSON.parse(tombol.dataset.presensi))));

    document.querySelectorAll('[data-tutup-modal]').forEach((tombol) =>
        tombol.addEventListener('click', () => tombol.closest('dialog').close()));

    // Buka kembali popup jika validasi gagal (isian lama dipertahankan)
    if (@json(session('modal')) === 'edit') {
        const tombolAwal = document.querySelector(`[data-edit][data-id="${@json(old('user_id'))}"]`);
        if (tombolAwal) buka(JSON.parse(tombolAwal.dataset.presensi), false);
    }
</script>
@endpush