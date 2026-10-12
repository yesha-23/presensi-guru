{{-- Tabel keputusan terakhir. $baris: koleksi [nama, jenis, tanggal, status, waktu, catatan] --}}
@if ($baris->isNotEmpty())
    <div class="mt-6 overflow-hidden rounded-2xl border border-garis bg-white">
        <div class="flex items-center justify-between border-b border-garis px-4 py-3">
            <h2 class="font-bold">Terakhir diproses</h2>
            <span class="text-sm text-gray-500">{{ $baris->count() }} keputusan terakhir Anda</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="bg-krem text-left text-gray-600">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Pegawai</th>
                        <th class="px-4 py-2.5 font-semibold">Jenis</th>
                        <th class="px-4 py-2.5 font-semibold">Tanggal</th>
                        <th class="px-4 py-2.5 font-semibold">Keputusan</th>
                        <th class="px-4 py-2.5 font-semibold">Diproses</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-garis">
                    @foreach ($baris as $b)
                        <tr>
                            <td class="px-4 py-2.5 font-semibold">{{ $b['nama'] }}</td>
                            <td class="px-4 py-2.5">{{ $b['jenis'] }}</td>
                            <td class="px-4 py-2.5">{{ $b['tanggal'] }}</td>
                            <td class="px-4 py-2.5">
                                <x-lencana :status="$b['status']" />
                                @if ($b['status'] === 'Ditolak' && $b['catatan'])
                                    <div class="mt-1 max-w-xs text-xs text-red-700">{{ $b['catatan'] }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-gray-500">{{ $b['waktu']?->translatedFormat('j M Y, H.i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif