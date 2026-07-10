<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseVa extends Model
{
    protected $table = 'case_va';
    protected $fillable = [
        'case_id', 'kelas', 'golongan', 'decision', 'decision_note',
        'estimasi_total', 'estimasi_rincian', 'done',
    ];
    protected $casts = [
        'estimasi_rincian' => 'array',
        'done' => 'boolean',
    ];
}
