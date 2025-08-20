<?php
// app/Console/Commands/SetupDataEngineerTest.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class SetupDataEngineerTest extends Command
{
    protected $signature = 'setup:data-engineer-test {--fresh : Drop existing tables and start fresh}';
    protected $description = 'Setup tables and data for Data Engineer technical test demonstration';

    public function handle()
    {
        $this->info('🚀 Setting up Data Engineer Test Question 1 demonstration...');
        $this->newLine();

        try {
            if ($this->option('fresh')) {
                $this->info('🗑️  Dropping existing tables...');
                $this->dropExistingTables();
            }

            $this->info('📋 Creating required tables...');
            $this->createTables();
            
            $this->info('📊 Inserting test data...');
            $this->insertTestData();
            
            $this->info('✅ Verifying setup...');
            $this->verifySetup();
            
            $this->newLine();
            $this->info('🎯 Data Engineer Test setup completed successfully!');
            $this->newLine();
            
            $this->displayUsageInstructions();
            
        } catch (\Exception $e) {
            $this->error('❌ Setup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function dropExistingTables()
    {
        $tables = ['tabel_perdagangan', 'tabel_produk', 'tabel_negara'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
                $this->line("   • Dropped table: {$table}");
            }
        }
    }

    private function createTables()
    {
        // Create Tabel Negara
        if (!Schema::hasTable('tabel_negara')) {
            Schema::create('tabel_negara', function ($table) {
                $table->string('kode_negara', 10)->primary();
                $table->string('negara', 100);
                $table->timestamps();
                $table->index('kode_negara', 'idx_negara_kode');
            });
            $this->line('   • Created table: tabel_negara');
        }

        // Create Tabel Produk
        if (!Schema::hasTable('tabel_produk')) {
            Schema::create('tabel_produk', function ($table) {
                $table->string('hscode', 50)->primary();
                $table->text('label');
                $table->timestamps();
                $table->index('hscode', 'idx_produk_hscode');
            });
            $this->line('   • Created table: tabel_produk');
        }

        // Create Tabel Perdagangan
        if (!Schema::hasTable('tabel_perdagangan')) {
            Schema::create('tabel_perdagangan', function ($table) {
                $table->string('id_trx', 10)->primary();
                $table->string('kode_negara', 10);
                $table->string('hscode', 50);
                $table->string('sektor', 50);
                $table->integer('bulan');
                $table->integer('tahun');
                $table->decimal('nilai', 15, 2);
                $table->timestamps();
                
                // Performance indexes (Question 1d)
                $table->index('kode_negara', 'idx_perdagangan_negara');
                $table->index('hscode', 'idx_perdagangan_hscode');
                $table->index('tahun', 'idx_perdagangan_tahun');
                $table->index('bulan', 'idx_perdagangan_bulan');
                $table->index(['kode_negara', 'tahun'], 'idx_perdagangan_negara_tahun');
                $table->index(['hscode', 'bulan'], 'idx_perdagangan_hscode_bulan');
                $table->index(['sektor', 'tahun'], 'idx_perdagangan_sektor_tahun');
            });
            $this->line('   • Created table: tabel_perdagangan');
        }

        // Add foreign keys after all tables are created
        if (!$this->foreignKeyExists('tabel_perdagangan', 'tabel_perdagangan_kode_negara_foreign')) {
            Schema::table('tabel_perdagangan', function ($table) {
                $table->foreign('kode_negara')->references('kode_negara')->on('tabel_negara');
                $table->foreign('hscode')->references('hscode')->on('tabel_produk');
            });
            $this->line('   • Added foreign key constraints');
        }
    }

    private function insertTestData()
    {
        // Insert Negara data
        DB::table('tabel_negara')->insertOrIgnore([
            ['kode_negara' => 'IDN', 'negara' => 'INDONESIA'],
            ['kode_negara' => 'MYS', 'negara' => 'MALAYSIA'],
            ['kode_negara' => 'THA', 'negara' => 'THAILAND'],
        ]);
        $this->line('   • Inserted data into tabel_negara');

        // Insert Produk data
        DB::table('tabel_produk')->insertOrIgnore([
            ['hscode' => '301.11', 'label' => 'IKAN AIR TAWAR'],
            ['hscode' => '0301.11.92', 'label' => 'IKAN MAS KOKI'],
            ['hscode' => '302.36.00', 'label' => 'TUNA SIRIP BIRU SELATAN'],
            ['hscode' => '901.11.20', 'label' => 'ARABIKA'],
            ['hscode' => '902.1', 'label' => 'TEH HIJAU'],
            ['hscode' => '902.3', 'label' => 'TEH HITAM'],
        ]);
        $this->line('   • Inserted data into tabel_produk');

        // Insert Perdagangan data (exact data from test)
        DB::table('tabel_perdagangan')->insertOrIgnore([
            ['id_trx' => 'T001', 'kode_negara' => 'IDN', 'hscode' => '0301.11.92', 'sektor' => 'PERIKANAN', 'bulan' => 2, 'tahun' => 2024, 'nilai' => 1500],
            ['id_trx' => 'T002', 'kode_negara' => 'IDN', 'hscode' => '902.1', 'sektor' => 'PERKEBUNAN', 'bulan' => 2, 'tahun' => 2024, 'nilai' => 1100],
            ['id_trx' => 'T003', 'kode_negara' => 'MYS', 'hscode' => '301.11', 'sektor' => 'PERIKANAN', 'bulan' => 3, 'tahun' => 2024, 'nilai' => 900],
            ['id_trx' => 'T004', 'kode_negara' => 'MYS', 'hscode' => '302.36.00', 'sektor' => 'PERIKANAN', 'bulan' => 3, 'tahun' => 2024, 'nilai' => 1600],
            ['id_trx' => 'T005', 'kode_negara' => 'THA', 'hscode' => '902.3', 'sektor' => 'PERKEBUNAN', 'bulan' => 4, 'tahun' => 2024, 'nilai' => 1300],
            ['id_trx' => 'T006', 'kode_negara' => 'THA', 'hscode' => '902.1', 'sektor' => 'PERKEBUNAN', 'bulan' => 5, 'tahun' => 2024, 'nilai' => 1350],
            ['id_trx' => 'T007', 'kode_negara' => 'IDN', 'hscode' => '901.11.20', 'sektor' => 'PERKEBUNAN', 'bulan' => 5, 'tahun' => 2024, 'nilai' => 1250],
        ]);
        $this->line('   • Inserted data into tabel_perdagangan');
    }

    private function verifySetup()
    {
        $negaraCount = DB::table('tabel_negara')->count();
        $produkCount = DB::table('tabel_produk')->count();
        $perdaganganCount = DB::table('tabel_perdagangan')->count();
        
        $this->line("   • tabel_negara: {$negaraCount} records");
        $this->line("   • tabel_produk: {$produkCount} records");
        $this->line("   • tabel_perdagangan: {$perdaganganCount} records");
        
        if ($negaraCount < 3 || $produkCount < 6 || $perdaganganCount < 7) {
            throw new \Exception('Data verification failed - insufficient records');
        }
    }

    private function foreignKeyExists($table, $constraintName)
    {
        try {
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = ? AND CONSTRAINT_NAME = ?
            ", [$table, $constraintName]);
            
            return count($constraints) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function displayUsageInstructions()
    {
        $this->info('📖 Usage Instructions:');
        $this->newLine();
        
        $this->line('1. Test Question 1a (JOIN Query):');
        $this->line('   php artisan tinker');
        $this->line('   >>> App\Models\TabelPerdagangan::getJoinedData()');
        $this->newLine();
        
        $this->line('2. Test Question 1b (GROUP_CONCAT):');
        $this->line('   >>> App\Models\TabelPerdagangan::getAggregatedData()');
        $this->newLine();
        
        $this->line('3. Test Question 1c (Monthly Pivot):');
        $this->line('   >>> App\Models\TabelPerdagangan::getMonthlyPivot()');
        $this->newLine();
        
        $this->line('4. Raw SQL Testing:');
        $this->line('   >>> DB::select("SELECT p.kode_negara, n.negara, p.hscode, pr.label FROM tabel_perdagangan p JOIN tabel_negara n ON p.kode_negara = n.kode_negara JOIN tabel_produk pr ON p.hscode = pr.hscode")');
        $this->newLine();
        
        $this->info('🎯 Perfect for technical interview demonstration!');
        $this->line('   All queries produce exact results shown in the test document.');
    }
}