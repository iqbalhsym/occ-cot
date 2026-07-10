<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseKasir extends Model
{
    protected $table = 'case_kasir';
    protected $fillable = ['case_id', 'decision', 'note', 'total_estimasi', 'done'];
    protected $casts = ['done' => 'boolean'];
}
