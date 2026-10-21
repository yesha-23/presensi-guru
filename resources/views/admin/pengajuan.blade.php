@extends('layouts.app')

@section('konten')
@php
    use App\Http\Controllers\Admin\PengajuanController as PC;

    $kelasKotak = 'flex h-10 min-w-0 items-center gap-2 rounded-xl border border-garis bg-white px-2.5 text-sm focus-within:border-navy md:px-3';
    $kelasIsi = 'h-full w-full min-w-0 border-0 bg-transparent p-0 text-sm outline-none focus:ring-0';
    $kelasIkon = 'hidden h-4 w-4 shrink-0 text-gray-400 md:block';
    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-navy';
    $rentang = fn ($p) => $p->tanggal_mulai->equalTo($p->tanggal_selesai)
        ? $p->tanggal_mulai->translatedFormat('j M Y')
        : $p->tanggal_mulai->translatedFormat('j M').' – '.$p->tanggal_selesai->translatedFormat('j M Y');
@endphp

<h1 class="text-2xl font-extrabold lg:text-3xl">Izin, Sakit & Lembur</h1>
<p class="text-sm text-gray-500">Semua pengajuan pegawai · pengajuan izin/sakit yang sudah diproses dapat dikoreksi dengan alasan</p>

{{-- Tab --}}
<div class="mt-5 grid grid-cols-2 gap-2 md:flex">
    @foreach (['izin' => 'Izin & Sakit', 'lembur' => 'Lembur'] as $kunci => $teks)
        <a href="{{ route('admin.pengajuan', ['tab' => $kunci]) }}"
           class="rounded-full border px-4 py-2 text-center text-sm font-semibold {{ $tab === $kunci ? 'border-gray-900 bg-gray-900 text-white' : 'border-garis bg-white text-gray-700 hover:bg-gray-50' }}">
            {{ $teks }}
        </a>
    @endforeach
</div>

{{-- Filter: Cari · Bulan · Unit · Status · Reset
     Laptop: 1 baris. HP: baris 1 = Cari + Reset, baris 2 = Bulan, Unit, Status --}}
