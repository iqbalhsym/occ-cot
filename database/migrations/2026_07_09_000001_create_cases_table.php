<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->string('id', 30)->primary(); // COT-YYYYMM-NNN
            $table->string('nama');
            $table->string('rm');
            $table->char('jenis_kelamin', 1)->default('L');
            $table->string('tgl_lahir')->nullable();
            $table->json('lokasi_pengajuan')->nullable();
            $table->text('diagnosis')->nullable();
            $table->json('jenis_operasi')->nullable(); // CITO, Elektif, ODC
            $table->string('anestesi')->nullable();
            $table->string('anestesi_lainnya')->nullable();
            $table->date('tanggal_pilihan1')->nullable();
            $table->date('tanggal_pilihan2')->nullable();
            $table->time('jam_operasi')->nullable();
            $table->string('estimasi_lama_operasi')->nullable();
            $table->string('lokasi_tindakan')->default('COT');
            $table->string('lokasi_tindakan_lainnya')->nullable();
            $table->string('asal_pasien')->nullable();
            $table->string('asal_pasien_lainnya')->nullable();
            $table->string('ruang_pasca_operasi')->nullable();
            $table->string('ruang_pasca_operasi_lainnya')->nullable();
            $table->string('estimasi_rawat_inap')->nullable();
            $table->string('penjamin')->default('Umum'); // Umum / Asuransi
            $table->string('nama_guarantor')->nullable();
            $table->string('kelas_perawatan')->nullable();
            $table->string('golongan')->nullable();
            $table->string('spesialisasi_op')->nullable();
            $table->string('current_flow')->default('Nurse'); // current step
            $table->string('status')->default('Draft'); // Draft/Submitted/InProgress/Returned/Completed/Cancelled
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        Schema::create('case_dpjp', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30);
            $table->string('nama');
            $table->integer('urutan')->default(0);
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        Schema::create('case_operators', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30);
            $table->string('nama');
            $table->string('spesialisasi')->nullable();
            $table->integer('urutan')->default(0);
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        Schema::create('case_tindakan', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30);
            $table->string('nama');
            $table->integer('urutan')->default(0);
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        Schema::create('case_alat', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30);
            $table->string('nama');
            $table->string('keterangan')->nullable();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        Schema::create('case_tambahan_bmhp', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30);
            $table->string('nama');
            $table->string('qty')->nullable();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        Schema::create('case_audit', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30);
            $table->string('actor')->nullable();
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_audit');
        Schema::dropIfExists('case_tambahan_bmhp');
        Schema::dropIfExists('case_alat');
        Schema::dropIfExists('case_tindakan');
        Schema::dropIfExists('case_operators');
        Schema::dropIfExists('case_dpjp');
        Schema::dropIfExists('cases');
    }
};
