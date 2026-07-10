<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAlat extends Model
{
    public $timestamps = false;
    protected $table = 'case_alat';
    protected $fillable = ['case_id', 'nama', 'keterangan'];
}
