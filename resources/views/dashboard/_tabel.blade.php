<div class="overflow-x-auto">
    <table class="w-full min-w-[40rem] text-sm">
        <thead class="bg-krem text-left text-gray-600">
            <tr>
                <th class="px-4 py-3 font-semibold">Nama</th>
                <th class="px-4 py-3 font-semibold">Unit</th>
                <th class="px-4 py-3 font-semibold">Datang</th>
                <th class="px-4 py-3 font-semibold">Jarak</th>
                <th class="px-4 py-3 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-garis">
            @forelse ($daftar as $r)
                @php $p = $r['presensi']; @endphp
                <tr>
                    <td class="px-4 py-3">
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
        </tbody>
    </table>
</div>