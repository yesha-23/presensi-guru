@php
    $modePkg = $modePkg ?? false;

    $kelasKotak = 'flex h-10 min-w-0 items-center gap-2 rounded-xl border border-garis bg-white px-2.5 text-sm focus-within:border-navy md:px-3';
    $kelasIsi = 'h-full w-full min-w-0 border-0 bg-transparent p-0 text-sm outline-none focus:ring-0';
    $kelasIkon = 'hidden h-4 w-4 shrink-0 text-gray-400 md:block';

    // Laptop: di Laporan PKG unit lebih sempit dan semester mengisi sisanya; di Rekap semua berbagi rata.
    $lebarUnit = $modePkg ? 'md:w-56 md:shrink-0' : 'md:flex-1';

    // Pilihan bulan berbahasa Indonesia: bulan depan s.d. 17 bulan lalu (+ bulan terpilih bila di luar daftar).
    // Untuk unit dengan periode 26–25, "bulan" = bulan tempat periode berakhir.
    $bulanTerpilih = ($bulanRekap ?? $mulai)->copy()->startOfMonth();
    $pilihanBulan = collect(range(-1, 17))
        ->map(fn ($i) => today()->startOfMonth()->subMonths($i))
        ->push($bulanTerpilih)
        ->unique(fn ($b) => $b->format('Y-m'))
        ->sortByDesc(fn ($b) => $b->format('Y-m'))
        ->values();
@endphp

{{-- Urutan: Waktu (periode/semester) · Unit · Kelompok · Reset
     HP: grid 6 kolom (baris 1 = waktu, baris 2 = unit, kelompok, reset). Laptop: 1 baris. --}}
<form method="GET" class="mt-4 grid grid-cols-6 gap-2 rounded-2xl border border-garis bg-white p-3 md:flex md:items-center">

    @if ($modePkg)
        {{-- ===== 1. Semester (Laporan PKG) ===== --}}
        <label class="{{ $kelasKotak }} col-span-3 md:flex-1">
            <i data-lucide="calendar-range" class="{{ $kelasIkon }}"></i>
            <span class="sr-only">Semester</span>
            <select name="semester" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
                @forelse ($semesters as $s)
                    <option value="{{ $s->id }}" @selected($semester?->id === $s->id)>
                        {{ $s->label() }}
                        ({{ $s->tanggal_mulai->translatedFormat('j M') }} – {{ $s->tanggal_selesai->translatedFormat('j M') }})
                    </option>
                @empty
                    <option value="">Belum ada semester</option>
                @endforelse
            </select>
        </label>
    @else
        {{-- ===== 1a. Jenis periode ===== --}}
        <label class="{{ $kelasKotak }} col-span-2 md:flex-1">
            <i data-lucide="calendar-range" class="{{ $kelasIkon }}"></i>
            <span class="sr-only">Periode</span>
            <select name="periode" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
                @foreach (['mingguan' => 'Mingguan', 'bulanan' => 'Bulanan', 'semester' => 'Semester', 'rentang' => 'Rentang tanggal'] as $nilai => $teks)
                    <option value="{{ $nilai }}" @selected($jenis === $nilai)>{{ $teks }}</option>
                @endforeach
            </select>
        </label>

        {{-- ===== 1b. Isian sesuai jenis periode ===== --}}
        @if ($jenis === 'mingguan')
            <label class="{{ $kelasKotak }} col-span-4 md:flex-1" title="Pilih tanggal mana pun dalam minggu tersebut">
                <span class="sr-only">Minggu</span>
                <input type="date" name="tanggal" value="{{ $mulai->toDateString() }}" onchange="this.form.submit()" class="{{ $kelasIsi }}">
            </label>
        @elseif ($jenis === 'semester')
            <label class="{{ $kelasKotak }} col-span-4 md:flex-1">
                <span class="sr-only">Semester</span>
                <select name="semester" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
                    @foreach ($semesters as $s)
                        <option value="{{ $s->id }}" @selected($semester?->id === $s->id)>
                            {{ $s->label() }}
                        </option>
                    @endforeach
                </select>
            </label>
        @elseif ($jenis === 'rentang')
            <label class="{{ $kelasKotak }} col-span-2 md:flex-1">
                <span class="sr-only">Tanggal mulai</span>
                <input type="date" name="mulai" value="{{ $mulai->toDateString() }}" onchange="this.form.submit()" class="{{ $kelasIsi }}">
            </label>
            <label class="{{ $kelasKotak }} col-span-2 md:flex-1">
                <span class="sr-only">Tanggal selesai</span>
                <input type="date" name="selesai" value="{{ $selesai->toDateString() }}" onchange="this.form.submit()" class="{{ $kelasIsi }}">
            </label>
        @else
            <label class="{{ $kelasKotak }} col-span-4 md:flex-1">
                <i data-lucide="calendar" class="{{ $kelasIkon }}"></i>
                <span class="sr-only">Bulan</span>
                <select name="bulan" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
                    @foreach ($pilihanBulan as $b)
                        <option value="{{ $b->format('Y-m') }}" @selected($b->format('Y-m') === $bulanTerpilih->format('Y-m'))>
                            {{ $b->translatedFormat('F Y') }}
                        </option>
                    @endforeach
                </select>
            </label>
        @endif
    @endif

    {{-- ===== 2. Unit ===== --}}
    @if ($bisaPilihUnit)
        <label class="{{ $kelasKotak }} col-span-2 {{ $lebarUnit }}">
            <i data-lucide="building-2" class="{{ $kelasIkon }}"></i>
            <span class="sr-only">Unit</span>
            <select name="unit" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
                <option value="">Semua unit</option>
                @foreach ($units as $u)
                    <option value="{{ $u->id }}" @selected($unit?->id === $u->id)>{{ $u->label() }}</option>
                @endforeach
            </select>
        </label>
    @else
        <span class="col-span-2 inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-krem px-3 text-sm font-semibold md:justify-start {{ $lebarUnit }}">
            <i data-lucide="building-2" class="{{ $kelasIkon }}"></i> {{ $unit->label() }}
        </span>
    @endif

    {{-- ===== 3. Kelompok (hanya Rekap) ===== --}}
    @unless ($modePkg)
        <label class="{{ $kelasKotak }} col-span-2 md:flex-1">
            <i data-lucide="users" class="{{ $kelasIkon }}"></i>
            <span class="sr-only">Kelompok</span>
            <select name="kelompok" onchange="this.form.submit()" class="{{ $kelasIsi }} cursor-pointer">
                <option value="">Guru & Staf</option>
                <option value="Guru" @selected($kelompok === 'Guru')>Guru</option>
                <option value="Staf" @selected($kelompok === 'Staf')>Staf</option>
            </select>
        </label>
    @endunless

    {{-- ===== 4. Reset: paling kanan ===== --}}
    <a href="{{ route($modePkg ? 'pkg' : 'rekap') }}" aria-label="Reset filter" title="Reset filter"
       class="{{ $modePkg ? 'col-span-1' : 'col-span-2' }} inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-garis text-sm font-semibold hover:bg-gray-50 md:shrink-0 md:px-4">
        <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
        <span class="{{ $modePkg ? 'hidden md:inline' : '' }}">Reset</span>
    </a>
</form>