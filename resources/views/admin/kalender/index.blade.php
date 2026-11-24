@extends('layouts.app')

@section('konten')
@php
    $HKT = \App\Models\KalenderEvent::HARI_KERJA_TAMBAHAN;
    $LIBUR = \App\Models\KalenderEvent::KATEGORI_LIBUR;

    $warnaEntri = function ($e) use ($HKT, $LIBUR) {
        if ($e->berlaku_untuk === 'Siswa') return 'bg-gray-100 text-gray-600';
        if ($e->kategori === $HKT) return 'bg-yellow-100 text-yellow-900';
        if (in_array($e->kategori, $LIBUR, true)) return 'bg-red-100 text-red-800';
        return 'bg-blue-50 text-blue-800';
    };
    $keterangan = function ($e) use ($HKT) {
        if ($e->kategori === $HKT) return 'Hari kerja tambahan · '.str_replace(':', '.', substr($e->jam_masuk, 0, 5)).'–'.str_replace(':', '.', substr($e->jam_pulang, 0, 5));
        if ($e->berlaku_untuk === 'Siswa') return $e->kategori.' · khusus siswa, guru & staf tetap masuk';
        if ($e->kategori === 'Kegiatan Sekolah') return 'Kegiatan sekolah · tetap masuk';
        return $e->kategori.' · guru & staf libur';
    };
    $rentang = fn ($e) => $e->tanggal_mulai->equalTo($e->tanggal_selesai)
        ? $e->tanggal_mulai->format('j')
        : $e->tanggal_mulai->format('j').'–'.$e->tanggal_selesai->format('j');

    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-navy disabled:bg-gray-50';
    $kelasLabel = 'block text-sm font-semibold';
    $urlBulan = fn ($b) => route('admin.kalender', ['unit' => $unit->id, 'bulan' => $b->format('Y-m')]);
    $tanggalAwal = today()->isSameMonth($bulan) ? today()->toDateString() : $bulan->toDateString();
@endphp

