{{--
    Tabel kehadiran hari ini (Pantau Kehadiran KS dan dashboard Admin).
    $idTabel : id tabel untuk kotak cari (bawaan "tabel-kehadiran").
    Area gulir sendiri: judul kolom tetap di atas, kolom Nama tetap di kiri saat digeser di HP.
--}}
@php
    $idTabel = $idTabel ?? 'tabel-kehadiran';
    $th = 'sticky top-0 z-20 bg-krem px-4 py-3 font-semibold';
@endphp

<div class="max-h-[70vh] overflow-auto">
    <table id="{{ $idTabel }}" class="w-full min-w-[40rem] text-sm">
        <thead class="text-left text-gray-600">
            <tr>
                <th class="sticky left-0 top-0 z-30 min-w-[11rem] bg-krem px-4 py-3 font-semibold shadow-[1px_0_0_0_#e2dcef]">Nama</th>
                <th class="{{ $th }}">Unit</th>
                <th class="{{ $th }}">Datang</th>
                <th class="{{ $th }}">Jarak</th>
                <th class="{{ $th }}">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-garis">
            @forelse ($daftar as $r)
                @php $p = $r['presensi']; @endphp
                <tr data-nama="{{ mb_strtolower($r['user']->nama_lengkap.' '.$r['user']->nama_panggilan) }}">
                    <td class="sticky left-0 z-10 min-w-[11rem] bg-white px-4 py-3 shadow-[1px_0_0_0_#e2dcef]">
                        <div class="font-semibold">{{ $r['user']->nama_lengkap }}</div>
                        <div class="text-xs text-gray-500">{{ $r['user']->kategori }}{{ $r['user']->jabatan ? ' · '.$r['user']->jabatan : '' }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $r['user']->unit->label() }}</td>
                    <td class="px-4 py-3 font-semibold tabular-nums">{{ $p?->jam_datang ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $p?->jarak_datang !== null ? round($p->jarak_datang).' m' : '—' }}</td>
                    <td class="px-4 py-3">
                        <x-lencana :status="$r['status']"
                                   :teks="$r['status'] === 'Terlambat' ? 'Terlambat '.\App\Models\Presensi::formatDetik($p->detik_terlambat) : null" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">Tidak ada data.</td></tr>
            @endforelse

            @if ($daftar->isNotEmpty())
                <tr data-cari-kosong hidden>
                    <td colspan="5" class="px-4 py-8 text-gray-500">
                        <span class="sticky left-4">Nama pegawai tidak ditemukan.</span>
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>