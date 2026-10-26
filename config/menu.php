<?php

return [
    'Administrator' => [
        ['label' => 'Dashboard',            'route' => 'admin.dashboard',   'ikon' => 'layout-grid'],
        ['label' => 'Persetujuan',          'route' => 'persetujuan',       'ikon' => 'clipboard-check'],
        ['label' => 'Data Presensi',        'route' => 'admin.presensi',    'ikon' => 'clock'],
        ['label' => 'Izin, Sakit & Lembur', 'route' => 'admin.pengajuan',   'ikon' => 'file-check'],
        ['label' => 'Surat Tugas',          'route' => 'admin.surat-tugas', 'ikon' => 'briefcase'],
        ['label' => 'Kalender Akademik',    'route' => 'admin.kalender',    'ikon' => 'calendar'],
        ['label' => 'Data Pegawai',         'route' => 'admin.pegawai',     'ikon' => 'users'],
        ['label' => 'Rekap & Laporan',      'route' => 'rekap',             'ikon' => 'chart-column'],
        ['label' => 'Pengaturan',           'route' => 'admin.pengaturan',  'ikon' => 'sliders-horizontal'],
    ],

    'Kepala Sekolah' => [
        ['label' => 'Persetujuan',      'route' => 'persetujuan',   'ikon' => 'file-check'],
        ['label' => 'Pantau Kehadiran', 'route' => 'kepsek.pantau', 'ikon' => 'clock'],
        ['label' => 'Laporan PKG',      'route' => 'pkg',           'ikon' => 'chart-column'],
        ['label' => 'Rekap Bulanan',    'route' => 'rekap',         'ikon' => 'calendar'],
    ],

    'Ketua Yayasan' => [
        ['label' => 'Ringkasan Yayasan', 'route' => 'yayasan.ringkasan', 'ikon' => 'layout-grid'],
        ['label' => 'Persetujuan',       'route' => 'persetujuan',       'ikon' => 'pen-line'],
        ['label' => 'Laporan PKG',       'route' => 'pkg',               'ikon' => 'chart-column'],
        ['label' => 'Rekap Bulanan',     'route' => 'rekap',             'ikon' => 'calendar'],
    ],

    // 'aktif' (opsional) = pola rute tambahan agar menu tetap tersorot di halaman turunannya
    'Pegawai' => [
        ['label' => 'Beranda',   'route' => 'pegawai.beranda',   'ikon' => 'house'],
        ['label' => 'Presensi',  'route' => 'presensi.scan',     'ikon' => 'scan-face'],
        ['label' => 'Pengajuan', 'route' => 'pegawai.pengajuan', 'ikon' => 'file-plus-2',
            'aktif' => ['pegawai.izin*', 'pegawai.lembur*']],
        ['label' => 'Riwayat',   'route' => 'pegawai.riwayat',   'ikon' => 'list'],
        ['label' => 'Profil',    'route' => 'pegawai.profil',    'ikon' => 'user'],
    ],

    // Menu pribadi untuk kepala sekolah & administrator yang juga berperan Pegawai
    'Pribadi' => [
        ['label' => 'Beranda saya',   'route' => 'pegawai.beranda',   'ikon' => 'house'],
        ['label' => 'Pengajuan saya', 'route' => 'pegawai.pengajuan', 'ikon' => 'file-plus-2',
            'aktif' => ['pegawai.izin*', 'pegawai.lembur*']],
        ['label' => 'Riwayat saya',   'route' => 'pegawai.riwayat',   'ikon' => 'list'],
    ],
];