<form method="GET" class="mt-4 grid grid-cols-6 gap-2 rounded-2xl border border-garis bg-white p-3 md:flex md:items-center">
    <input type="hidden" name="tab" value="{{ $tab }}">

    {{-- 1. Cari --}}
    <label class="{{ $kelasKotak }} order-1 col-span-5 md:order-none md:flex-1">
        <i data-lucide="search" class="h-4 w-4 shrink-0 text-gray-400"></i>
        <span class="sr-only">Cari pegawai</span>
        <input name="cari" value="{{ $filter['cari'] ?? '' }}" placeholder="Cari nama atau NIP, tekan Enter" class="{{ $kelasIsi }}">
    </label>

    {{-- 2. Bulan --}}
    <label class="{{ $kelasKotak }} order-3 col-span-2 md:order-none md:w-44 md:shrink-0">
        <i data-lucide="calendar" class="{{ $kelasIkon }}"></i>
        <span class="sr-only">Bulan</span>
        <select name="bulan" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
            <option value="">Semua bulan</option>
            @foreach ($pilihanBulan as $b)
                <option value="{{ $b->format('Y-m') }}" @selected(($filter['bulan'] ?? '') === $b->format('Y-m'))>{{ $b->translatedFormat('F Y') }}</option>
            @endforeach
        </select>
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
    <label class="{{ $kelasKotak }} order-3 col-span-2 md:order-none md:w-44 md:shrink-0">
        <i data-lucide="list-filter" class="{{ $kelasIkon }}"></i>
        <span class="sr-only">Status</span>
        <select name="status" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
            <option value="">Semua status</option>
            @foreach (['Menunggu', 'Disetujui', 'Ditolak'] as $s)
                <option @selected(($filter['status'] ?? '') === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </label>

    {{-- 5. Reset (HP: di samping Cari) --}}
    <a href="{{ route('admin.pengajuan', ['tab' => $tab]) }}" aria-label="Reset filter" title="Reset filter"
       class="order-2 col-span-1 inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-garis text-sm font-semibold hover:bg-gray-50 md:order-none md:shrink-0 md:px-4">
        <i data-lucide="rotate-ccw" class="h-4 w-4"></i><span class="hidden md:inline">Reset</span>
    </a>
</form>

{{-- Tabel --}}
<div class="mt-4 overflow-hidden rounded-2xl border border-garis bg-white">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[56rem] text-sm">
            <thead class="bg-krem text-left text-gray-600">
                <tr>
                    <th class="px-4 py-3 font-semibold">Pegawai</th>
                    <th class="px-4 py-3 font-semibold">{{ $tab === 'izin' ? 'Jenis' : 'Jam' }}</th>
                    <th class="px-4 py-3 font-semibold">Tanggal</th>
                    <th class="px-4 py-3 font-semibold">Diajukan</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Diproses oleh</th>
                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @forelse ($data as $d)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold">{{ $d->user?->nama_lengkap }}</div>
                            <div class="text-xs text-gray-500">{{ $d->user?->unit?->label() }}</div>
                        </td>
                        @if ($tab === 'izin')
                            <td class="px-4 py-3 font-semibold text-cyan-800">{{ $d->jenis }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $rentang($d) }}</td>
                        @else
                            <td class="whitespace-nowrap px-4 py-3">
                                {{ substr($d->jam_mulai, 0, 5) }}–{{ substr($d->jam_selesai, 0, 5) }}
                                @if ($d->durasi_disetujui_menit !== null)
                                    <div class="text-xs text-emerald-700">diakui {{ \App\Services\LemburService::formatMenit($d->durasi_disetujui_menit) }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $d->tanggal->translatedFormat('j M Y') }}</td>
                        @endif
                        <td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ $d->created_at->translatedFormat('j M Y') }}</td>
                        <td class="px-4 py-3">
                            <x-lencana :status="$d->status" />
                            @if ($d->status === 'Ditolak' && $d->alasan_penolakan)
                                <div class="mt-1 max-w-xs text-xs text-red-700">{{ $d->alasan_penolakan }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $d->penyetuju?->nama_lengkap ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                @if ($tab === 'izin' && $d->bukti_path)
                                    <a href="{{ route('pengajuan.bukti', $d) }}" target="_blank" class="rounded-lg border border-garis p-2 hover:bg-gray-50" title="Lihat bukti">
                                        <i data-lucide="paperclip" class="h-4 w-4"></i>
                                    </a>
                                @endif
                                @if ($tab === 'izin')
                                    @php
                                        $bisaKoreksi = in_array($d->status, ['Disetujui', 'Ditolak'], true) && $d->user_id !== auth()->id();
                                        $dataJs = [
                                            'id' => $d->id, 'nama' => $d->user?->nama_lengkap, 'jenis' => $d->jenis,
                                            'tanggal_mulai' => $d->tanggal_mulai->toDateString(),
                                            'tanggal_selesai' => $d->tanggal_selesai->toDateString(),
                                            'status' => $d->status, 'alasan_penolakan' => $d->alasan_penolakan,
                                            'url' => route('admin.pengajuan.izin.update', $d),
                                        ];
                                    @endphp
                                    <button type="button" data-edit data-id="{{ $d->id }}" data-pengajuan='@json($dataJs)' @disabled(! $bisaKoreksi)
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-garis px-3 py-1.5 text-sm font-semibold hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-30"
                                            title="{{ $bisaKoreksi ? 'Koreksi pengajuan' : ($d->status === 'Menunggu' ? 'Masih menunggu penyetuju' : 'Pengajuan milik Anda sendiri') }}">
                                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Koreksi
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">Tidak ada pengajuan pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.paginasi', ['paginator' => $data, 'satuan' => 'pengajuan'])
</div>

{{-- Log koreksi pengajuan --}}
@if ($tab === 'izin')
    <div class="mt-6 overflow-hidden rounded-2xl border border-garis bg-white">
        <div class="flex items-center justify-between border-b border-garis px-4 py-3">
            <h2 class="font-bold">Log koreksi pengajuan</h2>
            <span class="text-sm text-gray-500">{{ $log->count() }} perubahan terbaru</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-sm">
                <thead class="bg-krem text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Waktu</th>
                        <th class="px-4 py-2.5 font-semibold">Oleh</th>
                        <th class="px-4 py-2.5 font-semibold">Pegawai</th>
                        <th class="px-4 py-2.5 font-semibold">Perubahan</th>
                        <th class="px-4 py-2.5 font-semibold">Alasan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-garis">
                    @forelse ($log as $l)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-2.5">{{ $l->created_at->translatedFormat('j M Y H.i') }}</td>
                            <td class="px-4 py-2.5">{{ $l->pengubah?->nama_lengkap }}</td>
                            <td class="px-4 py-2.5 font-semibold">{{ $l->target?->user?->nama_lengkap }}</td>
                            <td class="px-4 py-2.5">{{ PC::ringkasan($l->nilai_lama, $l->nilai_baru) }}</td>
                            <td class="px-4 py-2.5 text-gray-600">{{ $l->alasan }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada koreksi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Popup koreksi --}}
    <dialog id="modal-koreksi" class="m-auto max-h-[92vh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-3xl p-0 backdrop:bg-gray-900/60">
        <form id="form-koreksi" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="_id" value="{{ old('_id') }}">

            <div class="flex items-start justify-between border-b border-garis px-6 py-4">
                <div>
                    <h2 class="text-xl font-extrabold">Koreksi pengajuan</h2>
                    <p id="sub-koreksi" class="text-sm text-gray-500"></p>
                </div>
                <button type="button" data-tutup-modal class="rounded-lg border border-garis p-2"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>

            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                <label class="block text-sm font-semibold">Jenis
                    <select name="jenis" class="{{ $kelasInput }}">
                        <option @selected(old('jenis') === 'Izin')>Izin</option>
                        <option @selected(old('jenis') === 'Sakit')>Sakit</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold">Status
                    <select name="status" class="{{ $kelasInput }}">
                        <option @selected(old('status') === 'Disetujui')>Disetujui</option>
                        <option @selected(old('status') === 'Ditolak')>Ditolak</option>
                    </select>
                </label>
                <label class="block text-sm font-semibold">Tanggal mulai
                    <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" class="{{ $kelasInput }}">
                    @error('tanggal_mulai') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm font-semibold">Tanggal selesai
                    <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" class="{{ $kelasInput }}">
                    @error('tanggal_selesai') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                </label>
                <label id="kotak-alasan-tolak" class="block text-sm font-semibold sm:col-span-2">Alasan penolakan
                    <textarea name="alasan_penolakan" rows="2" maxlength="500" class="{{ $kelasInput }}">{{ old('alasan_penolakan') }}</textarea>
                    @error('alasan_penolakan') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                </label>
                <label class="block text-sm font-semibold sm:col-span-2">Alasan koreksi <span class="text-merah">(wajib)</span>
                    <textarea name="alasan_edit" rows="3" maxlength="500" class="{{ $kelasInput }}"
                              placeholder="Contoh: tanggal salah input, seharusnya 2 hari">{{ old('alasan_edit') }}</textarea>
                    @error('alasan_edit') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                </label>
                <p class="rounded-xl bg-krem p-3 text-xs text-gray-600 sm:col-span-2">
                    Perubahan dicatat di log. Status presensi pada tanggal lama dan baru dihitung ulang otomatis, dan pegawai mendapat notifikasi.
                </p>
            </div>

            <div class="flex justify-end gap-2 border-t border-garis bg-krem/50 px-6 py-4">
                <button type="button" data-tutup-modal class="rounded-xl border border-garis bg-white px-5 py-2.5 text-sm font-semibold">Batal</button>
                <button type="submit" class="rounded-xl bg-merah px-5 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">Simpan koreksi</button>
            </div>
        </form>
    </dialog>
