<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal');
            $table->enum('status_harian', ['Hadir', 'Izin', 'Sakit', 'Tugas Khusus', 'Alpa', 'Libur'])->nullable();

            // Datang
            $table->time('jam_datang')->nullable();                 // waktu server
            $table->enum('status_datang', ['Tepat Waktu', 'Terlambat'])->nullable();
            $table->unsignedInteger('detik_terlambat')->default(0); // dihitung dari 07:00

            // Pulang (null sampai akhir hari = Tidak Presensi Pulang)
            $table->time('jam_pulang')->nullable();
            $table->boolean('pulang_cepat')->default(false);
            $table->unsignedInteger('detik_pulang_cepat')->default(0);

            // Bukti presensi (SR-F14)
            $table->string('foto_datang')->nullable();
            $table->string('foto_pulang')->nullable();
            $table->decimal('lat_datang', 10, 7)->nullable();
            $table->decimal('lng_datang', 10, 7)->nullable();
            $table->decimal('lat_pulang', 10, 7)->nullable();
            $table->decimal('lng_pulang', 10, 7)->nullable();
            $table->decimal('jarak_datang', 7, 2)->nullable();
            $table->decimal('jarak_pulang', 7, 2)->nullable();
            $table->decimal('skor_wajah_datang', 4, 3)->nullable();
            $table->decimal('skor_wajah_pulang', 4, 3)->nullable();

            // Sumber status non-hadir: izin, surat tugas, atau kalender (polimorfik)
            $table->nullableMorphs('referensi');

            $table->dateTime('dibatalkan_at')->nullable();          // tombol "Bukan saya"
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
    }
};