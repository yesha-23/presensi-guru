<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Pengaturan extends Model
{
    protected $table = 'pengaturan';
    protected $fillable = ['kunci', 'nilai'];

    /** Contoh: Pengaturan::ambil('ambang_wajah', 0.5) */
    public static function ambil(string $kunci, mixed $default = null): mixed
    {
        $nilai = Cache::rememberForever(
            "pengaturan.$kunci",
            fn () => static::where('kunci', $kunci)->value('nilai')
        );

        return $nilai ?? $default;
    }

    protected static function booted(): void
    {
        static::saved(fn (self $p) => Cache::forget("pengaturan.{$p->kunci}"));
        static::deleted(fn (self $p) => Cache::forget("pengaturan.{$p->kunci}"));
    }
}