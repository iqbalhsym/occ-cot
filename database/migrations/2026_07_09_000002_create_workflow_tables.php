<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verifikasi Asuransi (VA)
        Schema::create('case_va', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30)->unique();
            $table->string('kelas')->nullable();
            $table->string('golongan')->nullable();
            $table->string('decision')->nullable(); // Disetujui/Pending/Ditolak/DalamKonfirmasi
            $table->text('decision_note')->nullable();
            $table->bigInteger('estimasi_total')->nullable();
            $table->json('estimasi_rincian')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamps();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        // Kasir
        Schema::create('case_kasir', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30)->unique();
            $table->string('decision')->nullable(); // Disetujui/Ditolak
            $table->text('note')->nullable();
            $table->bigInteger('total_estimasi')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamps();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        // ADRU (Administrasi Rawat Unit)
        Schema::create('case_adru', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30)->unique();
            $table->string('decision')->nullable(); // Disetujui/Revisi
            $table->text('note')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamps();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        // Farmasi
        Schema::create('case_farmasi', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30)->unique();
            $table->string('decision')->nullable(); // Siap/Tidak_Siap
            $table->text('note')->nullable();
            $table->json('paket_siap')->nullable(); // list paket yang sudah siap
            $table->boolean('done')->default(false);
            $table->timestamps();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        // Admin COT
        Schema::create('case_admin_cot', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30)->unique();
            $table->boolean('required')->default(true);
            $table->boolean('prelim_done')->default(false);
            $table->boolean('final_done')->default(false);
            $table->string('decision')->nullable(); // Terjadwal/Reschedule/DalamKonfirmasi/Revisi
            $table->text('decision_note')->nullable();
            $table->date('tanggal_fix')->nullable();
            $table->time('jam_fix')->nullable();
            $table->string('kamar_operasi')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        // Case Manager
        Schema::create('case_manager', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30)->unique();
            $table->string('decision')->nullable(); // Disetujui/Revisi/DokumenBelumLengkap
            $table->string('return_to')->nullable(); // unit tujuan revisi
            $table->text('instruksi')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamps();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });

        // Customer Service (CS)
        Schema::create('case_cs', function (Blueprint $table) {
            $table->id();
            $table->string('case_id', 30)->unique();
            $table->string('decision')->nullable(); // Disetujui/Reschedule/DalamKonfirmasi/Batal
            $table->text('decision_note')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamps();
            $table->foreign('case_id')->references('id')->on('cases')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_cs');
        Schema::dropIfExists('case_manager');
        Schema::dropIfExists('case_admin_cot');
        Schema::dropIfExists('case_farmasi');
        Schema::dropIfExists('case_adru');
        Schema::dropIfExists('case_kasir');
        Schema::dropIfExists('case_va');
    }
};
