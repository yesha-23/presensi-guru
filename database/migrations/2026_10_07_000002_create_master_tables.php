<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_descriptors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->json('descriptor');          // array 128 angka dari face-api.js
            $table->string('foto_path');         // foto referensi saat pendaftaran
            $table->timestamps();
        });

        Schema::create('jam_kerja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->enum('kategori', ['Guru', 'Staf', 'Karyawan', 'Tendik']);
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->unsignedTinyInteger('toleransi_menit')->default(15);
            $table->timestamps();
            $table->unique(['unit_id', 'kategori']);
        });

        Schema::create('lokasi', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('radius_meter')->default(150);
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('tahun_ajaran', 9);   // 2026/2027
            $table->enum('jenis', ['Ganjil', 'Genap']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->timestamps();
            $table->unique(['unit_id', 'tahun_ajaran', 'jenis']);
        });

        Schema::create('kalender_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete(); // null = semua unit
            $table->string('nama', 150);
            $table->enum('kategori', [
                'Libur Nasional', 'Cuti Bersama', 'Libur Internal',
                'Kegiatan Sekolah', 'Hari Kerja Tambahan',
            ]);
            $table->enum('berlaku_untuk', ['Siswa', 'Guru dan Staf'])->default('Guru dan Staf');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->time('jam_masuk')->nullable();   // hanya untuk Hari Kerja Tambahan
            $table->time('jam_pulang')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });

        Schema::create('pengaturan', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 50)->unique();
            $table->string('nilai', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
        Schema::dropIfExists('kalender_events');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('lokasi');
        Schema::dropIfExists('jam_kerja');
        Schema::dropIfExists('face_descriptors');
    }
};