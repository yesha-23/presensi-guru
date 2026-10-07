<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_tugas', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_surat', 50)->nullable()->unique(); // dibuat otomatis setelah disetujui
            $table->string('perihal', 200);
            $table->enum('pemberi_tugas', ['Dinas', 'Yayasan', 'Lainnya']);
            $table->string('lokasi', 150)->nullable();
            $table->enum('jenis_kegiatan', ['Dalam', 'Luar']);      // KDLM / KKLR
            $table->enum('sifat', ['Terencana', 'Mendadak']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('kasus_khusus')->default(false);         // true = perlu Ketua Yayasan
            $table->string('lampiran_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->enum('status', ['Draf', 'Menunggu', 'Disetujui', 'Ditolak'])->default('Draf');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('surat_tugas_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_tugas_id')->constrained('surat_tugas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['surat_tugas_id', 'user_id']);
        });

        Schema::create('persetujuan_surat_tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_tugas_id')->constrained('surat_tugas')->cascadeOnDelete();
            $table->foreignId('penyetuju_id')->constrained('users');
            $table->unsignedTinyInteger('urutan');                   // 1 = kepala sekolah, 2 = ketua yayasan
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak'])->default('Menunggu');
            $table->text('catatan')->nullable();                     // alasan jika ditolak
            $table->dateTime('ditandatangani_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persetujuan_surat_tugas');
        Schema::dropIfExists('surat_tugas_pegawai');
        Schema::dropIfExists('surat_tugas');
    }
};