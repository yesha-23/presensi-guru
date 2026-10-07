<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PegawaiController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

// ================= PUBLIK =================
Route::view('/', 'beranda-publik')->name('beranda');
Route::view('/presensi', 'presensi.scan')->name('presensi.scan'); // diisi di langkah scan wajah

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.proses');
});

// ================= PERLU LOGIN =================
Route::middleware(['auth', 'batas.sesi'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->rutePeran()))
        ->name('dashboard');

    // ---------- Pegawai (termasuk kepala sekolah & admin yang juga berperan Pegawai) ----------
    Route::middleware('peran:'.Role::PEGAWAI)->prefix('pegawai')->name('pegawai.')->group(function () {
        Route::get('/beranda', [PegawaiController::class, 'beranda'])->name('beranda');
        Route::view('/riwayat', 'segera', ['judul' => 'Riwayat'])->name('riwayat');
        Route::view('/profil', 'segera', ['judul' => 'Profil'])->name('profil');
        Route::view('/izin', 'segera', ['judul' => 'Ajukan Izin / Sakit'])->name('izin');
        Route::view('/lembur', 'segera', ['judul' => 'Ajukan Lembur'])->name('lembur');
        Route::view('/surat-tugas', 'segera', ['judul' => 'Surat Tugas Saya'])->name('surat-tugas');
    });

    // ---------- Administrator ----------
    Route::middleware('peran:'.Role::ADMIN)->prefix('admin')->name('admin.')->group(function () {
        Route::view('/dashboard', 'segera', ['judul' => 'Kehadiran hari ini'])->name('dashboard');
        Route::view('/presensi', 'segera', ['judul' => 'Data Presensi'])->name('presensi');
        Route::view('/pengajuan', 'segera', ['judul' => 'Izin, Sakit & Lembur'])->name('pengajuan');
        Route::view('/surat-tugas', 'segera', ['judul' => 'Surat Tugas'])->name('surat-tugas');
        Route::view('/kalender', 'segera', ['judul' => 'Kalender Akademik'])->name('kalender');
        Route::view('/pegawai', 'segera', ['judul' => 'Data Pegawai'])->name('pegawai');
        Route::view('/rekap', 'segera', ['judul' => 'Rekap & Laporan'])->name('rekap');
        Route::view('/pengaturan', 'segera', ['judul' => 'Pengaturan'])->name('pengaturan');
    });

    // ---------- Kepala Sekolah ----------
    Route::middleware('peran:'.Role::KEPALA_SEKOLAH)->prefix('kepsek')->name('kepsek.')->group(function () {
        Route::view('/persetujuan', 'segera', ['judul' => 'Persetujuan'])->name('persetujuan');
        Route::view('/pantau', 'segera', ['judul' => 'Pantau Kehadiran'])->name('pantau');
        Route::view('/pkg', 'segera', ['judul' => 'Laporan PKG'])->name('pkg');
        Route::view('/rekap', 'segera', ['judul' => 'Rekap Bulanan'])->name('rekap');
    });

    // ---------- Ketua Yayasan ----------
    Route::middleware('peran:'.Role::KETUA_YAYASAN)->prefix('yayasan')->name('yayasan.')->group(function () {
        Route::view('/ringkasan', 'segera', ['judul' => 'Ringkasan Yayasan'])->name('ringkasan');
        Route::view('/surat-tugas', 'segera', ['judul' => 'Persetujuan Surat Tugas'])->name('surat-tugas');
        Route::view('/pkg', 'segera', ['judul' => 'Laporan PKG'])->name('pkg');
        Route::view('/rekap', 'segera', ['judul' => 'Rekap Bulanan'])->name('rekap');
    });
});