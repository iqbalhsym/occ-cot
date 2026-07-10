<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseFarmasi extends Model
{
    protected $table = 'case_farmasi';
    protected $fillable = ['case_id', 'decision', 'note', 'paket_siap', 'done'];
    protected $casts = ['paket_siap' => 'array', 'done' => 'boolean'];
}
