<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAdminCot extends Model
{
    protected $table = 'case_admin_cot';
    protected $fillable = [
        'case_id', 'required', 'prelim_done', 'final_done',
        'tindakan_selesai', 'decision', 'decision_note',
        'tanggal_fix', 'jam_fix', 'kamar_operasi', 'catatan',
    ];
    protected $casts = [
        'required'         => 'boolean',
        'prelim_done'      => 'boolean',
        'final_done'       => 'boolean',
        'tindakan_selesai' => 'boolean',
        'tanggal_fix'      => 'date',
    ];
}
