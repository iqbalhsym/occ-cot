<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAudit extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;
    protected $table = 'case_audit';
    protected $fillable = ['case_id', 'actor', 'action', 'note'];
}
