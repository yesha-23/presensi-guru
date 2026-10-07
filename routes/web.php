<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PegawaiController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ImporPegawaiController;
use App\Http\Controllers\Admin\PegawaiController as AdminPegawaiController;

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
        Route::view('/rekap', 'segera', ['judul' => 'Rekap & Laporan'])->name('rekap');
        Route::view('/pengaturan', 'segera', ['judul' => 'Pengaturan'])->name('pengaturan');

        // Data Pegawai: impor Excel
        Route::get('/pegawai/impor', [ImporPegawaiController::class, 'index'])->name('pegawai.impor');
        Route::get('/pegawai/impor/template', [ImporPegawaiController::class, 'template'])->name('pegawai.impor.template');
        Route::post('/pegawai/impor', [ImporPegawaiController::class, 'unggah'])->name('pegawai.impor.unggah');
        Route::post('/pegawai/impor/simpan', [ImporPegawaiController::class, 'simpan'])->name('pegawai.impor.simpan');
        Route::post('/pegawai/impor/batal', [ImporPegawaiController::class, 'batal'])->name('pegawai.impor.batal');

        // Data Pegawai: CRUD
        Route::get('/pegawai', [AdminPegawaiController::class, 'index'])->name('pegawai');
        Route::post('/pegawai', [AdminPegawaiController::class, 'store'])->name('pegawai.store');
        Route::put('/pegawai/{pegawai}', [AdminPegawaiController::class, 'update'])->name('pegawai.update');
        Route::post('/pegawai/{pegawai}/reset-sandi', [AdminPegawaiController::class, 'resetSandi'])->name('pegawai.reset-sandi');
        Route::delete('/pegawai/{pegawai}', [AdminPegawaiController::class, 'destroy'])->name('pegawai.destroy');

        // Diisi di langkah pendaftaran wajah (14 Okt)
        Route::view('/pegawai/{pegawai}/wajah', 'segera', ['judul' => 'Pendaftaran Data Wajah'])->name('pegawai.wajah');
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