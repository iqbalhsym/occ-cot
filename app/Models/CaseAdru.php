<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAdru extends Model
{
    protected $table = 'case_adru';
    protected $fillable = ['case_id', 'decision', 'note', 'done', 'estimasi', 'confirm_note', 'stage1_done', 'stage2_done'];
    protected $casts = [
        'done' => 'boolean',
        'stage1_done' => 'boolean',
        'stage2_done' => 'boolean',
    ];
}
