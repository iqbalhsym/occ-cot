<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_va', function (Blueprint $table) {
            $table->boolean('berkas_belum_lengkap')->default(false);
            $table->boolean('stage1_done')->default(false);
            $table->boolean('stage2_done')->default(false);
            $table->text('attachments')->nullable();
            $table->text('checklist')->nullable();
        });

        Schema::table('case_kasir', function (Blueprint $table) {
            $table->boolean('stage1_done')->default(false);
            $table->boolean('stage2_done')->default(false);
            $table->text('note2')->nullable();
        });

        Schema::table('case_adru', function (Blueprint $table) {
            $table->string('estimasi')->nullable();
            $table->text('confirm_note')->nullable();
            $table->boolean('stage1_done')->default(false);
            $table->boolean('stage2_done')->default(false);
        });

        Schema::table('case_cs', function (Blueprint $table) {
            $table->string('follow_up_due')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('case_va', function (Blueprint $table) {
            $table->dropColumn(['berkas_belum_lengkap', 'stage1_done', 'stage2_done', 'attachments', 'checklist']);
        });

        Schema::table('case_kasir', function (Blueprint $table) {
            $table->dropColumn(['stage1_done', 'stage2_done', 'note2']);
        });

        Schema::table('case_adru', function (Blueprint $table) {
            $table->dropColumn(['estimasi', 'confirm_note', 'stage1_done', 'stage2_done']);
        });

        Schema::table('case_cs', function (Blueprint $table) {
            $table->dropColumn('follow_up_due');
        });
    }
};
