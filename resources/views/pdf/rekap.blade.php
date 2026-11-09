<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Presensi</title>
    <style>
        @page { margin: 1.2cm 1.2cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #111; }
        h1 { font-size: 12pt; margin: 0; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th, table.data td { border: 1px solid #555; padding: 3px 4px; }
        table.data th { background: #efe8f8; text-align: center; }
        .tengah { text-align: center; }
        .manual { background: #d9d9d9; }
        .kelompok { background: #f6e1e1; font-weight: bold; }
    </style>
</head>
<body>
    @php $R = \App\Services\RekapService::class; @endphp
    <h1>{{ $format['judul'] }} · {{ $unit ? $format['subjudul'] : 'SEMUA UNIT' }}</h1>
    <div>Bulan/periode: {{ $label }} · data s.d. {{ $akhir->translatedFormat('j F Y') }}</div>

    <table class="data">
        <thead>
            <tr>
                <th>No</th><th>Nama</th>
                @foreach ($format['kolom'] as [$judulKolom, $satuan, $isiKolom])
                    <th class="{{ $isiKolom ? '' : 'manual' }}">{{ $judulKolom }}<br>{{ $satuan }}</th>
                @endforeach
                <th>KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            @foreach (['Guru' => 'A. GURU', 'Staf' => 'B. STAF'] as $kel => $judulKel)
                @php $isi = $baris->where('kelompok', $kel)->values(); @endphp
                @if ($isi->isNotEmpty())
                    <tr><td colspan="{{ count($format['kolom']) + 3 }}" class="kelompok">{{ $judulKel }}</td></tr>
                    @foreach ($isi as $i => $r)
                        <tr>
                            <td class="tengah">{{ $i + 1 }}</td>
                            <td>{{ $r['user']->nama_lengkap }}</td>
                            @foreach ($format['kolom'] as [$judulKolom, $satuan, $isiKolom])
                                <td class="{{ $isiKolom ? 'tengah' : 'manual' }}">{{ $R::nilaiKolom($r, $isiKolom) }}</td>
                            @endforeach
                            <td>{{ $r['keterangan'] === '—' ? '-' : $r['keterangan'] }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>

    <table style="width: 100%; margin-top: 18px; text-align: center; page-break-inside: avoid;">
        <tr>
            <td style="width: 33%;">{{ $kota }}, {{ now()->translatedFormat('j F Y') }}<br>Kepala Sekolah</td>
            <td style="width: 33%;">Divalidasi,<br>Kabag Adm. Umum</td>
            <td style="width: 33%;">Mengetahui,<br>Ketua Yayasan</td>
        </tr>
        <tr><td style="height: 50px;"></td><td></td><td></td></tr>
        <tr>
            <td><b>{{ $penandatangan['kepsek'] ?? '..............................' }}</b></td>
            <td><b>{{ $penandatangan['kabag'] ?? '..............................' }}</b></td>
            <td><b>{{ $penandatangan['ketua'] ?? '..............................' }}</b></td>
        </tr>
    </table>
</body>
</html>