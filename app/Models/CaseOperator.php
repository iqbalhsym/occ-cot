<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseOperator extends Model
{
    public $timestamps = false;
    protected $table = 'case_operators';
    protected $fillable = ['case_id', 'nama', 'spesialisasi', 'urutan'];
}
