<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Database tindakan operasi (dari Excel sheet DATABASE)
        Schema::create('tindakan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('golongan')->nullable();
            $table->string('spesialisasi')->nullable();
            $table->string('paket')->nullable();
            $table->bigInteger('harga_bpjs')->nullable();
            $table->bigInteger('harga_umum')->nullable();
            $table->json('bmhp')->nullable(); // [{n, h, q}]
            $table->string('paket_anestesi')->nullable();
            $table->string('alat')->nullable();
            $table->timestamps();
        });

        // Database paket BMHP (dari Excel sheet PAKET BMHP)
        Schema::create('paket_bmhp', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->bigInteger('tarif')->nullable();
            $table->timestamps();
        });

        // Database alat khusus (dari Excel sheet ALAT)
        Schema::create('alat_khusus', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->bigInteger('tarif')->nullable();
            $table->timestamps();
        });

        // Database tindakan + golongan (dari Excel sheet TINDAKAN+GOLONGAN)
        Schema::create('tindakan_golongan', function (Blueprint $table) {
            $table->id();
            $table->string('tindakan');
            $table->string('operator')->nullable();
            $table->string('golongan')->nullable();
            $table->timestamps();
        });

        // Master data pasien (untuk lookup RM)
        Schema::create('pasien', function (Blueprint $table) {
            $table->id();
            $table->string('rm')->unique();
            $table->string('nama');
            $table->char('jenis_kelamin', 1)->default('L');
            $table->string('tgl_lahir')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasien');
        Schema::dropIfExists('tindakan_golongan');
        Schema::dropIfExists('alat_khusus');
        Schema::dropIfExists('paket_bmhp');
        Schema::dropIfExists('tindakan');
    }
};
