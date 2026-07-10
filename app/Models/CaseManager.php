<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseManager extends Model
{
    protected $table = 'case_manager';
    protected $fillable = ['case_id', 'decision', 'return_to', 'instruksi', 'done'];
    protected $casts = ['done' => 'boolean'];
}
