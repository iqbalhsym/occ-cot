<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('case_admin_cot', function (Blueprint $table) {
            $table->boolean('tindakan_selesai')->default(false)->after('final_done');
        });
    }

    public function down(): void
    {
        Schema::table('case_admin_cot', function (Blueprint $table) {
            $table->dropColumn('tindakan_selesai');
        });
    }
};
