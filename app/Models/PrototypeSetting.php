<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrototypeSetting extends Model
{
    protected $table = 'prototype_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
