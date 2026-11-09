@extends('layouts.app')

@section('konten')
@php
    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-2.5 text-sm font-normal outline-none focus:border-navy';
    $kelasLabel = 'block text-sm font-semibold';
    $kelasBantu = 'mt-1 block text-xs font-normal text-gray-500';
    $kelasJam = 'w-full min-w-0 rounded-lg border border-garis bg-white px-2 py-2 text-sm tabular-nums outline-none focus:border-navy';
    $kelasKartu = 'overflow-hidden rounded-2xl border border-garis bg-white';
    $kelasKepala = 'border-b border-garis px-5 py-4';
    $kelasKaki = 'flex justify-end border-t border-garis bg-krem/40 px-5 py-4';
    $tombol = 'rounded-xl bg-merah px-5 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua';
    $hari = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    $tabs = [
        'jam'      => ['Jam kerja', 'clock'],
        'lokasi'   => ['Lokasi', 'map-pin'],
        'presensi' => ['Presensi & wajah', 'scan-face'],
        'penyetuju' => ['Penyetuju', 'user-check'],
        'rekap'    => ['Rekap', 'table'],
        'kop'      => ['Kop surat', 'file-text'],
    ];

    // Jika validasi gagal, buka tab yang berisi isian bermasalah
    $tabGalat = null;
    foreach (array_keys($errors->getMessages()) as $kunci) {
        $tabGalat = match (true) {
            str_starts_with($kunci, 'jam.') => 'jam',
            in_array($kunci, ['nama', 'latitude', 'longitude', 'radius_meter'], true) => 'lokasi',
            in_array($kunci, ['alamat_yayasan', 'kota_surat', 'telepon_surat', 'web_surat'], true) => 'kop',
            str_starts_with($kunci, 'penyetuju_ks') => 'penyetuju',
            str_starts_with($kunci, 'awal_periode_rekap') || $kunci === 'pembulatan_jam_rekap' || str_starts_with($kunci, 'yayasan') => 'rekap',
            default => 'presensi',
        };
        break;
    }
    // Galat form jam kerja khusus (error bag terpisah) -> buka tab Jam kerja
    if ($errors->jamKhusus->any()) {
        $tabGalat = 'jam';
    }
    $tabAwal = $tabGalat ?? 'jam';

    $tabAktif = 'border-gray-900 bg-gray-900 text-white';
    $tabBiasa = 'border-garis bg-white text-gray-700 hover:bg-gray-50';

    // Jam kerja dikelompokkan per unit: SD, KB & TK, Yayasan
    $perUnit = $jamKerja->groupBy(fn ($j) => $j->unit->label());
@endphp

<h1 class="text-2xl font-extrabold lg:text-3xl">Pengaturan</h1>
<p class="text-sm text-gray-500">Perubahan berlaku untuk presensi berikutnya; data yang sudah tercatat tidak berubah.</p>

@if ($errors->any())
    <div class="mt-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
@endif

{{-- ================= TAB: HP 2×2, laptop 1 baris ================= --}}
<div role="tablist" class="mt-5 grid grid-cols-2 gap-2 md:flex md:flex-wrap">
    @foreach ($tabs as $kunci => [$teks, $ikon])
        <button type="button" role="tab" data-tab="{{ $kunci }}" aria-selected="{{ $tabAwal === $kunci ? 'true' : 'false' }}"
                class="inline-flex items-center justify-center gap-2 truncate rounded-full border px-3 py-2 text-sm font-semibold md:px-4 {{ $tabAwal === $kunci ? $tabAktif : $tabBiasa }}">
            <i data-lucide="{{ $ikon }}" class="h-4 w-4 shrink-0"></i> {{ $teks }}
        </button>
    @endforeach
</div>

{{-- ================= 1. JAM KERJA ================= --}}
@php
    // Kolom: Kategori (sisa ruang) · Jam masuk · Jam pulang · Toleransi
    $kolomJam = 'sm:grid-cols-[minmax(5rem,1fr)_8.5rem_8.5rem_5.5rem]';