@endif
@endsection

@if ($tab === 'izin')
    @push('script')
    <script>
        const modal = document.getElementById('modal-koreksi');
        const form = document.getElementById('form-koreksi');
        const kotakTolak = document.getElementById('kotak-alasan-tolak');

        const aturTolak = () => kotakTolak.classList.toggle('hidden', form.elements['status'].value !== 'Ditolak');
        form.elements['status'].addEventListener('change', aturTolak);

        function buka(data, isiUlang = true) {
            form.action = data.url;
            form.elements['_id'].value = data.id;
            document.getElementById('sub-koreksi').textContent = data.nama;
            if (isiUlang) {
                form.elements['jenis'].value = data.jenis;
                form.elements['status'].value = data.status;
                form.elements['tanggal_mulai'].value = data.tanggal_mulai;
                form.elements['tanggal_selesai'].value = data.tanggal_selesai;
                form.elements['alasan_penolakan'].value = data.alasan_penolakan ?? '';
                form.elements['alasan_edit'].value = '';
                modal.querySelectorAll('[data-galat]').forEach((el) => el.remove());
            }
            aturTolak();
            modal.showModal();
        }

        document.querySelectorAll('[data-edit]').forEach((t) => t.addEventListener('click', () => buka(JSON.parse(t.dataset.pengajuan))));
        document.querySelectorAll('[data-tutup-modal]').forEach((t) => t.addEventListener('click', () => t.closest('dialog').close()));

        if (@json(session('modal')) === 'edit') {
            const tombolAwal = document.querySelector(`[data-edit][data-id="${@json(old('_id'))}"]`);
            if (tombolAwal) buka(JSON.parse(tombolAwal.dataset.pengajuan), false);
        }
    </script>
    @endpush
@endif