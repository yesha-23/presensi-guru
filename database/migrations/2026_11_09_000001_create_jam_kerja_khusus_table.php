<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jam kerja khusus per pegawai (hasil wawancara Kepala SD, 5 Nov 2026).
 * Contoh: staf yang masuk 08.00 dan pulang lebih sore untuk menjaga penjemputan siswa.
 * Jika pegawai punya baris di sini, jam masuk & pulang unit/kategori diganti dengan jam ini.
 * Toleransi tetap mengikuti tabel jam_kerja (unit & kategori pegawai).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jam_kerja_khusus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->string('keterangan', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jam_kerja_khusus');
    }
};
