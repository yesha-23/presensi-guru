<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $table = 'units';
    protected $fillable = ['kode', 'nama'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function jamKerja(): HasMany
    {
        return $this->hasMany(JamKerja::class);
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class);
    }

    public function kalenderEvents(): HasMany
    {
        return $this->hasMany(KalenderEvent::class);
    }
}