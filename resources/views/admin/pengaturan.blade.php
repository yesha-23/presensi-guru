@extends('layouts.app')

@section('konten')
@php
    $kelasInput = 'mt-1 w-full rounded-xl border border-garis bg-white px-3 py-2.5 text-sm outline-none focus:border-navy';
    $kelasLabel = 'block text-sm font-semibold';
    $tombol = 'rounded-xl bg-merah px-5 py-2.5 text-sm font-semibold text-white hover:bg-merah-tua';
    $hari = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];
@endphp

<h1 class="text-2xl font-extrabold lg:text-3xl">Pengaturan</h1>
<p class="text-sm text-gray-500">Perubahan berlaku untuk presensi berikutnya; data yang sudah tercatat tidak berubah.</p>

@if ($errors->any())
    <div class="mt-4 rounded-xl bg-red-100 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
@endif

{{-- ================= JAM KERJA ================= --}}
<form method="POST" action="{{ route('admin.pengaturan.jam-kerja') }}" class="mt-5 overflow-hidden rounded-2xl border border-garis bg-white">
    @csrf @method('PUT')
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-garis px-5 py-4">
        <div>
            <h2 class="font-bold">Jam kerja</h2>
            <p class="text-xs text-gray-500">Keterlambatan dihitung dari jam masuk. Jam Sabtu rapat diatur per tanggal di Kalender Akademik.</p>
        </div>
        <button class="{{ $tombol }}">Simpan jam kerja</button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[40rem] text-sm">
            <thead class="bg-krem text-left text-gray-600">
                <tr>
                    <th class="px-5 py-3 font-semibold">Unit</th>
                    <th class="px-5 py-3 font-semibold">Kategori</th>
                    <th class="px-5 py-3 font-semibold">Jam masuk</th>
                    <th class="px-5 py-3 font-semibold">Jam pulang</th>
                    <th class="px-5 py-3 font-semibold">Toleransi (menit)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-garis">
                @foreach ($jamKerja as $j)
                    <tr>
                        <td class="px-5 py-2.5 font-bold">{{ $j->unit->label() }}</td>
                        <td class="px-5 py-2.5">{{ $j->kategori }}</td>
                        <td class="px-5 py-2.5">
                            <input type="time" name="jam[{{ $j->id }}][jam_masuk]" value="{{ old("jam.{$j->id}.jam_masuk", substr($j->jam_masuk, 0, 5)) }}"
                                   class="rounded-lg border border-garis px-2 py-1.5">
                        </td>
                        <td class="px-5 py-2.5">
                            <input type="time" name="jam[{{ $j->id }}][jam_pulang]" value="{{ old("jam.{$j->id}.jam_pulang", substr($j->jam_pulang, 0, 5)) }}"
                                   class="rounded-lg border border-garis px-2 py-1.5">
                            @error("jam.{$j->id}.jam_pulang") <div class="text-xs text-merah">{{ $message }}</div> @enderror
                        </td>
                        <td class="px-5 py-2.5">
                            <input type="number" min="0" max="120" name="jam[{{ $j->id }}][toleransi_menit]"
                                   value="{{ old("jam.{$j->id}.toleransi_menit", $j->toleransi_menit) }}"
                                   class="w-24 rounded-lg border border-garis px-2 py-1.5">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</form>

