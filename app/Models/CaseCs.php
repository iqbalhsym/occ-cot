<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseCs extends Model
{
    protected $table = 'case_cs';
    protected $fillable = ['case_id', 'decision', 'decision_note', 'done', 'follow_up_due'];
    protected $casts = ['done' => 'boolean'];
}
