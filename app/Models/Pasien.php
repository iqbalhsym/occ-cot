<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pasien extends Model
{
    protected $table = 'pasien';
    protected $fillable = ['rm', 'nama', 'jenis_kelamin', 'tgl_lahir'];
}
