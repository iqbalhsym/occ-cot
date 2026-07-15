<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_alat', function (Blueprint $table) {
            $table->bigInteger('harga')->default(0);
        });

        Schema::table('case_tambahan_bmhp', function (Blueprint $table) {
            $table->bigInteger('harga')->default(0);
            $table->string('jenis', 30)->default('tambahan'); // 'paket' or 'tambahan'
        });
    }

    public function down(): void
    {
        Schema::table('case_alat', function (Blueprint $table) {
            $table->dropColumn('harga');
        });

        Schema::table('case_tambahan_bmhp', function (Blueprint $table) {
            $table->dropColumn(['harga', 'jenis']);
        });
    }
};
