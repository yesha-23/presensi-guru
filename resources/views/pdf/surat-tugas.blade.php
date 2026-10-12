<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Tugas {{ $surat->nomor_surat }}</title>
    <style>
        @page { margin: 1.3cm 1.8cm 1.6cm 4.4cm; }
        body { margin: 0; }
        p { margin: 6px 0; }
    </style>
</head>
<body>
    @include('surat-tugas._isi')
</body>
</html>