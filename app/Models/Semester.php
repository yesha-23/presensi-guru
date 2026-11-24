<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Semester extends Model
{
    protected $table = 'semesters';
    protected $fillable = ['unit_id', 'tahun_ajaran', 'jenis', 'tanggal_mulai', 'tanggal_selesai'];

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date'];
    }
    
    /** Nomor semester: Ganjil = 1, Genap = 2 (C2). */
    public function nomor(): int
    {
        return $this->jenis === 'Genap' ? 2 : 1;
    }

    /** "Semester 1 Tahun Ajaran 2026/2027" (C2: dipakai di filter, judul, PDF, dan Excel). */
    public function label(): string
    {
        return "Semester {$this->nomor()} Tahun Ajaran {$this->tahun_ajaran}";
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}