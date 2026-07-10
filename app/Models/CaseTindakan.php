<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseTindakan extends Model
{
    public $timestamps = false;
    protected $table = 'case_tindakan';
    protected $fillable = ['case_id', 'nama', 'urutan'];
}
