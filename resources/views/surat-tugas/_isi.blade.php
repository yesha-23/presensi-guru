@php
    $modePdf = $modePdf ?? false;
    $ungu = '#4b1f7a';
    $unguMuda = '#9b6fd0';

    // Posisi elemen pita: fixed di PDF (berulang tiap halaman), absolute di pratinjau
    $pos = fn (string $pdf, string $layar) => $modePdf ? "position: fixed; {$pdf}" : "position: absolute; {$layar}";

    $hari = fn ($t) => $t->translatedFormat('l, j F Y');
    $rentang = $surat->tanggal_mulai->equalTo($surat->tanggal_selesai)
        ? $hari($surat->tanggal_mulai)
        : $hari($surat->tanggal_mulai).' s.d. '.$hari($surat->tanggal_selesai);

    $tanggalTerbit = $penandatangan->pluck('tanggal')->filter()->max() ?? now();
    $kepsek = $penandatangan->firstWhere('jabatan', 'Kepala Sekolah');
    $ketua = $penandatangan->firstWhere('jabatan', 'Ketua Yayasan');

    $sans = "font-family: 'DejaVu Sans', Arial, sans-serif;";
    $sel = 'border: 1px solid #333; padding: 5px 7px; vertical-align: middle;';
    $th = $sel.' background: #efe8f8; font-weight: bold; text-align: center; font-size: 9pt;';
@endphp

