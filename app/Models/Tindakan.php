<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tindakan extends Model
{
    protected $table = 'tindakan';
    protected $fillable = [
        'nama', 'golongan', 'spesialisasi', 'paket',
        'harga_bpjs', 'harga_umum', 'bmhp', 'paket_anestesi', 'alat',
    ];
    protected $casts = ['bmhp' => 'array'];

    public function getHargaUmumFormattedAttribute(): string
    {
        return $this->harga_umum ? 'Rp ' . number_format($this->harga_umum, 0, ',', '.') : '-';
    }

    public function getHargaBpjsFormattedAttribute(): string
    {
        return $this->harga_bpjs ? 'Rp ' . number_format($this->harga_bpjs, 0, ',', '.') : '-';
    }
}
