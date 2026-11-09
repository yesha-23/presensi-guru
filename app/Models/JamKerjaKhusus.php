<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jam masuk & pulang khusus untuk satu pegawai (menggantikan jam unit/kategori). */
class JamKerjaKhusus extends Model
{
    protected $table = 'jam_kerja_khusus';

    protected $fillable = ['user_id', 'jam_masuk', 'jam_pulang', 'keterangan'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
