<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuarantorMapping extends Model
{
    protected $fillable = ['pola', 'kelompok_tarif', 'cob'];

    protected $casts = [
        'cob' => 'boolean',
    ];
}
