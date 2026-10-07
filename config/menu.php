<?php

return [
    'Administrator' => [
        ['label' => 'Dashboard',            'route' => 'admin.dashboard',   'ikon' => 'layout-grid'],
        ['label' => 'Data Presensi',        'route' => 'admin.presensi',    'ikon' => 'clock'],
        ['label' => 'Izin, Sakit & Lembur', 'route' => 'admin.pengajuan',   'ikon' => 'file-check'],
        ['label' => 'Surat Tugas',          'route' => 'admin.surat-tugas', 'ikon' => 'briefcase'],
        ['label' => 'Kalender Akademik',    'route' => 'admin.kalender',    'ikon' => 'calendar'],
        ['label' => 'Data Pegawai',         'route' => 'admin.pegawai',     'ikon' => 'users'],
        ['label' => 'Rekap & Laporan',      'route' => 'admin.rekap',       'ikon' => 'chart-column'],
        ['label' => 'Pengaturan',           'route' => 'admin.pengaturan',  'ikon' => 'sliders-horizontal'],
    ],

    'Kepala Sekolah' => [
        ['label' => 'Riwayat Presensi Saya', 'route' => 'pegawai.riwayat',    'ikon' => 'house'],
        ['label' => 'Persetujuan',           'route' => 'kepsek.persetujuan', 'ikon' => 'file-check'],
        ['label' => 'Pantau Kehadiran',      'route' => 'kepsek.pantau',      'ikon' => 'clock'],
        ['label' => 'Laporan PKG',           'route' => 'kepsek.pkg',         'ikon' => 'chart-column'],
        ['label' => 'Rekap Bulanan',         'route' => 'kepsek.rekap',       'ikon' => 'calendar'],
    ],

    'Ketua Yayasan' => [
        ['label' => 'Ringkasan Yayasan',       'route' => 'yayasan.ringkasan',   'ikon' => 'layout-grid'],
        ['label' => 'Persetujuan Surat Tugas', 'route' => 'yayasan.surat-tugas', 'ikon' => 'pen-line'],
        ['label' => 'Laporan PKG',             'route' => 'yayasan.pkg',         'ikon' => 'chart-column'],
        ['label' => 'Rekap Bulanan',           'route' => 'yayasan.rekap',       'ikon' => 'calendar'],
    ],

    'Pegawai' => [
        ['label' => 'Beranda',  'route' => 'pegawai.beranda', 'ikon' => 'house'],
        ['label' => 'Presensi', 'route' => 'presensi.scan',   'ikon' => 'scan-face'],
        ['label' => 'Riwayat',  'route' => 'pegawai.riwayat', 'ikon' => 'list'],
        ['label' => 'Profil',   'route' => 'pegawai.profil',  'ikon' => 'user'],
    ],
];