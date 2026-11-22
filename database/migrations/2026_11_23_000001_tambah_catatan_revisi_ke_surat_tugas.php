<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan black box (B5): saat surat hasil revisi dikirim ulang, penanda tangan tidak
 * mengetahui catatan revisi sebelumnya dan apa yang sudah diperbaiki administrator.
 *
 *   catatan_revisi    = catatan terakhir dari penanda tangan yang meminta revisi
 *   catatan_perbaikan = penjelasan administrator tentang apa yang sudah diperbaiki
 *
 * Disimpan di surat_tugas karena baris persetujuan_surat_tugas dibuat ulang
 * setiap kali surat dikirim ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->text('catatan_revisi')->nullable()->after('catatan_usulan');
            $table->text('catatan_perbaikan')->nullable()->after('catatan_revisi');
        });

        // Surat yang saat ini berstatus Perlu Revisi: salin catatannya dari tabel persetujuan
        DB::statement("
            UPDATE surat_tugas s
            JOIN persetujuan_surat_tugas p ON p.surat_tugas_id = s.id AND p.status = 'Perlu Revisi'
            SET s.catatan_revisi = p.catatan
            WHERE s.status = 'Perlu Revisi'
        ");
    }

    public function down(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->dropColumn(['catatan_revisi', 'catatan_perbaikan']);
        });
    }
};