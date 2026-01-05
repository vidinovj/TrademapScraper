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
        Schema::table('tb_trade', function (Blueprint $table) {
            $table->unique(['negara', 'kode_hs', 'tahun'], 'trade_record_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_trade', function (Blueprint $table) {
            $table->dropUnique('trade_record_unique');
        });
    }
};