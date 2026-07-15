<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseVa extends Model
{
    protected $table = 'case_va';
    protected $fillable = [
        'case_id', 'kelas', 'golongan', 'decision', 'decision_note',
        'estimasi_total', 'estimasi_rincian', 'done',
        'berkas_belum_lengkap', 'stage1_done', 'stage2_done',
        'attachments', 'checklist'
    ];
    protected $casts = [
        'estimasi_rincian' => 'array',
        'done' => 'boolean',
        'berkas_belum_lengkap' => 'boolean',
        'stage1_done' => 'boolean',
        'stage2_done' => 'boolean',
        'attachments' => 'array',
        'checklist' => 'array',
    ];
}
