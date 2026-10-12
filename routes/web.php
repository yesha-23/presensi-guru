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
use App\Http\Controllers\LemburController;
use App\Http\Controllers\Admin\SuratTugasController;
use App\Http\Controllers\DokumenSuratController;
use App\Http\Controllers\TtdController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\Admin\DataPresensiController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\Admin\PengaturanController;
use App\Http\Controllers\DashboardController;

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

    // Notifikasi (lonceng)
    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi');
    Route::get('/notifikasi/{id}/buka', [NotifikasiController::class, 'buka'])->name('notifikasi.buka');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');

    // Bukti izin/sakit: dicek di controller (pemilik, penyetuju, administrator)
    Route::get('/pengajuan/{pengajuan}/bukti', BuktiPengajuanController::class)->name('pengajuan.bukti');

    // Dokumen surat tugas: dicek di controller (admin, pegawai ditugaskan, penyetuju)
    Route::get('/surat-tugas/{surat}/pdf', [DokumenSuratController::class, 'pdf'])->name('surat-tugas.pdf');
    Route::get('/surat-tugas/{surat}/lampiran', [DokumenSuratController::class, 'lampiran'])->name('surat-tugas.lampiran');

    // ---------- Persetujuan bersama: kepala sekolah, administrator, ketua yayasan ----------
    Route::middleware('peran:'.Role::KEPALA_SEKOLAH.','.Role::ADMIN.','.Role::KETUA_YAYASAN)->group(function () {
        Route::get('/persetujuan', [PersetujuanController::class, 'index'])->name('persetujuan');
        Route::post('/persetujuan/izin/{pengajuan}/setujui', [PersetujuanController::class, 'setujui'])->name('persetujuan.izin.setujui');
        Route::post('/persetujuan/izin/{pengajuan}/tolak', [PersetujuanController::class, 'tolak'])->name('persetujuan.izin.tolak');
        Route::post('/persetujuan/lembur/{lembur}/setujui', [PersetujuanController::class, 'setujuiLembur'])->name('persetujuan.lembur.setujui');
        Route::post('/persetujuan/lembur/{lembur}/tolak', [PersetujuanController::class, 'tolakLembur'])->name('persetujuan.lembur.tolak');
        Route::post('/persetujuan/surat/{surat}/setujui', [PersetujuanController::class, 'setujuiSurat'])->name('persetujuan.surat.setujui');
        Route::post('/persetujuan/surat/{surat}/tolak', [PersetujuanController::class, 'tolakSurat'])->name('persetujuan.surat.tolak');
        Route::post('/persetujuan/koreksi/{log}/setujui', [PersetujuanController::class, 'setujuiKoreksi'])->name('persetujuan.koreksi.setujui');
        Route::post('/persetujuan/koreksi/{log}/tolak', [PersetujuanController::class, 'tolakKoreksi'])->name('persetujuan.koreksi.tolak');

        // Gambar tanda tangan milik penyetuju
        Route::get('/ttd', [TtdController::class, 'tampil'])->name('ttd.tampil');
        Route::post('/ttd', [TtdController::class, 'simpan'])->name('ttd.simpan');
    });

    // ---------- Rekap & Laporan PKG: administrator, kepala sekolah, ketua yayasan ----------
    Route::middleware('peran:'.Role::KEPALA_SEKOLAH.','.Role::ADMIN.','.Role::KETUA_YAYASAN)->group(function () {
        Route::get('/rekap', [RekapController::class, 'rekap'])->name('rekap');
        Route::get('/rekap/excel', [RekapController::class, 'rekapExcel'])->name('rekap.excel');
        Route::get('/rekap/pdf', [RekapController::class, 'rekapPdf'])->name('rekap.pdf');
        Route::get('/laporan-pkg', [RekapController::class, 'pkg'])->name('pkg');
        Route::get('/laporan-pkg/excel', [RekapController::class, 'pkgExcel'])->name('pkg.excel');
        Route::get('/laporan-pkg/pdf', [RekapController::class, 'pkgPdf'])->name('pkg.pdf');
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

        // Lembur
        Route::get('/lembur', [LemburController::class, 'create'])->name('lembur');
        Route::post('/lembur', [LemburController::class, 'store'])->name('lembur.store');
        Route::delete('/lembur/{lembur}', [LemburController::class, 'batal'])->name('lembur.batal');

        Route::get('/surat-tugas', [PegawaiController::class, 'suratTugas'])->name('surat-tugas');
    });

    // ---------- Administrator ----------
    Route::middleware('peran:'.Role::ADMIN)->prefix('admin')->name('admin.')->group(function () {
        Route::view('/pengajuan', 'segera', ['judul' => 'Izin, Sakit & Lembur'])->name('pengajuan');
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');

        // Pengaturan
        Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan');
        Route::put('/pengaturan/jam-kerja', [PengaturanController::class, 'jamKerja'])->name('pengaturan.jam-kerja');
        Route::put('/pengaturan/lokasi', [PengaturanController::class, 'lokasi'])->name('pengaturan.lokasi');
        Route::put('/pengaturan/presensi', [PengaturanController::class, 'presensi'])->name('pengaturan.presensi');
        Route::put('/pengaturan/kop', [PengaturanController::class, 'kop'])->name('pengaturan.kop');

        // Data Presensi
        Route::get('/presensi', [DataPresensiController::class, 'index'])->name('presensi');
        Route::get('/presensi/ekspor', [DataPresensiController::class, 'ekspor'])->name('presensi.ekspor');
        Route::post('/presensi/koreksi', [DataPresensiController::class, 'koreksi'])->name('presensi.koreksi');

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

        // Surat Tugas
        Route::get('/surat-tugas', [SuratTugasController::class, 'index'])->name('surat-tugas');
        Route::post('/surat-tugas', [SuratTugasController::class, 'store'])->name('surat-tugas.store');
        Route::get('/surat-tugas/{surat}', [SuratTugasController::class, 'show'])->name('surat-tugas.show');
        Route::put('/surat-tugas/{surat}', [SuratTugasController::class, 'update'])->name('surat-tugas.update');
        Route::post('/surat-tugas/{surat}/kirim', [SuratTugasController::class, 'kirim'])->name('surat-tugas.kirim');
        Route::delete('/surat-tugas/{surat}', [SuratTugasController::class, 'destroy'])->name('surat-tugas.destroy');
    });

    // ---------- Kepala Sekolah ----------
    Route::middleware('peran:'.Role::KEPALA_SEKOLAH)->prefix('kepsek')->name('kepsek.')->group(function () {
        Route::get('/pantau', [DashboardController::class, 'pantau'])->name('pantau');
    });

    // ---------- Ketua Yayasan ----------
    Route::middleware('peran:'.Role::KETUA_YAYASAN)->prefix('yayasan')->name('yayasan.')->group(function () {
        Route::get('/ringkasan', [DashboardController::class, 'yayasan'])->name('ringkasan');
    });
});