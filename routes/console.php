<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pengingat presensi datang/pulang & ringkasan admin: dicek setiap 5 menit
Schedule::command('presensi:pengingat')->everyFiveMinutes()->between('05:00', '22:00')->withoutOverlapping();

// Tutup hari: tetapkan Alpa, Izin, Sakit, Tugas Khusus, Libur (SR-F15)
Schedule::command('presensi:tutup-hari')->dailyAt('23:55')->withoutOverlapping();

// Rekap mingguan untuk rapat evaluasi hari Senin (SR-F27)
Schedule::command('presensi:rekap-mingguan')->weeklyOn(0, '06:00')->withoutOverlapping();