@endphp
<div data-panel="jam" class="mt-5 {{ $tabAwal === 'jam' ? '' : 'hidden' }}">
    <form method="POST" action="{{ route('admin.pengaturan.jam-kerja') }}" class="{{ $kelasKartu }}">
        @csrf @method('PUT')
        <div class="{{ $kelasKepala }}">
            <h2 class="font-bold">Jam kerja</h2>
            <p class="text-xs text-gray-500">
                Status Terlambat dicatat jika menit datang melewati jam masuk + toleransi (toleransi 0: 07.00 lewat beberapa detik masih tepat waktu, 07.01 terlambat).
                Lama terlambat dihitung dari jam masuk. Datang setelah batas presensi datang diatur di tab Presensi &amp; wajah.
                Jam Sabtu rapat diatur per tanggal di Kalender Akademik; pegawai dengan jam berbeda diatur di kartu Jam kerja khusus di bawah.
            </p>
        </div>

        <div class="grid items-start gap-4 p-5 min-[1400px]:grid-cols-2">
            @foreach ($perUnit as $namaUnit => $daftar)
                <div class="overflow-hidden rounded-2xl border border-garis">
                    <div class="flex items-center justify-between bg-krem px-4 py-2.5">
                        <h3 class="font-bold">{{ $namaUnit }}</h3>
                        <span class="text-xs text-gray-500">{{ $daftar->count() }} kategori</span>
                    </div>

                    {{-- Judul kolom (laptop) --}}
                    <div class="hidden gap-3 px-4 pt-3 text-xs font-semibold text-gray-500 sm:grid {{ $kolomJam }}">
                        <span>Kategori</span>
                        <span>Jam masuk</span>
                        <span>Jam pulang</span>
                        <span>Toleransi (menit)</span>
                    </div>

                    <div class="divide-y divide-garis">
                        @foreach ($daftar as $j)
                            <div class="grid grid-cols-3 items-end gap-2 px-4 py-3 sm:items-center sm:gap-3 sm:py-2.5 {{ $kolomJam }}">
                                <div class="col-span-3 text-sm font-semibold sm:col-span-1">{{ $j->kategori }}</div>

                                <label class="min-w-0">
                                    <span class="mb-1 block text-xs text-gray-500 sm:sr-only">Jam masuk</span>
                                    <input type="time" name="jam[{{ $j->id }}][jam_masuk]"
                                           value="{{ old("jam.{$j->id}.jam_masuk", substr($j->jam_masuk, 0, 5)) }}"
                                           class="{{ $kelasJam }}">
                                </label>

                                <label class="min-w-0">
                                    <span class="mb-1 block text-xs text-gray-500 sm:sr-only">Jam pulang</span>
                                    <input type="time" name="jam[{{ $j->id }}][jam_pulang]"
                                           value="{{ old("jam.{$j->id}.jam_pulang", substr($j->jam_pulang, 0, 5)) }}"
                                           class="{{ $kelasJam }}">
                                </label>

                                <label class="min-w-0">
                                    <span class="mb-1 block text-xs text-gray-500 sm:sr-only">Toleransi (menit)</span>
                                    <input type="number" min="0" max="120" inputmode="numeric"
                                           name="jam[{{ $j->id }}][toleransi_menit]"
                                           value="{{ old("jam.{$j->id}.toleransi_menit", $j->toleransi_menit) }}"
                                           class="{{ $kelasJam }} text-center">
                                </label>

                                @error("jam.{$j->id}.jam_pulang")
                                    <p class="col-span-3 text-xs text-merah sm:col-span-4">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="{{ $kelasKaki }}"><button class="{{ $tombol }}">Simpan jam kerja</button></div>
    </form>

    {{-- ---------- Jam kerja khusus per pegawai ---------- --}}
    @php $galatKhusus = $errors->jamKhusus; @endphp
    <div id="jam-khusus" class="mt-5 {{ $kelasKartu }}">
        <div class="{{ $kelasKepala }}">
            <h2 class="font-bold">Jam kerja khusus pegawai</h2>
            <p class="text-xs text-gray-500">
                Untuk pegawai yang jam masuk/pulangnya berbeda dari unit &amp; kategorinya (misalnya masuk 08.00 dan pulang lebih sore).
                Toleransi tetap mengikuti unit &amp; kategori. Pada Hari Kerja Tambahan yang punya jam sendiri, jam kalender yang dipakai.
            </p>
        </div>

        {{-- Daftar --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="bg-krem text-left text-gray-600">
                    <tr>
                        <th class="px-5 py-2.5 font-semibold">Pegawai</th>
                        <th class="px-5 py-2.5 font-semibold">Jam kerja</th>
                        <th class="px-5 py-2.5 font-semibold">Keterangan</th>
                        <th class="px-5 py-2.5 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-garis">
                    @forelse ($jamKhusus as $jk)
                        <tr>
                            <td class="px-5 py-3">
                                <div class="font-semibold">{{ $jk->user?->nama_lengkap ?? '(pegawai dihapus)' }}</div>
                                <div class="text-xs text-gray-500">{{ $jk->user?->kategori }} · {{ $jk->user?->unit?->label() }}</div>
                            </td>
                            <td class="px-5 py-3 tabular-nums">{{ substr($jk->jam_masuk, 0, 5) }} – {{ substr($jk->jam_pulang, 0, 5) }}</td>
                            <td class="px-5 py-3 text-gray-600">{{ $jk->keterangan ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @php
                                    $dataUbah = [
                                        'user_id'    => $jk->user_id,
                                        'jam_masuk'  => substr($jk->jam_masuk, 0, 5),
                                        'jam_pulang' => substr($jk->jam_pulang, 0, 5),
                                        'keterangan' => $jk->keterangan,
                                    ];
                                    $pesanHapus = 'Hapus jam kerja khusus '.($jk->user?->nama_lengkap ?? 'pegawai ini')
                                        .'? Pegawai kembali memakai jam unit dan kategorinya.';
                                @endphp
                                <div class="flex justify-end gap-2">
                                    <button type="button" title="Ubah"
                                            data-ubah-khusus="{{ json_encode($dataUbah) }}"
                                            class="rounded-lg border border-garis p-2 hover:bg-gray-50">
                                        <i data-lucide="pencil" class="h-4 w-4"></i>
                                    </button>
                                    <form method="POST" action="{{ route('admin.pengaturan.jam-khusus.hapus', $jk) }}"
                                          data-konfirmasi="{{ $pesanHapus }}"
                                          onsubmit="return confirm(this.dataset.konfirmasi)">
                                        @csrf @method('DELETE')
                                        <button title="Hapus" class="rounded-lg border border-red-200 p-2 text-merah hover:bg-merah-muda">
                                            <i data-lucide="trash-2" class="h-4 w-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-6 text-center text-gray-500">Belum ada pegawai dengan jam kerja khusus.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Form tambah / ubah --}}
        <form method="POST" action="{{ route('admin.pengaturan.jam-khusus') }}" id="form-jam-khusus" class="border-t border-garis">
            @csrf
            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.6fr)_8.5rem_8.5rem_minmax(0,1.4fr)]">
                <label class="{{ $kelasLabel }}">Pegawai
                    <select name="user_id" class="{{ $kelasInput }}">
                        <option value="">Pilih pegawai</option>
                        @foreach ($pilihanPegawai as $pg)
                            <option value="{{ $pg->id }}" @selected(old('user_id') == $pg->id)>
                                {{ $pg->nama_lengkap }} · {{ $pg->kategori }} {{ $pg->unit?->label() }}
                            </option>
                        @endforeach
                    </select>
                    @if ($galatKhusus->has('user_id')) <span class="mt-1 block text-xs font-normal text-merah">{{ $galatKhusus->first('user_id') }}</span> @endif
                </label>
                <label class="{{ $kelasLabel }}">Jam masuk
                    <input type="time" name="jam_masuk" value="{{ old('jam_masuk', '08:00') }}" class="{{ $kelasInput }} tabular-nums">
                    @if ($galatKhusus->has('jam_masuk')) <span class="mt-1 block text-xs font-normal text-merah">{{ $galatKhusus->first('jam_masuk') }}</span> @endif
                </label>
                <label class="{{ $kelasLabel }}">Jam pulang
                    <input type="time" name="jam_pulang" value="{{ old('jam_pulang') }}" class="{{ $kelasInput }} tabular-nums">
                    @if ($galatKhusus->has('jam_pulang')) <span class="mt-1 block text-xs font-normal text-merah">{{ $galatKhusus->first('jam_pulang') }}</span> @endif
                </label>
                <label class="{{ $kelasLabel }}">Keterangan
                    <input name="keterangan" value="{{ old('keterangan') }}" maxlength="150" placeholder="Contoh: jaga penjemputan siswa" class="{{ $kelasInput }}">
                    @if ($galatKhusus->has('keterangan')) <span class="mt-1 block text-xs font-normal text-merah">{{ $galatKhusus->first('keterangan') }}</span> @endif
                </label>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-garis bg-krem/40 px-5 py-4">
                <span class="text-xs text-gray-500">Memilih pegawai yang sudah ada di daftar akan memperbarui jamnya.</span>
                <button class="{{ $tombol }}">Simpan jam khusus</button>
            </div>
        </form>
    </div>
