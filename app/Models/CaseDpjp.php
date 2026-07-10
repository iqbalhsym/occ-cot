<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseDpjp extends Model
{
    public $timestamps = false;
    protected $table = 'case_dpjp';
    protected $fillable = ['case_id', 'nama', 'urutan'];
}

