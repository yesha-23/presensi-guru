<?php

use App\Http\Controllers\Admin\ImporPegawaiController;
use App\Http\Controllers\Admin\KalenderController;
use App\Http\Controllers\Admin\KalenderImporController;
use App\Http\Controllers\Admin\PegawaiController as AdminPegawaiController;
use App\Http\Controllers\Admin\WajahController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuktiPengajuanController;
use App\Http\Controllers\IzinController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PersetujuanController;
use App\Http\Controllers\PresensiController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

// ================= PUBLIK =================
Route::view('/', 'beranda-publik')->name('beranda');
Route::get('/presensi', [PresensiController::class, 'index'])->name('presensi.scan');
Route::post('/presensi/masuk', [PresensiController::class, 'masuk'])->name('presensi.masuk');

// Dibatasi 30 permintaan per menit per IP agar endpoint publik tidak disalahgunakan
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/presensi/scan', [PresensiController::class, 'scan'])->name('presensi.proses');
    Route::post('/presensi/pulang/konfirmasi', [PresensiController::class, 'konfirmasiPulang'])->name('presensi.pulang.konfirmasi');
    Route::post('/presensi/pulang/batal', [PresensiController::class, 'batalPulang'])->name('presensi.pulang.batal');
    Route::post('/presensi/bukan-saya', [PresensiController::class, 'bukanSaya'])->name('presensi.bukan-saya');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.proses');
});

// ================= PERLU LOGIN =================
Route::middleware(['auth', 'batas.sesi'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', fn () => redirect()->route(auth()->user()->rutePeran()))->name('dashboard');

    // Bukti izin/sakit: dicek di controller (pemilik, penyetuju, administrator)
    Route::get('/pengajuan/{pengajuan}/bukti', BuktiPengajuanController::class)->name('pengajuan.bukti');

    // ---------- Persetujuan bersama: kepala sekolah, administrator, ketua yayasan ----------
    Route::middleware('peran:'.Role::KEPALA_SEKOLAH.','.Role::ADMIN.','.Role::KETUA_YAYASAN)->group(function () {
        Route::get('/persetujuan', [PersetujuanController::class, 'index'])->name('persetujuan');
        Route::post('/persetujuan/izin/{pengajuan}/setujui', [PersetujuanController::class, 'setujui'])->name('persetujuan.izin.setujui');
        Route::post('/persetujuan/izin/{pengajuan}/tolak', [PersetujuanController::class, 'tolak'])->name('persetujuan.izin.tolak');
    });

    // ---------- Pegawai (termasuk kepala sekolah & admin yang juga berperan Pegawai) ----------
    Route::middleware('peran:'.Role::PEGAWAI)->prefix('pegawai')->name('pegawai.')->group(function () {
        Route::get('/beranda', [PegawaiController::class, 'beranda'])->name('beranda');
        Route::view('/riwayat', 'segera', ['judul' => 'Riwayat'])->name('riwayat');
        Route::view('/profil', 'segera', ['judul' => 'Profil'])->name('profil');

        // Izin & sakit
        Route::get('/izin', [IzinController::class, 'create'])->name('izin');
        Route::post('/izin', [IzinController::class, 'store'])->name('izin.store');
        Route::delete('/izin/{pengajuan}', [IzinController::class, 'batal'])->name('izin.batal');

        Route::view('/lembur', 'segera', ['judul' => 'Ajukan Lembur'])->name('lembur');
        Route::view('/surat-tugas', 'segera', ['judul' => 'Surat Tugas Saya'])->name('surat-tugas');
    });

    // ---------- Administrator ----------
    Route::middleware('peran:'.Role::ADMIN)->prefix('admin')->name('admin.')->group(function () {
        Route::view('/dashboard', 'segera', ['judul' => 'Kehadiran hari ini'])->name('dashboard');
        Route::view('/presensi', 'segera', ['judul' => 'Data Presensi'])->name('presensi');
        Route::view('/pengajuan', 'segera', ['judul' => 'Izin, Sakit & Lembur'])->name('pengajuan');
        Route::view('/surat-tugas', 'segera', ['judul' => 'Surat Tugas'])->name('surat-tugas');
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

        // Pendaftaran data wajah
        Route::get('/pegawai/{pegawai}/wajah', [WajahController::class, 'create'])->name('pegawai.wajah');
        Route::post('/pegawai/{pegawai}/wajah', [WajahController::class, 'store'])->name('pegawai.wajah.simpan');

        // Kalender Akademik: impor Excel
        Route::get('/kalender/impor', [KalenderImporController::class, 'index'])->name('kalender.impor');
        Route::get('/kalender/impor/template', [KalenderImporController::class, 'template'])->name('kalender.impor.template');
        Route::post('/kalender/impor', [KalenderImporController::class, 'unggah'])->name('kalender.impor.unggah');
        Route::post('/kalender/impor/simpan', [KalenderImporController::class, 'simpan'])->name('kalender.impor.simpan');
        Route::post('/kalender/impor/batal', [KalenderImporController::class, 'batal'])->name('kalender.impor.batal');

        // Kalender Akademik: entri & semester
        Route::get('/kalender', [KalenderController::class, 'index'])->name('kalender');
        Route::post('/kalender', [KalenderController::class, 'store'])->name('kalender.store');
        Route::post('/kalender/semester', [KalenderController::class, 'simpanSemester'])->name('kalender.semester');
        Route::put('/kalender/{entri}', [KalenderController::class, 'update'])->name('kalender.update');
        Route::delete('/kalender/{entri}', [KalenderController::class, 'destroy'])->name('kalender.destroy');
    });

    // ---------- Kepala Sekolah ----------
    Route::middleware('peran:'.Role::KEPALA_SEKOLAH)->prefix('kepsek')->name('kepsek.')->group(function () {
        Route::view('/pantau', 'segera', ['judul' => 'Pantau Kehadiran'])->name('pantau');
        Route::view('/pkg', 'segera', ['judul' => 'Laporan PKG'])->name('pkg');
        Route::view('/rekap', 'segera', ['judul' => 'Rekap Bulanan'])->name('rekap');
    });

    // ---------- Ketua Yayasan ----------
    Route::middleware('peran:'.Role::KETUA_YAYASAN)->prefix('yayasan')->name('yayasan.')->group(function () {
        Route::view('/ringkasan', 'segera', ['judul' => 'Ringkasan Yayasan'])->name('ringkasan');
        Route::view('/pkg', 'segera', ['judul' => 'Laporan PKG'])->name('pkg');
        Route::view('/rekap', 'segera', ['judul' => 'Rekap Bulanan'])->name('rekap');
    });
});