</div>

{{-- ================= 2. LOKASI ================= --}}
<div data-panel="lokasi" class="mt-5 {{ $tabAwal === 'lokasi' ? '' : 'hidden' }}">
    <form method="POST" action="{{ route('admin.pengaturan.lokasi') }}" id="form-lokasi" class="{{ $kelasKartu }}">
        @csrf @method('PUT')
        <div class="{{ $kelasKepala }}">
            <h2 class="font-bold">Lokasi presensi</h2>
            <p class="text-xs text-gray-500">Satu titik di tengah kompleks agar gedung SD dan KB & TK tercakup radius.</p>
        </div>

        <div class="grid gap-5 p-5 lg:grid-cols-2">
            {{-- Peta --}}
            <div>
                @if ($lokasi)
                    @php
                        $lat = $lokasi->latitude;
                        $lng = $lokasi->longitude;
                        $bbox = ($lng - 0.004).','.($lat - 0.003).','.($lng + 0.004).','.($lat + 0.003);
                    @endphp
                    <iframe class="h-64 w-full rounded-xl border border-garis lg:h-full lg:min-h-[18rem]" loading="lazy" title="Peta lokasi presensi"
                            src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ $lat }},{{ $lng }}"></iframe>
                @else
                    <div class="flex h-64 items-center justify-center rounded-xl border border-dashed border-garis bg-krem text-sm text-gray-500">
                        Lokasi belum diatur
                    </div>
                @endif
            </div>

            {{-- Isian --}}
            <div class="grid content-start gap-4 sm:grid-cols-2">
                <label class="{{ $kelasLabel }} sm:col-span-2">Nama lokasi
                    <input name="nama" value="{{ old('nama', $lokasi?->nama ?? 'Kompleks YPK Teruna Bangsa') }}" class="{{ $kelasInput }}">
                </label>
                <label class="{{ $kelasLabel }}">Latitude
                    <input name="latitude" value="{{ old('latitude', $lokasi?->latitude) }}" class="{{ $kelasInput }} tabular-nums">
                </label>
                <label class="{{ $kelasLabel }}">Longitude
                    <input name="longitude" value="{{ old('longitude', $lokasi?->longitude) }}" class="{{ $kelasInput }} tabular-nums">
                </label>
                <label class="{{ $kelasLabel }}">Radius (meter)
                    <input type="number" min="20" max="1000" name="radius_meter" value="{{ old('radius_meter', $lokasi?->radius_meter ?? 150) }}" class="{{ $kelasInput }}">
                    <span class="{{ $kelasBantu }}">20–1000 m dari titik tengah</span>
                </label>
                <div class="flex flex-col justify-start">
                    <span class="{{ $kelasLabel }} invisible hidden sm:block">Aksi</span>
                    <button type="button" id="tombol-lokasi-saya"
                            class="mt-1 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-garis py-2.5 text-sm font-semibold hover:bg-gray-50">
                        <i data-lucide="locate-fixed" class="h-4 w-4"></i> Gunakan lokasi saya
                    </button>
                </div>
                <p id="info-lokasi" class="rounded-xl bg-krem p-3 text-xs text-gray-600 sm:col-span-2">
                    Saat berdiri di tengah kompleks, tekan <b>Gunakan lokasi saya</b>, lalu <b>Simpan lokasi</b>.
                </p>
            </div>
        </div>

        <div class="{{ $kelasKaki }}"><button class="{{ $tombol }}">Simpan lokasi</button></div>
    </form>
