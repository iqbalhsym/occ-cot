<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OperationCase extends Model
{
    protected $table = 'cases';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'nama', 'rm', 'jenis_kelamin', 'tgl_lahir',
        'lokasi_pengajuan', 'diagnosis',
        'jenis_operasi', 'anestesi', 'anestesi_lainnya',
        'tanggal_pilihan1', 'tanggal_pilihan2', 'jam_operasi', 'estimasi_lama_operasi',
        'lokasi_tindakan', 'lokasi_tindakan_lainnya',
        'asal_pasien', 'asal_pasien_lainnya',
        'ruang_pasca_operasi', 'ruang_pasca_operasi_lainnya',
        'estimasi_rawat_inap',
        'penjamin', 'nama_guarantor', 'kelas_perawatan',
        'golongan', 'spesialisasi_op',
        'current_flow', 'status', 'catatan',
    ];

    protected $casts = [
        'lokasi_pengajuan' => 'array',
        'jenis_operasi' => 'array',
        'tanggal_pilihan1' => 'date',
        'tanggal_pilihan2' => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function dpjp(): HasMany
    {
        return $this->hasMany(CaseDpjp::class, 'case_id')->orderBy('urutan');
    }

    public function operators(): HasMany
    {
        return $this->hasMany(CaseOperator::class, 'case_id')->orderBy('urutan');
    }

    public function tindakan(): HasMany
    {
        return $this->hasMany(CaseTindakan::class, 'case_id')->orderBy('urutan');
    }

    public function alat(): HasMany
    {
        return $this->hasMany(CaseAlat::class, 'case_id');
    }

    public function tambahanBmhp(): HasMany
    {
        return $this->hasMany(CaseTambahanBmhp::class, 'case_id');
    }

    public function audit(): HasMany
    {
        return $this->hasMany(CaseAudit::class, 'case_id')->orderBy('created_at', 'desc');
    }

    public function va(): HasOne
    {
        return $this->hasOne(CaseVa::class, 'case_id');
    }

    public function kasir(): HasOne
    {
        return $this->hasOne(CaseKasir::class, 'case_id');
    }

    public function adru(): HasOne
    {
        return $this->hasOne(CaseAdru::class, 'case_id');
    }

    public function farmasi(): HasOne
    {
        return $this->hasOne(CaseFarmasi::class, 'case_id');
    }

    public function adminCot(): HasOne
    {
        return $this->hasOne(CaseAdminCot::class, 'case_id');
    }

    public function caseManager(): HasOne
    {
        return $this->hasOne(CaseManager::class, 'case_id');
    }

    public function cs(): HasOne
    {
        return $this->hasOne(CaseCs::class, 'case_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public static function generateId(): string
    {
        $prefix = 'COT-' . now()->format('Ym') . '-';
        $last = self::where('id', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();
        $num = $last ? (int) substr($last->id, -3) + 1 : 1;
        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }

    public function getDpjpListAttribute(): array
    {
        return $this->dpjp->pluck('nama')->toArray();
    }

    public function getTindakanListAttribute(): array
    {
        return $this->tindakan->pluck('nama')->toArray();
    }

    public function getOperatorListAttribute(): array
    {
        return $this->operators->pluck('nama')->toArray();
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match($this->status) {
            'Draft'     => 'st-draft',
            'Submitted' => 'st-Menunggu',
            'InProgress'=> 'st-Disetujui',
            'Returned'  => 'st-Revisi',
            'Completed' => 'st-Completed',
            'Cancelled' => 'st-Batal',
            default     => 'st-default',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'Draft'      => 'Draft',
            'Submitted'  => 'Diajukan',
            'InProgress' => 'Dalam Proses',
            'Returned'   => 'Dikembalikan',
            'Completed'  => 'Selesai',
            'Cancelled'  => 'Dibatalkan',
            default      => $this->status,
        };
    }

    public function addAudit(string $action, ?string $note = null, ?string $actor = null): void
    {
        $this->audit()->create([
            'actor'  => $actor ?? 'Sistem',
            'action' => $action,
            'note'   => $note,
        ]);
    }

    public function isNeedingAsuransiFlow(): bool
    {
        return $this->penjamin === 'Asuransi';
    }
}
