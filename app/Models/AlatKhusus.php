<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlatKhusus extends Model
{
    protected $table = 'alat_khusus';
    protected $fillable = ['nama', 'tarif'];

    public function getTarifFormattedAttribute(): string
    {
        return $this->tarif ? 'Rp ' . number_format($this->tarif, 0, ',', '.') : '-';
    }
}
