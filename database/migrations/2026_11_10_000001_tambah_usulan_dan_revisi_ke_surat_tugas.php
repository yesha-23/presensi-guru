<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Arahan dosen pembimbing (bimbingan 4 Nov 2026): guru dapat mengusulkan surat tugas,
 * dan alur status surat tugas diperjelas.
 *
 * surat_tugas.status:
 *   Diajukan     = usulan dari pegawai, belum diproses admin
 *   Diproses     = admin sedang memeriksa/melengkapi usulan
 *   Draf         = disusun admin, belum dikirim
 *   Menunggu     = menunggu persetujuan kepala sekolah / Ketua Yayasan
 *   Perlu Revisi = penanda tangan meminta perbaikan, kembali ke admin
 *   Disetujui    = nomor terbit, PDF final
 *   Ditolak      = ditolak final (oleh penanda tangan, atau usulan ditolak admin)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE surat_tugas MODIFY status
            ENUM('Diajukan','Diproses','Draf','Menunggu','Perlu Revisi','Disetujui','Ditolak') NOT NULL DEFAULT 'Draf'");

        DB::statement("ALTER TABLE persetujuan_surat_tugas MODIFY status
            ENUM('Menunggu','Disetujui','Perlu Revisi','Ditolak') NOT NULL DEFAULT 'Menunggu'");

        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->foreignId('diajukan_oleh')->nullable()->after('dibuat_oleh')->constrained('users')->nullOnDelete();
            $table->text('catatan_usulan')->nullable()->after('diajukan_oleh'); // alasan admin menolak usulan
        });
    }

    public function down(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diajukan_oleh');
            $table->dropColumn('catatan_usulan');
        });

        DB::statement("UPDATE surat_tugas SET status = 'Draf' WHERE status IN ('Diajukan','Diproses','Perlu Revisi')");
        DB::statement("ALTER TABLE surat_tugas MODIFY status
            ENUM('Draf','Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Draf'");

        DB::statement("UPDATE persetujuan_surat_tugas SET status = 'Ditolak' WHERE status = 'Perlu Revisi'");
        DB::statement("ALTER TABLE persetujuan_surat_tugas MODIFY status
            ENUM('Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Menunggu'");
    }
};
