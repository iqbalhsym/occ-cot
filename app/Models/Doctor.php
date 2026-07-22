<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $table = 'doctors';
    protected $fillable = ['nama', 'nama_gelar', 'spesialis', 'ksm', 'konsultan', 'status', 'no_hp'];
}
