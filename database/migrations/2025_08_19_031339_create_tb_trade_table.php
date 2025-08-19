<?php
// database/migrations/2024_xx_xx_create_tb_trade_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates TbTrade table as per Data Engineering Test specification:
     * - Negara: Nama Negara Pengimpor 
     * - Kode HS: Kode Harmonized System
     * - Label: Deskripsi Produk
     * - Tahun: Tahun Ekspor (2020-2024)
     * - Jumlah: Value Ekspor
     * - Satuan: Satuan Data ("-" if not available)
     * - Sumber Data: "Trademap"
     */
    public function up(): void
    {
        Schema::create('tb_trade', function (Blueprint $table) {
            $table->id();
            
            // Core fields as per test requirements
            $table->string('negara', 100)->comment('Nama Negara Pengimpor (e.g., Indonesia)');
            $table->string('kode_hs', 50)->comment('Kode Harmonized System dari kolom Code');
            $table->text('label')->comment('Deskripsi Produk dari kolom Product Label');
            $table->year('tahun')->comment('Tahun Ekspor (2020-2024)');
            $table->decimal('jumlah', 15, 2)->default(0)->comment('Value Ekspor dari kolom Imported Value');
            $table->string('satuan', 50)->default('-')->comment('Satuan Data (- jika tidak tersedia)');
            $table->string('sumber_data', 50)->default('Trademap')->comment('Sumber Data: Trademap');
            
            // Additional metadata for monitoring
            $table->timestamp('scraped_at')->useCurrent()->comment('When data was scraped');
            $table->timestamps();
            
            // Indexes for performance (matching SQL solutions from Question 1d)
            $table->index('negara', 'idx_tb_trade_negara');
            $table->index('kode_hs', 'idx_tb_trade_kode_hs');
            $table->index('tahun', 'idx_tb_trade_tahun');
            $table->index(['negara', 'tahun'], 'idx_tb_trade_negara_tahun');
            $table->index(['kode_hs', 'tahun'], 'idx_tb_trade_kode_hs_tahun');
            
            // Composite index for common queries
            $table->index(['sumber_data', 'tahun'], 'idx_tb_trade_source_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_trade');
    }
};