</div>

{{-- ================= 3. PRESENSI & VERIFIKASI ================= --}}
<div data-panel="presensi" class="mt-5 {{ $tabAwal === 'presensi' ? '' : 'hidden' }}">
    <form method="POST" action="{{ route('admin.pengaturan.presensi') }}" class="{{ $kelasKartu }}">
        @csrf @method('PUT')
        <div class="{{ $kelasKepala }}">
            <h2 class="font-bold">Presensi, verifikasi wajah & pengingat</h2>
            <p class="text-xs text-gray-500">Aturan pemeriksaan saat pegawai melakukan presensi.</p>
        </div>

        <div class="grid gap-6 p-5 lg:grid-cols-2">
            {{-- Verifikasi --}}
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">Verifikasi</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <label class="{{ $kelasLabel }}">Ambang kecocokan wajah
                        <input type="number" step="0.01" min="0.3" max="0.7" name="ambang_wajah"
                               value="{{ old('ambang_wajah', $nilai['ambang_wajah']) }}" class="{{ $kelasInput }}">
                        <span class="{{ $kelasBantu }}">0,30–0,70. Makin kecil makin ketat.</span>
                    </label>
                    <label class="{{ $kelasLabel }}">Akurasi GPS maksimal (meter)
                        <input type="number" min="10" max="1000" name="akurasi_gps_maks"
                               value="{{ old('akurasi_gps_maks', $nilai['akurasi_gps_maks']) }}" class="{{ $kelasInput }}">
                        <span class="{{ $kelasBantu }}">GPS yang melenceng lebih dari ini ditolak.</span>
                    </label>
                </div>
            </div>

            {{-- Waktu --}}
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">Waktu & pengingat</h3>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <label class="{{ $kelasLabel }}">Pengingat presensi datang
                        <input type="time" name="jam_pengingat_datang"
                               value="{{ old('jam_pengingat_datang', $nilai['jam_pengingat_datang']) }}" class="{{ $kelasInput }}">
                        <span class="{{ $kelasBantu }}">Pengingat pulang dikirim saat jam pulang.</span>
                    </label>
                    <label class="{{ $kelasLabel }}">Batas presensi datang
                        <input type="time" name="batas_presensi_datang"
                               value="{{ old('batas_presensi_datang', $nilai['batas_presensi_datang']) }}" class="{{ $kelasInput }}">
                        <span class="{{ $kelasBantu }}">Setelah jam pulang, presensi datang selalu ditolak.</span>
                    </label>
                    <div class="sm:col-span-2">
                        <span class="{{ $kelasLabel }}">Presensi datang setelah batas</span>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ([
                                \App\Models\Presensi::MODE_CATAT => ['Catat sebagai Lewat Batas', 'Kehadiran tetap tercatat (dihitung masuk dan terlambat) dengan label Lewat Batas.'],
                                \App\Models\Presensi::MODE_TOLAK => ['Tolak presensi', 'Presensi datang ditutup; tanpa izin, sakit, atau tugas, hari itu menjadi Alpa.'],
                            ] as $mode => [$judulMode, $teksMode])
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-garis p-3 has-[:checked]:border-merah has-[:checked]:bg-merah-muda">
                                    <input type="radio" name="mode_lewat_batas" value="{{ $mode }}" class="mt-0.5 h-4 w-4 accent-merah"
                                           @checked(old('mode_lewat_batas', $nilai['mode_lewat_batas']) === $mode)>
                                    <span>
                                        <span class="block text-sm font-semibold">{{ $judulMode }}</span>
                                        <span class="block text-xs font-normal text-gray-500">{{ $teksMode }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('mode_lewat_batas') <span class="mt-1 block text-xs text-merah">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Hari kerja --}}
            <div class="lg:col-span-2">
                <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">Hari kerja</h3>
                <div class="mt-3 grid grid-cols-7 gap-2 sm:flex sm:flex-wrap">
                    @foreach ($hari as $n => $teks)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="hari_kerja[]" value="{{ $n }}" class="peer sr-only"
                                   @checked(in_array($n, old('hari_kerja', $nilai['hari_kerja'])))>
                            <span class="block rounded-lg border border-garis py-2 text-center text-sm font-semibold text-gray-500 peer-checked:border-merah peer-checked:bg-merah-muda peer-checked:text-merah sm:px-4">{{ $teks }}</span>
                        </label>
                    @endforeach
                </div>
                <span class="{{ $kelasBantu }}">Sabtu rapat tidak perlu dicentang; tandai sebagai Hari Kerja Tambahan di kalender.</span>
            </div>
        </div>

        <div class="{{ $kelasKaki }}"><button class="{{ $tombol }}">Simpan pengaturan presensi</button></div>
    </form>
