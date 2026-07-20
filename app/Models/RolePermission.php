<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $fillable = ['role_id', 'label', 'menus'];

    protected $casts = [
        'menus' => 'array',
    ];
}
