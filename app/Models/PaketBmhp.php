<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaketBmhp extends Model
{
    protected $table = 'paket_bmhp';
    protected $fillable = ['nama', 'tarif'];

    public function getTarifFormattedAttribute(): string
    {
        return $this->tarif ? 'Rp ' . number_format($this->tarif, 0, ',', '.') : '-';
    }
}