{{-- ================= JUDUL ================= --}}
<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-extrabold lg:text-3xl">Kalender Akademik</h1>
        <p class="text-sm text-gray-500">
            @if ($semesterAktif)
                {{ $semesterAktif->label() }}:
                {{ $semesterAktif->tanggal_mulai->translatedFormat('j F') }} – {{ $semesterAktif->tanggal_selesai->translatedFormat('j F Y') }}
            @else
                Bulan ini di luar periode semester yang tercatat
            @endif
        </p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('admin.kalender.impor', ['unit' => $unit->id]) }}"
           class="inline-flex items-center gap-2 rounded-xl border border-garis bg-white px-4 py-2.5 text-sm font-semibold hover:bg-gray-50">
            <i data-lucide="upload" class="h-4 w-4"></i> Impor Excel
        </a>
        <button type="button" data-tambah
                class="inline-flex items-center gap-2 rounded-xl bg-merah px-4 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">
            <i data-lucide="plus" class="h-4 w-4"></i> Tambah entri
        </button>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_22rem]">
    {{-- ================= KALENDER ================= --}}
    <div class="rounded-3xl border border-garis bg-white p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <a href="{{ $urlBulan($bulan->copy()->subMonth()) }}" class="rounded-xl border border-garis p-2 hover:bg-gray-50" aria-label="Bulan sebelumnya">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                </a>
                <h2 class="min-w-40 text-center text-lg font-extrabold">{{ $bulan->translatedFormat('F Y') }}</h2>
                <a href="{{ $urlBulan($bulan->copy()->addMonth()) }}" class="rounded-xl border border-garis p-2 hover:bg-gray-50" aria-label="Bulan berikutnya">
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                </a>
            </div>
            <div class="flex rounded-xl bg-krem p-1">
                @foreach ($units as $u)
                    <a href="{{ route('admin.kalender', ['unit' => $u->id, 'bulan' => $bulan->format('Y-m')]) }}"
                       class="rounded-lg px-4 py-1.5 text-sm font-semibold {{ $u->id === $unit->id ? 'bg-white text-merah shadow-sm' : 'text-gray-600' }}">
                        {{ $u->label() }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="mt-4 overflow-x-auto">
            <div class="min-w-[40rem]">
                <div class="grid grid-cols-7 gap-1.5 px-1 text-xs font-semibold text-gray-500">
                    @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $namaHari)
                        <div>{{ $namaHari }}</div>
                    @endforeach
                </div>

                <div class="mt-1 grid grid-cols-7 gap-1.5">
                    @foreach ($hari as $h)
                        @php
                            $latar = ! $h['bulanIni'] ? 'bg-white opacity-40'
                                : match ($h['jenis']) {
                                    'nonkerja' => 'bg-[#ece9e3]',
                                    'libur'    => 'bg-red-50',
                                    default    => 'bg-white hover:border-navy',
                                };
                        @endphp
                        <div @if ($h['bulanIni']) data-tanggal="{{ $h['tanggal']->toDateString() }}" @endif
                             class="min-h-24 rounded-xl border border-garis p-1.5 {{ $latar }} {{ $h['bulanIni'] ? 'cursor-pointer' : '' }}"
                             title="{{ $h['bulanIni'] ? 'Klik untuk menambah entri pada tanggal ini' : '' }}">
                            @if ($h['tanggal']->isToday())
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-merah text-xs font-bold text-white">{{ $h['tanggal']->day }}</span>
                            @else
                                <span class="text-sm font-semibold">{{ $h['tanggal']->day }}</span>
                            @endif

                            @if ($h['bulanIni'])
                                @foreach ($h['entri'] as $e)
                                    <div class="mt-1 truncate rounded px-1 py-0.5 text-[11px] font-semibold leading-tight {{ $warnaEntri($e) }}" title="{{ $e->nama }}">
                                        {{ $e->nama }}
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-red-100"></span> Libur (guru & staf)</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-yellow-100"></span> Hari kerja tambahan</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-blue-50 ring-1 ring-blue-100"></span> Kegiatan sekolah (tetap masuk)</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-gray-100"></span> Khusus siswa</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-[#ece9e3]"></span> Hari nonkerja</span>
        </div>
    </div>

    {{-- ================= PANEL KANAN ================= --}}
    <div class="space-y-4">
        {{-- Hari efektif --}}
        <div class="rounded-3xl bg-merah p-5 text-white">
            <div class="text-sm text-white/80">Hari efektif guru & staf · {{ $unit->label() }}</div>
            <div class="mt-1 text-4xl font-extrabold">{{ $efektif['total'] }} hari</div>
            <div class="mt-1 text-sm text-white/80">
                {{ $efektif['kerja'] + $efektif['libur'] }} hari {{ $labelHariKerja }}
                @if ($efektif['libur']) − {{ $efektif['libur'] }} libur @endif
                @if ($efektif['hkt']) + {{ $efektif['hkt'] }} hari kerja tambahan @endif
            </div>
        </div>

        {{-- Entri bulan ini --}}
        <div class="rounded-3xl border border-garis bg-white p-5">
            <h2 class="font-bold">Entri bulan ini</h2>

            <div class="mt-3 space-y-3">
                @forelse ($entri as $e)
                    @php
                        $dataJs = [
                            'id' => $e->id, 'nama' => $e->nama, 'kategori' => $e->kategori,
                            'berlaku_untuk' => $e->berlaku_untuk, 'unit_id' => $e->unit_id,
                            'tanggal_mulai' => $e->tanggal_mulai->toDateString(),
                            'tanggal_selesai' => $e->tanggal_selesai->toDateString(),
                            'jam_masuk' => $e->jam_masuk ? substr($e->jam_masuk, 0, 5) : null,
                            'jam_pulang' => $e->jam_pulang ? substr($e->jam_pulang, 0, 5) : null,
                            'url_update' => route('admin.kalender.update', $e),
                        ];
                    @endphp
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 min-w-10 shrink-0 items-center justify-center rounded-lg px-1.5 text-xs font-bold {{ $warnaEntri($e) }}">
                            {{ $rentang($e) }}
                        </span>
                        <div class="min-w-0 flex-1 leading-tight">
                            <div class="font-semibold">{{ $e->nama }}</div>
                            <div class="text-xs text-gray-500">{{ $keterangan($e) }}</div>
                            <div class="text-xs text-gray-400">{{ $e->unit_id ? $units->firstWhere('id', $e->unit_id)?->label() ?? 'Yayasan' : 'Semua unit' }}</div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" data-edit data-id="{{ $e->id }}" data-entri='@json($dataJs)'
                                    class="rounded-lg border border-garis p-1.5 hover:bg-gray-50" title="Ubah">
                                <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.kalender.destroy', $e) }}"
                                  onsubmit="return confirm('Hapus entri &quot;{{ addslashes($e->nama) }}&quot;? Tanggal yang sudah lewat akan dihitung ulang.')">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="_unit" value="{{ $unit->id }}">
                                <button class="rounded-lg border border-red-200 p-1.5 text-merah hover:bg-merah-muda" title="Hapus">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Belum ada entri pada bulan ini.</p>
                @endforelse
            </div>
        </div>

        {{-- Semester --}}
        <div class="rounded-3xl border border-garis bg-white p-5">
            <div class="flex items-center justify-between">
                <h2 class="font-bold">Periode semester · {{ $unit->label() }}</h2>
                <button type="button" data-semester='@json(null)' class="text-sm font-semibold text-merah">+ Tambah</button>
            </div>
            <div class="mt-3 space-y-2">
                @forelse ($semesters as $s)
                    <button type="button"
                            data-semester="{{ json_encode(['tahun_ajaran' => $s->tahun_ajaran, 'jenis' => $s->jenis, 'tanggal_mulai' => $s->tanggal_mulai->toDateString(), 'tanggal_selesai' => $s->tanggal_selesai->toDateString()]) }}"
                            class="flex w-full items-center justify-between rounded-xl bg-krem px-3 py-2 text-left text-sm hover:bg-gray-100">
                        <span><b>{{ $s->label() }}</b></span>
                        <span class="text-gray-500">{{ $s->tanggal_mulai->translatedFormat('j M Y') }} – {{ $s->tanggal_selesai->translatedFormat('j M Y') }}</span>
                    </button>
                @empty
                    <p class="text-sm text-gray-500">Belum ada semester.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-2xl bg-yellow-50 p-4 text-sm text-yellow-900">
            Sabtu rapat ditandai sebagai hari kerja tambahan setelah konfirmasi kepala sekolah, paling lambat H-1.
        </div>
    </div>
</div>

{{-- ================= POPUP ENTRI ================= --}}
<dialog id="modal-entri" class="m-auto max-h-[92vh] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-3xl p-0 backdrop:bg-gray-900/60">
    <form id="form-entri" method="POST" action="{{ route('admin.kalender.store') }}"
          data-url-tambah="{{ route('admin.kalender.store') }}"
          data-unit="{{ $unit->id }}"
          data-tanggal-awal="{{ $tanggalAwal }}">
        @csrf
        <input type="hidden" name="_method" value="POST">
        <input type="hidden" name="_id" value="{{ old('_id') }}">
        <input type="hidden" name="_unit" value="{{ $unit->id }}">

        <div class="flex items-start justify-between border-b border-garis px-6 py-4">
            <div>
                <h2 id="judul-entri" class="text-xl font-extrabold">Tambah entri kalender</h2>
                <p class="text-sm text-gray-500">{{ $unit->label() }} · {{ $semesterAktif ? 'Tahun ajaran '.$semesterAktif->tahun_ajaran : $bulan->translatedFormat('F Y') }}</p>
            </div>
            <button type="button" data-tutup-modal class="rounded-lg border border-garis p-2"><i data-lucide="x" class="h-4 w-4"></i></button>
        </div>

        <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
            <label class="{{ $kelasLabel }} sm:col-span-2">Nama entri
                <input name="nama" value="{{ old('nama') }}" placeholder="Contoh: Rapat Sabtu" class="{{ $kelasInput }}">
                @error('nama') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <label class="{{ $kelasLabel }}">Kategori
                <select name="kategori" class="{{ $kelasInput }}">
                    @foreach ($daftarKategori as $k)
                        <option @selected(old('kategori') === $k)>{{ $k }}</option>
                    @endforeach
                </select>
                @error('kategori') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <label class="{{ $kelasLabel }}">Berlaku untuk
                <select name="berlaku_untuk" class="{{ $kelasInput }}">
                    @foreach ($daftarBerlaku as $b)
                        <option @selected(old('berlaku_untuk') === $b)>{{ $b }}</option>
                    @endforeach
                </select>
                @error('berlaku_untuk') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <label class="{{ $kelasLabel }}">Tanggal mulai
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" class="{{ $kelasInput }}">
                @error('tanggal_mulai') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <label class="{{ $kelasLabel }}">Tanggal selesai
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" class="{{ $kelasInput }}">
                @error('tanggal_selesai') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <label class="{{ $kelasLabel }} sm:col-span-2">Unit
                <select name="unit_id" class="{{ $kelasInput }}">
                    <option value="">Semua unit</option>
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}" @selected(old('unit_id') == $u->id)>{{ $u->label() }}</option>
                    @endforeach
                </select>
                @error('unit_id') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
            </label>

            <div id="kotak-jam" class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
                <label class="{{ $kelasLabel }}">Jam masuk
                    <input type="time" name="jam_masuk" value="{{ old('jam_masuk') }}" class="{{ $kelasInput }}">
                    @error('jam_masuk') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                </label>
                <label class="{{ $kelasLabel }}">Jam pulang
                    <input type="time" name="jam_pulang" value="{{ old('jam_pulang') }}" class="{{ $kelasInput }}">
                    @error('jam_pulang') <span data-galat class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                </label>
            </div>

            <p class="rounded-xl bg-yellow-50 p-3 text-xs text-yellow-900 sm:col-span-2">
                Jam masuk dan pulang hanya diisi untuk Hari Kerja Tambahan. Libur yang berlaku untuk siswa saja tidak mengubah kewajiban presensi guru dan staf.
            </p>
        </div>

        <div class="flex justify-end gap-2 border-t border-garis bg-krem/50 px-6 py-4">
            <button type="button" data-tutup-modal class="rounded-xl border border-garis bg-white px-5 py-2.5 text-sm font-semibold">Batal</button>
            <button type="submit" class="rounded-xl bg-merah px-5 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">Simpan entri</button>
        </div>
    </form>
