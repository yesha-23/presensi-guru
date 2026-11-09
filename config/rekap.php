<?php

/*
 * Format kolom rekap bulanan per unit (mengikuti format Excel yang dipakai sekolah).
 * Setiap kolom: [judul, satuan, isi]
 *   isi = kunci nilai yang dihitung sistem (hadir, dnas, tlat, lemb, kdlm, kklr),
 *         atau null = kolom diisi bagian administrasi di Excel (di luar lingkup sistem).
 * Kolom No, Nama, dan KETERANGAN selalu ada di awal/akhir.
 * Unit selain KBTK (SD, Yayasan, semua unit) memakai format SD.
 */

return [
    'format' => [
        'SD' => [
            'judul'    => 'REKAP PRESENSI GURU DAN STAF',
            'subjudul' => 'SD TERUNA BANGSA',
            'kolom'    => [
                ['MSUK', 'hari', 'hadir'],
                ['DNAS', 'hari', 'dnas'],
                ['TLAT', 'jam', 'tlat'],
                ['LEMB', 'jam', 'lemb'],
                ['DGTI', 'jam', null],
                ['PGTI', 'jam', null],
                ['LJAM', 'JP', null],
                ['Ekskul', 'jaga', null],
                ['Ekskul', 'ajar ×', null],
                ['KDLM', 'hari', 'kdlm'],
                ['KKLR', 'hari', 'kklr'],
            ],
        ],

        // Sumber: FORMAT REKAPAN PRESENSI KB-TK TERUNA BANGSA.xlsx (dari Kepala KB & TK, Nov 2026)
        'KBTK' => [
            'judul'    => 'REKAP PRESENSI GURU & STAF',
            'subjudul' => 'KB-TK TERUNA BANGSA',
            'kolom'    => [
                ['HARI MASUK', 'hari', 'hadir'],
                ['DNAS', 'hari', 'dnas'],
                ['TLBT', 'jam', 'tlat'],
                ['LMBR', 'jam', 'lemb'],
                ['DGTI', 'jam', null],
                ['PGTI', 'jam', null],
                ['LBJM', 'jam', null],
                ['KGDL', 'hari', 'kdlm'],
                ['KGLR', 'hari', 'kklr'],
                ['Mengajar Ekstra', 'jam', null],
                ['Pendamping Ekstra di KB-TK', 'jam', null],
                ['Pendamping Kelas Extention', 'jam', null],
            ],
        ],
    ],
];
