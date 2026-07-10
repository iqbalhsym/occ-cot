<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseTambahanBmhp extends Model
{
    public $timestamps = false;
    protected $table = 'case_tambahan_bmhp';
    protected $fillable = ['case_id', 'nama', 'qty'];
}