</dialog>

{{-- ================= POPUP SEMESTER ================= --}}
<dialog id="modal-semester" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl p-0 backdrop:bg-gray-900/60">
    <form id="form-semester" method="POST" action="{{ route('admin.kalender.semester') }}">
        @csrf
        <input type="hidden" name="unit_id" value="{{ $unit->id }}">

        <div class="flex items-start justify-between border-b border-garis px-6 py-4">
            <div>
                <h2 class="text-xl font-extrabold">Periode semester</h2>
                <p class="text-sm text-gray-500">{{ $unit->label() }} · acuan laporan PKG semester</p>
            </div>
            <button type="button" data-tutup-modal class="rounded-lg border border-garis p-2"><i data-lucide="x" class="h-4 w-4"></i></button>
        </div>

        <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
            <label class="{{ $kelasLabel }}">Tahun ajaran
                <input name="tahun_ajaran" value="{{ old('tahun_ajaran') }}" placeholder="2026/2027" class="{{ $kelasInput }}">
            </label>
            <label class="{{ $kelasLabel }}">Semester
                <select name="jenis" class="{{ $kelasInput }}">
                    <option value="Ganjil" @selected(old('jenis') === 'Ganjil')>Semester 1</option>
                    <option value="Genap" @selected(old('jenis') === 'Genap')>Semester 2</option>
                </select>
            </label>
            <label class="{{ $kelasLabel }}">Tanggal mulai
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai') }}" class="{{ $kelasInput }}">
            </label>
            <label class="{{ $kelasLabel }}">Tanggal selesai
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai') }}" class="{{ $kelasInput }}">
            </label>
            @if ($errors->semester->any())
                <p class="text-sm text-merah sm:col-span-2">{{ $errors->semester->first() }}</p>
            @endif
            <p class="text-xs text-gray-500 sm:col-span-2">Jika tahun ajaran dan semester yang sama sudah ada, tanggalnya akan diperbarui.</p>
        </div>

        <div class="flex justify-end gap-2 border-t border-garis bg-krem/50 px-6 py-4">
            <button type="button" data-tutup-modal class="rounded-xl border border-garis bg-white px-5 py-2.5 text-sm font-semibold">Batal</button>
            <button type="submit" class="rounded-xl bg-merah px-5 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua">Simpan semester</button>
        </div>
    </form>
