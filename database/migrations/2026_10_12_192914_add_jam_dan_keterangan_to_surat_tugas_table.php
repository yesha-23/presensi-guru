<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->time('jam_mulai')->nullable()->after('tanggal_selesai');
            $table->time('jam_selesai')->nullable()->after('jam_mulai');
            $table->text('keterangan')->nullable()->after('lokasi'); // dresscode, perlengkapan, dll.
        });
    }

    public function down(): void
    {
        Schema::table('surat_tugas', function (Blueprint $table) {
            $table->dropColumn(['jam_mulai', 'jam_selesai', 'keterangan']);
        });
    }
};