<div style="{{ $modePdf ? '' : 'position: relative; width: 21cm; min-height: 29.7cm; padding: 1.3cm 1.8cm 1.6cm 4.4cm; box-sizing: border-box; background: #fff; margin: 0 auto;' }}
            font-family: 'DejaVu Serif', Georgia, serif; font-size: 10.5pt; color: #111; line-height: 1.45;">

    {{-- ================= PITA UNGU ================= --}}
    <div style="{{ $pos('left: -4.4cm; top: -1.3cm; width: 3.2cm; height: 29.7cm;', 'left: 0; top: 0; width: 3.2cm; height: 100%;') }} background: {{ $ungu }};"></div>
    <div style="{{ $pos('left: -1.35cm; top: -1.3cm; width: 0.15cm; height: 29.7cm;', 'left: 3.05cm; top: 0; width: 0.15cm; height: 100%;') }} background: {{ $unguMuda }};"></div>

    <img src="{{ $logo }}" alt=""
         style="{{ $pos('left: -4.2cm; top: -1.0cm; width: 2.8cm;', 'left: 0.2cm; top: 0.3cm; width: 2.8cm;') }}">

    <div style="{{ $pos('left: -4.1cm; bottom: -1.1cm; width: 2.6cm;', 'left: 0.3cm; bottom: 0.5cm; width: 2.6cm;') }}
                {{ $sans }} color: #fff; font-size: 11pt; font-weight: bold; line-height: 1.2;">
        Bertumbuh.<br>Cerdas.<br>Berkarakter<br>Luhur.
    </div>

    {{-- ================= KOP ================= --}}
    <div style="{{ $sans }} border-bottom: 2.5px solid {{ $ungu }}; padding-bottom: 6px;">
        <div style="font-size: 11pt; font-weight: bold;">YAYASAN PENDIDIKAN KRISTEN TERUNA BANGSA YOGYAKARTA</div>
        <div style="font-size: 15pt; font-weight: bold; color: {{ $ungu }}; margin: 1px 0 4px;">{{ $namaUnit }}</div>
        <div style="font-size: 7.5pt; line-height: 1.35;">{{ $alamat }}</div>
        @if ($kontak['telepon'])
            <div style="font-size: 7.5pt; font-weight: bold;">{{ $kontak['telepon'] }}</div>
        @endif
        @if ($kontak['web'])
            <div style="font-size: 7.5pt; font-weight: bold;">{{ $kontak['web'] }}</div>
        @endif
    </div>
    <div style="border-top: 0.75px solid {{ $ungu }}; margin-top: 2px;"></div>

    {{-- ================= TANGGAL & NOMOR ================= --}}
    <p style="text-align: right; margin: 14px 0 10px;">{{ $kota }}, {{ $tanggalTerbit->translatedFormat('j F Y') }}</p>

    <table style="border-collapse: collapse; margin-bottom: 14px;">
        <tr><td style="width: 1.5cm; padding: 0;">No</td><td style="padding: 0;">: {{ $surat->nomor_surat ?? '(diterbitkan setelah disetujui)' }}</td></tr>
        <tr><td style="padding: 0;">Hal</td><td style="padding: 0;">: <b>Surat Tugas</b></td></tr>
        <tr><td style="padding: 0;">Lamp</td><td style="padding: 0;">: {{ $surat->lampiran_path ? '1 (satu) berkas' : '-' }}</td></tr>
    </table>

    <p style="margin: 0;">Yth. Bapak/Ibu yang namanya tercantum di bawah ini</p>
    <p style="margin: 0 0 12px;">Di tempat</p>

    <p style="margin: 0 0 4px;">Salam Sejahtera,</p>
    <p style="margin: 0 0 8px; text-align: justify;">
        Yang bertanda tangan di bawah ini, Kepala {{ $namaUnitBiasa }}, dengan ini menugaskan:
    </p>

    {{-- ================= PEGAWAI ================= --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <thead>
            <tr>
                <th style="{{ $th }} width: 0.9cm;">NO</th>
                <th style="{{ $th }}">NAMA</th>
                <th style="{{ $th }} width: 3.6cm;">NIP</th>
                <th style="{{ $th }} width: 4.6cm;">JABATAN / UNIT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($surat->pegawai as $i => $p)
                <tr>
                    <td style="{{ $sel }} text-align: center;">{{ $i + 1 }}</td>
                    <td style="{{ $sel }}">{{ $p->nama_lengkap }}</td>
                    <td style="{{ $sel }}">{{ $p->nip ?? '-' }}</td>
                    <td style="{{ $sel }}">{{ $p->jabatan ?? $p->kategori }} · {{ $p->unit?->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin: 0 0 8px;">untuk melaksanakan tugas sebagai berikut:</p>

    {{-- ================= TUGAS ================= --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <thead>
            <tr>
                <th style="{{ $th }} width: 0.9cm;">NO</th>
                <th style="{{ $th }} width: 3.6cm;">HARI/TANGGAL</th>
                <th style="{{ $th }}">KEGIATAN</th>
                <th style="{{ $th }} width: 3.4cm;">TEMPAT</th>
                <th style="{{ $th }} width: 3.6cm;">KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="{{ $sel }} text-align: center;">1</td>
                <td style="{{ $sel }} text-align: center;">{{ $rentang }}</td>
                <td style="{{ $sel }} text-align: center;"><b>{{ $surat->perihal }}</b></td>
                <td style="{{ $sel }} text-align: center;">{{ $surat->lokasi }}</td>
                <td style="{{ $sel }} font-size: 9pt;">
                    -Pemberi tugas: {{ $surat->pemberi_tugas }}<br>
                    -{{ $surat->jenis_kegiatan === 'Dalam' ? 'Kegiatan dalam sekolah' : 'Kegiatan luar sekolah' }}<br>
                    -Sifat: {{ $surat->sifat }}
                </td>
            </tr>
        </tbody>
    </table>

    <p style="margin: 0; text-align: justify;">
        Demikian surat tugas ini dibuat untuk dilaksanakan dengan penuh tanggung jawab. Atas perhatian dan
        kerja samanya kami mengucapkan terima kasih. Tuhan Yesus memberkati.
    </p>

    {{-- ================= TANDA TANGAN ================= --}}
    <table style="width: 100%; margin-top: 22px; page-break-inside: avoid;">
        <tr>
            <td style="width: 50%; text-align: center; vertical-align: top;">
                @if ($ketua)
                    <div>Mengetahui,</div>
                    <div>Ketua Yayasan</div>
                    <div style="height: 2.2cm; margin: 4px 0;">
                        @if ($ketua['ttd'])
                            <img src="{{ $ketua['ttd'] }}" alt="" style="height: 2.1cm;">
                        @else
                            <div style="padding-top: 0.9cm; color: #999; font-size: 8pt;">Belum ditandatangani</div>
                        @endif
                    </div>
                    <div>{{ $ketua['nama'] ?? '..............................' }}</div>
                @endif
            </td>
            <td style="width: 50%; text-align: center; vertical-align: top;">
                <div>&nbsp;</div>
                <div>Kepala Sekolah</div>
                <div style="height: 2.2cm; margin: 4px 0;">
                    @if ($kepsek['ttd'] ?? null)
                        <img src="{{ $kepsek['ttd'] }}" alt="" style="height: 2.1cm;">
                    @else
                        <div style="padding-top: 0.9cm; color: #999; font-size: 8pt;">Belum ditandatangani</div>
                    @endif
                </div>
                <div>{{ $kepsek['nama'] ?? '..............................' }}</div>
            </td>
        </tr>
    </table>

    {{-- ================= TEMBUSAN ================= --}}
    <div style="margin-top: 24px; font-size: 9.5pt; page-break-inside: avoid;">
        Tembusan:
        <ol style="margin: 2px 0 0 18px; padding: 0;">
            @unless ($ketua)
                <li>Ketua Yayasan</li>
            @endunless
            <li>Arsip</li>
        </ol>
    </div>
</div>