</dialog>
@endsection

@push('script')
<script>
    const HKT = 'Hari Kerja Tambahan';
    const modal = document.getElementById('modal-entri');
    const form = document.getElementById('form-entri');
    const kotakJam = document.getElementById('kotak-jam');
    const modalSemester = document.getElementById('modal-semester');
    const formSemester = document.getElementById('form-semester');

    // Jam masuk/pulang hanya muncul untuk Hari Kerja Tambahan
    function aturJam() {
        const hkt = form.elements['kategori'].value === HKT;
        kotakJam.classList.toggle('hidden', !hkt);
        kotakJam.querySelectorAll('input').forEach((i) => (i.disabled = !hkt));
        if (hkt) form.elements['berlaku_untuk'].value = 'Guru dan Staf';
    }
    form.elements['kategori'].addEventListener('change', aturJam);

    function aturMode(mode, data) {
        const tambah = mode === 'tambah';
        form.action = tambah ? form.dataset.urlTambah : data.url_update;
        form.elements['_method'].value = tambah ? 'POST' : 'PUT';
        form.elements['_id'].value = data?.id ?? '';
        document.getElementById('judul-entri').textContent = tambah ? 'Tambah entri kalender' : 'Ubah entri kalender';
    }

    function isi(data, tanggal = null) {
        const awal = tanggal ?? form.dataset.tanggalAwal;
        form.elements['nama'].value = data?.nama ?? '';
        form.elements['kategori'].value = data?.kategori ?? 'Kegiatan Sekolah';
        form.elements['berlaku_untuk'].value = data?.berlaku_untuk ?? 'Guru dan Staf';
        form.elements['unit_id'].value = data ? (data.unit_id ?? '') : form.dataset.unit;
        form.elements['tanggal_mulai'].value = data?.tanggal_mulai ?? awal;
        form.elements['tanggal_selesai'].value = data?.tanggal_selesai ?? awal;
        form.elements['jam_masuk'].value = data?.jam_masuk ?? '07:00';
        form.elements['jam_pulang'].value = data?.jam_pulang ?? '12:00';
        modal.querySelectorAll('[data-galat]').forEach((el) => el.remove());
        aturJam();
    }

    function bukaTambah(tanggal = null) {
        aturMode('tambah', null);
        isi(null, tanggal);
        modal.showModal();
    }

    document.querySelector('[data-tambah]').addEventListener('click', () => bukaTambah());

    // Klik tanggal di kalender -> tambah entri pada tanggal itu
    document.querySelectorAll('[data-tanggal]').forEach((sel) =>
        sel.addEventListener('click', () => bukaTambah(sel.dataset.tanggal)));

    document.querySelectorAll('[data-edit]').forEach((tombol) =>
        tombol.addEventListener('click', () => {
            const data = JSON.parse(tombol.dataset.entri);
            aturMode('edit', data);
            isi(data);
            modal.showModal();
        }));

    // Semester
    document.querySelectorAll('[data-semester]').forEach((tombol) =>
        tombol.addEventListener('click', () => {
            const data = JSON.parse(tombol.dataset.semester);
            formSemester.elements['tahun_ajaran'].value = data?.tahun_ajaran ?? '';
            formSemester.elements['jenis'].value = data?.jenis ?? 'Ganjil';
            formSemester.elements['tanggal_mulai'].value = data?.tanggal_mulai ?? '';
            formSemester.elements['tanggal_selesai'].value = data?.tanggal_selesai ?? '';
            modalSemester.showModal();
        }));

    document.querySelectorAll('[data-tutup-modal]').forEach((tombol) =>
        tombol.addEventListener('click', () => tombol.closest('dialog').close()));

    // Buka kembali popup jika validasi gagal
    const modeAwal = @json(session('modal'));
    if (modeAwal === 'semester') {
        modalSemester.showModal();
    } else if (modeAwal) {
        const idAwal = @json(old('_id'));
        const tombolAwal = idAwal ? document.querySelector(`[data-edit][data-id="${idAwal}"]`) : null;
        aturMode(tombolAwal ? 'edit' : 'tambah', tombolAwal ? JSON.parse(tombolAwal.dataset.entri) : null);
        aturJam();
        modal.showModal();
    }
</script>
@endpush