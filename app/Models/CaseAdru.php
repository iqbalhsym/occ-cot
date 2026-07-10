<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAdru extends Model
{
    protected $table = 'case_adru';
    protected $fillable = ['case_id', 'decision', 'note', 'done'];
    protected $casts = ['done' => 'boolean'];
}
