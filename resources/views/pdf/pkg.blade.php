<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan PKG</title>
    <style>
        @page { margin: 1.2cm 1.2cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.5pt; color: #111; }
        h1 { font-size: 11pt; margin: 0; }
        .sub { margin-top: 2px; }
        .catatan { color: #555; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #555; padding: 3px 4px; }
        table.data th { background: #efe8f8; text-align: center; }
        .tengah { text-align: center; }
        .kanan { text-align: right; }
        .tebal { font-weight: bold; }
        .rendah { color: #b45309; font-weight: bold; }
    </style>
</head>
<body>
    @php
        use App\Models\Presensi;
        $angka = fn ($n) => number_format($n, 1, ',', '.');
        $efektif = $unit ? ($efektifUnit[$unit->id] ?? 0) : null;
    @endphp

    <h1>LAPORAN PRESENSI GURU UNTUK PENILAIAN KINERJA GURU (PKG)</h1>
    <div class="sub">
        {{ $unit?->nama ?? 'Semua unit' }} · {{ $label }}
        @if ($efektif !== null) · {{ $efektif }} hari efektif @endif
        · data s.d. {{ $akhir->lt($mulai) ? '–' : $akhir->translatedFormat('j F Y') }}
    </div>
    <div class="catatan">Bahan pendukung penilaian kinerja guru, bukan nilai akhir PKG.</div>

    {{-- Perbandingan antarunit (bila semua unit) --}}
    @if ($perUnit->isNotEmpty())
        <table class="data" style="width: 60%;">
            <thead>
                <tr><th>Unit</th><th>Jumlah guru</th><th>Rata-rata kehadiran</th><th>Rata-rata alpa</th></tr>
            </thead>
            <tbody>
                @foreach ($perUnit as $namaUnit => $u)
                    <tr>
                        <td>{{ $namaUnit }}</td>
                        <td class="tengah">{{ $u['jumlah'] }}</td>
                        <td class="tengah tebal">{{ $angka($u['persen']) }}%</td>
                        <td class="tengah">{{ $angka($u['persen_alpa']) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th>Nama guru</th>
                @if (! $unit) <th style="width: 9%;">Unit</th> @endif
                <th style="width: 7%;">Hari efektif</th>
                <th style="width: 6%;">Hadir</th>
                <th style="width: 7%;">Tugas khusus</th>
                <th style="width: 5%;">Izin</th>
                <th style="width: 5%;">Sakit</th>
                <th style="width: 5%;">Alpa</th>
                <th style="width: 8%;">Terlambat (kali)</th>
                <th style="width: 9%;">Total terlambat</th>
                <th style="width: 8%;">Kehadiran</th>
                <th style="width: 6%;">Alpa (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris->values() as $i => $r)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td>{{ $r['user']->nama_lengkap }}</td>
                    @if (! $unit) <td>{{ $r['user']->unit?->label() }}</td> @endif
                    <td class="tengah">{{ $r['efektif'] }}</td>
                    <td class="tengah">{{ $r['hadir'] }}</td>
                    <td class="tengah">{{ $r['tugas'] }}</td>
                    <td class="tengah">{{ $r['izin'] }}</td>
                    <td class="tengah">{{ $r['sakit'] }}</td>
                    <td class="tengah tebal">{{ $r['alpa'] }}</td>
                    <td class="tengah">{{ $r['terlambat'] }}</td>
                    <td class="tengah">{{ $r['detik_terlambat'] ? Presensi::formatDetik($r['detik_terlambat']) : '-' }}</td>
                    <td class="kanan {{ $r['persen'] >= 95 ? 'tebal' : 'rendah' }}">{{ $angka($r['persen']) }}%</td>
                    <td class="kanan">{{ $angka($r['persen_alpa']) }}%</td>
                </tr>
            @empty
                <tr><td colspan="{{ $unit ? 12 : 13 }}" class="tengah">Tidak ada data guru pada periode ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="catatan" style="margin-top: 6px;">
        Kehadiran = (Hadir + Tugas khusus) ÷ Hari efektif × 100%. Alpa (%) = Alpa ÷ Hari efektif × 100%.
        Kehadiran di bawah 95% dicetak berwarna oranye.
    </div>

    <table style="width: 100%; margin-top: 18px; text-align: center; page-break-inside: avoid;">
        <tr>
            <td style="width: 50%;">Mengetahui,<br>Ketua Yayasan</td>
            <td style="width: 50%;">{{ $kota }}, {{ now()->translatedFormat('j F Y') }}<br>Kepala Sekolah</td>
        </tr>
        <tr><td style="height: 50px;"></td><td></td></tr>
        <tr>
            <td><b>{{ $penandatangan['ketua'] ?? '..............................' }}</b></td>
            <td><b>{{ $unit ? ($penandatangan['kepsek'] ?? '..............................') : '..............................' }}</b></td>
        </tr>
    </table>
</body>
</html>