</div>

{{-- ================= 4. PENYETUJU PENGAJUAN KEPALA SEKOLAH ================= --}}
@php
    // null = belum pernah diatur: semua administrator masih menjadi penyetuju (perilaku lama)
    $terpilih = array_map('intval', (array) old('penyetuju_ks', $idPenyetujuKs ?? $daftarAdmin->pluck('id')->all()));
@endphp
<div data-panel="penyetuju" class="mt-5 {{ $tabAwal === 'penyetuju' ? '' : 'hidden' }}">
    <form method="POST" action="{{ route('admin.pengaturan.penyetuju') }}" class="{{ $kelasKartu }}">
        @csrf @method('PUT')
        <div class="{{ $kelasKepala }}">
            <h2 class="font-bold">Penyetuju pengajuan kepala sekolah</h2>
            <p class="text-xs text-gray-500">
                Izin, sakit, dan lembur milik kepala sekolah disetujui <b>Ketua Yayasan</b> atau salah satu administrator yang dicentang di bawah
                (misalnya Kabag Adm. Umum). Administrator yang tidak dicentang tetap mengelola data, tetapi tidak menyetujui pengajuan kepala sekolah.
                Administrator pertama yang dicentang juga dipakai sebagai penanda tangan Kabag Adm. Umum pada rekap.
            </p>
            @if ($idPenyetujuKs === null)
                <p class="mt-2 rounded-lg bg-yellow-50 px-3 py-2 text-xs text-yellow-900">
                    Belum pernah diatur: saat ini semua administrator dapat menyetujui. Simpan sekali untuk menetapkannya.
                </p>
            @endif
        </div>

        <div class="grid gap-2 p-5 sm:grid-cols-2">
            @forelse ($daftarAdmin as $adm)
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-garis p-3 has-[:checked]:border-merah has-[:checked]:bg-merah-muda">
                    <input type="checkbox" name="penyetuju_ks[]" value="{{ $adm->id }}" class="mt-0.5 h-4 w-4 accent-merah"
                           @checked(in_array($adm->id, $terpilih, true))>
                    <span>
                        <span class="block text-sm font-semibold">{{ $adm->nama_lengkap }}</span>
                        <span class="block text-xs text-gray-500">{{ $adm->jabatan ?? 'Administrator' }} · {{ $adm->unit?->label() }}</span>
                    </span>
                </label>
            @empty
                <p class="text-sm text-gray-500">Belum ada pegawai aktif dengan peran Administrator.</p>
            @endforelse
            @error('penyetuju_ks.*') <p class="text-xs text-merah sm:col-span-2">{{ $message }}</p> @enderror
        </div>

        <div class="{{ $kelasKaki }}"><button class="{{ $tombol }}">Simpan penyetuju</button></div>
    </form>
