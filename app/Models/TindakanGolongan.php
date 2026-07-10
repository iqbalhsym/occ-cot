<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TindakanGolongan extends Model
{
    protected $table = 'tindakan_golongan';
    protected $fillable = ['tindakan', 'operator', 'golongan'];
}
