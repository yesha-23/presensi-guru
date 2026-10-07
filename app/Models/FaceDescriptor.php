<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaceDescriptor extends Model
{
    protected $table = 'face_descriptors';
    protected $fillable = ['user_id', 'descriptor', 'foto_path'];

    protected function casts(): array
    {
        return ['descriptor' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}