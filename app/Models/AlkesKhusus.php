<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlkesKhusus extends Model
{
    protected $table = 'alkes_khusus';
    protected $fillable = ['nama', 'aturan_ruangan', 'allowed_rooms'];
    protected $casts = [
        'allowed_rooms' => 'array',
    ];
}
