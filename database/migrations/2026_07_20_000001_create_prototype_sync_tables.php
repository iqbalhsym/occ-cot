<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. guarantor_mappings
        Schema::create('guarantor_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('pola')->unique();
            $table->string('kelompok_tarif');
            $table->boolean('cob')->default(false);
            $table->timestamps();
        });

        // Seed default guarantor mappings
        $mappings = [
            ['pola' => 'Inhealth', 'kelompok_tarif' => '2023', 'cob' => false],
            ['pola' => 'YKKBI', 'kelompok_tarif' => '2023', 'cob' => false],
            ['pola' => 'COB Inhealth', 'kelompok_tarif' => '2023', 'cob' => true],
            ['pola' => 'COB KAI', 'kelompok_tarif' => '2023', 'cob' => true],
            ['pola' => 'COB BRI', 'kelompok_tarif' => '2023', 'cob' => true],
            ['pola' => 'ADMEDIKA', 'kelompok_tarif' => '2026', 'cob' => false],
            ['pola' => 'BPJSTK', 'kelompok_tarif' => '2025', 'cob' => false],
            ['pola' => 'NAYAKA', 'kelompok_tarif' => '2025', 'cob' => false],
            ['pola' => 'Allianz', 'kelompok_tarif' => '2025', 'cob' => false],
            ['pola' => 'PT KAI', 'kelompok_tarif' => '2025', 'cob' => false],
            ['pola' => 'UMUM', 'kelompok_tarif' => 'UMUM', 'cob' => false],
        ];
        $now = now();
        foreach ($mappings as $m) {
            DB::table('guarantor_mappings')->insert(array_merge($m, ['created_at' => $now, 'updated_at' => $now]));
        }

        // 2. estimasi_histories
        Schema::create('estimasi_histories', function (Blueprint $table) {
            $table->id();
            $table->string('rm')->nullable();
            $table->string('nama')->nullable();
            $table->text('tindakan')->nullable();
            $table->string('penjamin')->nullable();
            $table->string('guarantor')->nullable();
            $table->string('golongan')->nullable();
            $table->string('kelas')->nullable();
            $table->bigInteger('total_estimasi');
            $table->json('rincian')->nullable();
            $table->timestamps();
        });

        // 3. role_permissions
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role_id')->unique();
            $table->string('label');
            $table->json('menus');
            $table->timestamps();
        });

        // Seed default role permissions (based on defaultMenusForRole in prototype HTML)
        $roles = [
            [
                'role_id' => 'Nurse',
                'label' => 'Nurse',
                'menus' => json_encode(["dashboard", "monitoring", "roles", "newcase", "queue"])
            ],
            [
                'role_id' => 'VA',
                'label' => 'VA (Verifikator Asuransi)',
                'menus' => json_encode(["dashboard", "monitoring", "roles", "queue", "estimasiMandiri", "guarantorMapping", "estimasiHistory"])
            ],
            [
                'role_id' => 'Kasir',
                'label' => 'Kasir',
                'menus' => json_encode(["dashboard", "monitoring", "roles", "queue", "estimasiMandiri"])
            ],
            [
                'role_id' => 'ADRUCOT',
                'label' => 'ADRU COT',
                'menus' => json_encode(["dashboard", "monitoring", "roles", "queue", "estimasiMandiri"])
            ],
            [
                'role_id' => 'Farmasi',
                'label' => 'Farmasi',
                'menus' => json_encode(["dashboard", "monitoring", "roles", "queue"])
            ],
            [
                'role_id' => 'AdminCOT',
                'label' => 'Admin COT',
                'menus' => json_encode(["dashboard", "monitoring", "roles", "queue"])
            ],
            [
                'role_id' => 'CaseManager',
                'label' => 'Case Manager',
                'menus' => json_encode(["dashboard", "monitoring", "roles", "queue", "estimasiMandiri", "guarantorMapping", "estimasiHistory", "roleManagement"])
            ],
            [
                'role_id' => 'CS',
                'label' => 'Customer Service',
                'menus' => json_encode(["dashboard", "monitoring", "roles", "queue"])
            ],
            [
                'role_id' => 'Viewer',
                'label' => 'Viewer',
                'menus' => json_encode(["dashboard", "monitoring", "roles"])
            ],
            [
                'role_id' => 'Administrator',
                'label' => 'Administrator',
                'menus' => json_encode(["dashboard", "monitoring", "roles", "newcase", "queue", "estimasiMandiri", "guarantorMapping", "estimasiHistory", "roleManagement"])
            ],
        ];
        foreach ($roles as $r) {
            DB::table('role_permissions')->insert(array_merge($r, ['created_at' => $now, 'updated_at' => $now]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('estimasi_histories');
        Schema::dropIfExists('guarantor_mappings');
    }
};
