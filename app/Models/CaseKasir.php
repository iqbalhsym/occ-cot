<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseKasir extends Model
{
    protected $table = 'case_kasir';
    protected $fillable = ['case_id', 'decision', 'note', 'total_estimasi', 'done', 'stage1_done', 'stage2_done', 'note2'];
    protected $casts = [
        'done' => 'boolean',
        'stage1_done' => 'boolean',
        'stage2_done' => 'boolean',
    ];
}