</div>

{{-- ================= 5. REKAP ================= --}}
@php
    $pilihanBulat = [
        \App\Services\RekapService::BULAT_SETENGAH      => ['Ke 0,5 jam terdekat', 'Contoh: 1 jam 10 menit → 1; 1 jam 20 menit → 1,5.'],
        \App\Services\RekapService::BULAT_SETENGAH_ATAS => ['Ke atas, kelipatan 0,5 jam', 'Contoh: 10 menit → 0,5; 1 jam 10 menit → 1,5.'],
        \App\Services\RekapService::BULAT_DESIMAL       => ['1 angka desimal', 'Contoh: 1 jam 10 menit → 1,2.'],
    ];
    $bulatTerpilih = old('pembulatan_jam_rekap', $nilai['pembulatan_jam_rekap']);
@endphp
<div data-panel="rekap" class="mt-5 {{ $tabAwal === 'rekap' ? '' : 'hidden' }}">
    <form method="POST" action="{{ route('admin.pengaturan.rekap') }}" class="{{ $kelasKartu }}">
        @csrf @method('PUT')
        <div class="{{ $kelasKepala }}">
            <h2 class="font-bold">Rekap bulanan</h2>
            <p class="text-xs text-gray-500">Periode bulanan, pembulatan jam terlambat & lembur, serta pegawai Yayasan yang ikut dicantumkan di rekap unit.</p>
        </div>

        <div class="space-y-6 p-5">
            {{-- Periode bulanan --}}
            <div>
                <div class="text-sm font-bold">Tanggal awal periode bulanan</div>
                <p class="text-xs text-gray-500">Isi 1 untuk periode kalender (1 s.d. akhir bulan). Isi 26 untuk periode 26 bulan lalu s.d. 25 (rekap "November" = 26 Oktober – 25 November).</p>
                <div class="mt-2 grid gap-4 sm:grid-cols-2">
                    @foreach (['SD' => 'SD', 'KBTK' => 'KB & TK'] as $kode => $namaUnit)
                        <label class="{{ $kelasLabel }}">{{ $namaUnit }}
                            <input type="number" min="1" max="28" name="awal_periode_rekap_{{ $kode }}"
                                   value="{{ old('awal_periode_rekap_'.$kode, $nilai['awal_periode_rekap_'.$kode]) }}" class="{{ $kelasInput }}">
                            @error('awal_periode_rekap_'.$kode) <span class="mt-1 block text-xs font-normal text-merah">{{ $message }}</span> @enderror
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Pembulatan --}}
            <div>
                <div class="text-sm font-bold">Pembulatan jam terlambat & lembur</div>
                <p class="text-xs text-gray-500">Dipakai di kolom TLAT/TLBT dan LEMB/LMBR. Total dibulatkan per pegawai per periode.</p>
                <div class="mt-2 grid gap-2 sm:grid-cols-3">
                    @foreach ($pilihanBulat as $mode => [$judulBulat, $contohBulat])
                        <label class="flex cursor-pointer gap-3 rounded-xl border border-garis p-3 has-[:checked]:border-merah has-[:checked]:bg-merah-muda">
                            <input type="radio" name="pembulatan_jam_rekap" value="{{ $mode }}" class="mt-0.5 h-4 w-4 accent-merah" @checked($bulatTerpilih === $mode)>
                            <span>
                                <span class="block text-sm font-semibold">{{ $judulBulat }}</span>
                                <span class="block text-xs font-normal text-gray-500">{{ $contohBulat }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Pegawai Yayasan di rekap unit --}}
            <div>
                <div class="text-sm font-bold">Pegawai Yayasan di rekap unit</div>
                <p class="text-xs text-gray-500">Pegawai unit Yayasan (mis. Kabag Adm. Umum) dapat dicantumkan di rekap SD dan/atau KB-TK. Datanya tetap satu; hanya letak namanya di rekap yang berbeda. Tidak memengaruhi Laporan PKG.</p>
                <div class="mt-2 overflow-x-auto rounded-xl border border-garis">
                    <table class="w-full min-w-[32rem] text-sm">
                        <thead class="bg-krem text-left text-gray-600">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Pegawai Yayasan</th>
                                <th class="px-4 py-2.5 font-semibold">Di rekap SD</th>
                                <th class="px-4 py-2.5 font-semibold">Di rekap KB-TK</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-garis">
                            @forelse ($pegawaiYayasan as $py)
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <div class="font-semibold">{{ $py->nama_lengkap }}</div>
                                        <div class="text-xs text-gray-500">{{ $py->jabatan ?? $py->kategori }}</div>
                                    </td>
                                    @foreach (['SD', 'KBTK'] as $kode)
                                        @php $pilih = old("yayasan.{$py->id}.{$kode}", $tampilanYayasan[$py->id][$kode] ?? ''); @endphp
                                        <td class="px-4 py-2.5">
                                            <select name="yayasan[{{ $py->id }}][{{ $kode }}]" class="w-full rounded-lg border border-garis bg-white px-2 py-2 text-sm">
                                                <option value="">Tidak dicantumkan</option>
                                                <option value="Guru" @selected($pilih === 'Guru')>Kelompok A. Guru</option>
                                                <option value="Staf" @selected($pilih === 'Staf')>Kelompok B. Staf</option>
                                            </select>
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-4 text-center text-gray-500">Belum ada pegawai unit Yayasan yang wajib presensi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="{{ $kelasKaki }}"><button class="{{ $tombol }}">Simpan pengaturan rekap</button></div>
    </form>
