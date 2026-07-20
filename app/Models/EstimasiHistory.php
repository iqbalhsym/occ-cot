<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstimasiHistory extends Model
{
    protected $fillable = [
        'rm', 'nama', 'tindakan', 'penjamin',
        'guarantor', 'golongan', 'kelas',
        'total_estimasi', 'rincian'
    ];

    protected $casts = [
        'rincian' => 'array',
    ];
}
