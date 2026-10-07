<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();   // SD, KBTK, YYS
            $table->string('nama', 100);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units');
            $table->string('nip', 30)->nullable()->unique();      // null setelah data pegawai Keluar dihapus
            $table->string('nama_lengkap', 150);                  // dengan gelar, untuk laporan
            $table->string('nama_panggilan', 50)->nullable();     // untuk tampilan & notifikasi
            $table->string('email', 100)->nullable()->unique();
            $table->string('password')->nullable();               // bcrypt, dibuat administrator
            $table->enum('kategori', ['Guru', 'Staf', 'Karyawan', 'Tendik'])->nullable(); // null untuk Ketua Yayasan
            $table->string('jabatan', 100)->nullable();
            $table->string('ttd_path')->nullable();               // gambar tanda tangan
            $table->dateTime('persetujuan_wajah_at')->nullable(); // persetujuan biometrik
            $table->enum('status', ['Aktif', 'Nonaktif', 'Keluar'])->default('Aktif');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();                                // deleted_at untuk hapus data pegawai Keluar
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('units');
    }
};