<div class="mt-5 grid gap-5 xl:grid-cols-2 xl:items-start">
    {{-- ================= LOKASI ================= --}}
    <form method="POST" action="{{ route('admin.pengaturan.lokasi') }}" id="form-lokasi" class="rounded-2xl border border-garis bg-white p-5">
        @csrf @method('PUT')
        <h2 class="font-bold">Lokasi presensi</h2>
        <p class="text-xs text-gray-500">Satu titik di tengah kompleks agar gedung SD dan KB & TK tercakup radius.</p>

        @if ($lokasi)
            @php
                $lat = $lokasi->latitude;
                $lng = $lokasi->longitude;
                $bbox = ($lng - 0.004).','.($lat - 0.003).','.($lng + 0.004).','.($lat + 0.003);
            @endphp
            <iframe class="mt-3 h-56 w-full rounded-xl border border-garis" loading="lazy" title="Peta lokasi presensi"
                    src="https://www.openstreetmap.org/export/embed.html?bbox={{ $bbox }}&layer=mapnik&marker={{ $lat }},{{ $lng }}"></iframe>
        @endif

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="{{ $kelasLabel }} sm:col-span-2">Nama lokasi
                <input name="nama" value="{{ old('nama', $lokasi?->nama ?? 'Kompleks YPK Teruna Bangsa') }}" class="{{ $kelasInput }}">
            </label>
            <label class="{{ $kelasLabel }}">Latitude
                <input name="latitude" value="{{ old('latitude', $lokasi?->latitude) }}" class="{{ $kelasInput }}">
            </label>
            <label class="{{ $kelasLabel }}">Longitude
                <input name="longitude" value="{{ old('longitude', $lokasi?->longitude) }}" class="{{ $kelasInput }}">
            </label>
            <label class="{{ $kelasLabel }}">Radius (meter)
                <input type="number" min="20" max="1000" name="radius_meter" value="{{ old('radius_meter', $lokasi?->radius_meter ?? 150) }}" class="{{ $kelasInput }}">
            </label>
            <div class="flex items-end">
                <button type="button" id="tombol-lokasi-saya"
                        class="w-full rounded-xl border border-garis py-2.5 text-sm font-semibold hover:bg-gray-50">
                    Gunakan lokasi saya sekarang
                </button>
            </div>
        </div>
        <p id="info-lokasi" class="mt-2 text-xs text-gray-500">Saat berdiri di tengah kompleks, tekan tombol di atas lalu Simpan.</p>
        <div class="mt-4 text-right"><button class="{{ $tombol }}">Simpan lokasi</button></div>
    </form>

    <div class="space-y-5">
        {{-- ================= PRESENSI & VERIFIKASI ================= --}}
        <form method="POST" action="{{ route('admin.pengaturan.presensi') }}" class="rounded-2xl border border-garis bg-white p-5">
            @csrf @method('PUT')
            <h2 class="font-bold">Presensi, verifikasi wajah & pengingat</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="{{ $kelasLabel }}">Ambang kecocokan wajah
                    <input type="number" step="0.01" min="0.3" max="0.7" name="ambang_wajah" value="{{ old('ambang_wajah', $nilai['ambang_wajah']) }}" class="{{ $kelasInput }}">
                    <span class="mt-1 block text-xs font-normal text-gray-500">Makin kecil makin ketat. Ditetapkan saat uji lapangan.</span>
                </label>
                <label class="{{ $kelasLabel }}">Akurasi GPS minimal (meter)
                    <input type="number" min="10" max="1000" name="akurasi_gps_maks" value="{{ old('akurasi_gps_maks', $nilai['akurasi_gps_maks']) }}" class="{{ $kelasInput }}">
                </label>
                <label class="{{ $kelasLabel }}">Pengingat presensi datang
                    <input type="time" name="jam_pengingat_datang" value="{{ old('jam_pengingat_datang', $nilai['jam_pengingat_datang']) }}" class="{{ $kelasInput }}">
                    <span class="mt-1 block text-xs font-normal text-gray-500">Pengingat pulang dikirim saat jam pulang.</span>
                </label>
                <label class="{{ $kelasLabel }}">Batas presensi datang
                    <input type="time" name="batas_presensi_datang" value="{{ old('batas_presensi_datang', $nilai['batas_presensi_datang']) }}" class="{{ $kelasInput }}">
                    <span class="mt-1 block text-xs font-normal text-gray-500">Lewat batas ini presensi datang ditolak (Alpa).</span>
                </label>
                <div class="sm:col-span-2">
                    <span class="{{ $kelasLabel }}">Hari kerja</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($hari as $n => $teks)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="hari_kerja[]" value="{{ $n }}" class="peer sr-only"
                                       @checked(in_array($n, old('hari_kerja', $nilai['hari_kerja'])))>
                                <span class="block rounded-lg border border-garis px-3 py-1.5 text-sm font-semibold text-gray-500 peer-checked:border-merah peer-checked:bg-merah-muda peer-checked:text-merah">{{ $teks }}</span>
                            </label>
                        @endforeach
                    </div>
                    <span class="mt-1 block text-xs text-gray-500">Sabtu rapat tidak perlu dicentang; tandai sebagai Hari Kerja Tambahan di kalender.</span>
                </div>
            </div>
            <div class="mt-4 text-right"><button class="{{ $tombol }}">Simpan pengaturan presensi</button></div>
        </form>

        {{-- ================= KOP SURAT ================= --}}
        <form method="POST" action="{{ route('admin.pengaturan.kop') }}" class="rounded-2xl border border-garis bg-white p-5">
            @csrf @method('PUT')
            <h2 class="font-bold">Kop surat tugas</h2>
            <div class="mt-4 grid gap-4">
                <label class="{{ $kelasLabel }}">Alamat yayasan
                    <textarea name="alamat_yayasan" rows="2" class="{{ $kelasInput }}">{{ old('alamat_yayasan', $nilai['alamat_yayasan']) }}</textarea>
                </label>
                <div class="grid gap-4 sm:grid-cols-[10rem_1fr]">
                    <label class="{{ $kelasLabel }}">Kota surat
                        <input name="kota_surat" value="{{ old('kota_surat', $nilai['kota_surat']) }}" class="{{ $kelasInput }}">
                    </label>
                    <label class="{{ $kelasLabel }}">Telepon / WA
                        <input name="telepon_surat" value="{{ old('telepon_surat', $nilai['telepon_surat']) }}" class="{{ $kelasInput }}">
                    </label>
                </div>
                <label class="{{ $kelasLabel }}">Website / email
                    <input name="web_surat" value="{{ old('web_surat', $nilai['web_surat']) }}" class="{{ $kelasInput }}">
                </label>
            </div>
            <div class="mt-4 text-right"><button class="{{ $tombol }}">Simpan kop surat</button></div>
        </form>
    </div>
</div>
@endsection

@push('script')
<script>
    // Isi koordinat dari GPS perangkat (berguna saat uji lapangan di tengah kompleks)
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