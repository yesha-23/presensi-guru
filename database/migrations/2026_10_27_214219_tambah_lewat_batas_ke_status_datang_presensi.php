<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE presensi MODIFY status_datang ENUM('Tepat Waktu','Terlambat','Lewat Batas') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        // Kembalikan data ke nilai lama sebelum ENUM dipersempit
        DB::table('presensi')->where('status_datang', 'Lewat Batas')->update(['status_datang' => 'Terlambat']);
        DB::statement("ALTER TABLE presensi MODIFY status_datang ENUM('Tepat Waktu','Terlambat') NULL DEFAULT NULL");
    }
};