</div>

{{-- ================= 6. KOP SURAT ================= --}}
<div data-panel="kop" class="mt-5 {{ $tabAwal === 'kop' ? '' : 'hidden' }}">
    <form method="POST" action="{{ route('admin.pengaturan.kop') }}" class="{{ $kelasKartu }}">
        @csrf @method('PUT')
        <div class="{{ $kelasKepala }}">
            <h2 class="font-bold">Kop surat tugas</h2>
            <p class="text-xs text-gray-500">Dipakai pada surat tugas yang disetujui berikutnya.</p>
        </div>

        <div class="grid gap-4 p-5 lg:grid-cols-2">
            <label class="{{ $kelasLabel }}">Alamat yayasan
                <textarea name="alamat_yayasan" rows="5" class="{{ $kelasInput }}">{{ old('alamat_yayasan', $nilai['alamat_yayasan']) }}</textarea>
            </label>
            <div class="grid content-start gap-4 sm:grid-cols-[10rem_minmax(0,1fr)]">
                <label class="{{ $kelasLabel }}">Kota surat
                    <input name="kota_surat" value="{{ old('kota_surat', $nilai['kota_surat']) }}" class="{{ $kelasInput }}">
                </label>
                <label class="{{ $kelasLabel }}">Telepon / WA
                    <input name="telepon_surat" value="{{ old('telepon_surat', $nilai['telepon_surat']) }}" class="{{ $kelasInput }}">
                </label>
                <label class="{{ $kelasLabel }} sm:col-span-2">Website / email
                    <input name="web_surat" value="{{ old('web_surat', $nilai['web_surat']) }}" class="{{ $kelasInput }}">
                </label>
            </div>
        </div>

        <div class="{{ $kelasKaki }}"><button class="{{ $tombol }}">Simpan kop surat</button></div>
    </form>
