{{--
    Daftar pengajuan yang menunggu (tampilan awal setiap tab).
    $baris : koleksi [url, jenis, warna, judul, ket, diajukan (Carbon), penanda (opsional)]
    $kosong: pesan bila tidak ada pengajuan
--}}
@if ($baris->isEmpty())
    @include('persetujuan._kosong', ['pesan' => $kosong])
@else
    <div class="overflow-hidden rounded-2xl border border-garis bg-white">
        <div class="divide-y divide-garis">
            @foreach ($baris as $b)
                <a href="{{ $b['url'] }}" class="group flex items-center gap-4 px-4 py-4 hover:bg-krem/60 md:px-5">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                            <span class="font-bold uppercase {{ $b['warna'] }}">{{ $b['jenis'] }}</span>
                            @if (! empty($b['penanda']))
                                <span class="rounded-full bg-blue-50 px-2 py-0.5 font-semibold text-blue-800">{{ $b['penanda'] }}</span>
                            @endif
                        </div>
                        <div class="mt-0.5 truncate font-bold">{{ $b['judul'] }}</div>
                        <div class="truncate text-sm text-gray-500">{{ $b['ket'] }}</div>
                    </div>
                    <div class="hidden shrink-0 text-right text-xs text-gray-500 sm:block">
                        Diajukan<br>{{ $b['diajukan']->translatedFormat('j M Y, H.i') }}
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1 rounded-xl border border-garis px-3 py-2 text-sm font-semibold group-hover:border-merah group-hover:text-merah">
                        Tinjau <i data-lucide="chevron-right" class="h-4 w-4"></i>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@endif
