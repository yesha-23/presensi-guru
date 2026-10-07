<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public const ADMIN = 'Administrator';
    public const PEGAWAI = 'Pegawai';
    public const KEPALA_SEKOLAH = 'Kepala Sekolah';
    public const KETUA_YAYASAN = 'Ketua Yayasan';

    protected $table = 'roles';
    protected $fillable = ['nama'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }
}