</div>
@endsection

@push('script')
<script>
    // ================= Tab =================
    const tombolTab = document.querySelectorAll('[data-tab]');
    const panel = document.querySelectorAll('[data-panel]');
    const kelasAktif = @json(explode(' ', $tabAktif));
    const kelasBiasa = @json(explode(' ', $tabBiasa));
    const daftarTab = @json(array_keys($tabs));

    function bukaTab(nama) {
        tombolTab.forEach((t) => {
            const aktif = t.dataset.tab === nama;
            t.setAttribute('aria-selected', aktif ? 'true' : 'false');
            kelasAktif.forEach((k) => t.classList.toggle(k, aktif));
            kelasBiasa.forEach((k) => t.classList.toggle(k, !aktif));
        });
        panel.forEach((p) => p.classList.toggle('hidden', p.dataset.panel !== nama));
        try { sessionStorage.setItem('tab-pengaturan', nama); } catch {}
        history.replaceState(null, '', `#${nama}`);
    }

    tombolTab.forEach((t) => t.addEventListener('click', () => bukaTab(t.dataset.tab)));

    // Urutan pilihan tab awal: tab berisi galat → alamat #tab → tab terakhir dibuka → Jam kerja
    let tabAwal = @json($tabGalat) || location.hash.slice(1);
    if (!daftarTab.includes(tabAwal)) {
        try { tabAwal = sessionStorage.getItem('tab-pengaturan'); } catch { tabAwal = null; }
    }
    bukaTab(daftarTab.includes(tabAwal) ? tabAwal : 'jam');

    // ================= Jam kerja khusus: tombol Ubah mengisi form =================
    const formKhusus = document.getElementById('form-jam-khusus');
    document.querySelectorAll('[data-ubah-khusus]').forEach((tombol) =>
        tombol.addEventListener('click', () => {
            const d = JSON.parse(tombol.dataset.ubahKhusus);
            ['user_id', 'jam_masuk', 'jam_pulang', 'keterangan'].forEach((k) => (formKhusus.elements[k].value = d[k] ?? ''));
            formKhusus.scrollIntoView({ behavior: 'smooth', block: 'center' });
            formKhusus.elements['jam_masuk'].focus();
        })
    );

    // ================= Isi koordinat dari GPS perangkat =================
    document.getElementById('tombol-lokasi-saya').addEventListener('click', () => {
        const form = document.getElementById('form-lokasi');
        const info = document.getElementById('info-lokasi');

        if (!navigator.geolocation) {
            info.textContent = 'Browser tidak mendukung GPS.';
            return;
        }

        info.textContent = 'Mengambil lokasi…';
        navigator.geolocation.getCurrentPosition(
            (p) => {
                form.elements['latitude'].value = p.coords.latitude.toFixed(7);
                form.elements['longitude'].value = p.coords.longitude.toFixed(7);
                info.textContent = `Lokasi terisi (akurasi ±${Math.round(p.coords.accuracy)} m). Tekan Simpan lokasi.`;
            },
            () => (info.textContent = 'Lokasi tidak dapat diambil. Izinkan akses lokasi di browser.'),
            { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }
        );
    });
</script>
@endpush