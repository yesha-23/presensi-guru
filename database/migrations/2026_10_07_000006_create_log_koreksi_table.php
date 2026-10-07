<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_koreksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');       // yang mengubah
            $table->morphs('target');                                  // presensi atau pengajuan_izin
            $table->json('nilai_lama')->nullable();
            $table->json('nilai_baru')->nullable();
            $table->text('alasan');
            $table->enum('status_persetujuan', ['Tidak Perlu', 'Menunggu', 'Disetujui', 'Ditolak'])
                  ->default('Tidak Perlu');
            // Tambahan untuk persetujuan kepala sekolah atas koreksi presensi milik admin
            $table->foreignId('penyetuju_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_penyetuju')->nullable();
            $table->dateTime('diproses_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_koreksi');
    }
};