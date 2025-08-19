<?php
// app/Models/TbTrade.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TbTrade extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'tb_trade';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'negara',
        'kode_hs', 
        'label',
        'tahun',
        'jumlah',
        'satuan',
        'sumber_data',
        'scraped_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'tahun' => 'integer',
        'jumlah' => 'decimal:2',
        'scraped_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Scope for filtering by country
     */
    public function scopeByCountry($query, string $country)
    {
        return $query->where('negara', $country);
    }

    /**
     * Scope for filtering by year
     */
    public function scopeByYear($query, int $year)
    {
        return $query->where('tahun', $year);
    }

    /**
     * Scope for filtering by HS code
     */
    public function scopeByHsCode($query, string $hsCode)
    {
        return $query->where('kode_hs', $hsCode);
    }

    /**
     * Get formatted trade value
     */
    public function getFormattedJumlahAttribute(): string
    {
        return number_format($this->jumlah, 2);
    }

    /**
     * Get data for Question 1a format (JOIN equivalent)
     * This demonstrates how to get the same result as SQL JOIN
     */
    public static function getFormattedTradeData()
    {
        return self::select([
            'negara as Nama_Negara',
            'kode_hs as HsCode', 
            'label as Label',
            'tahun as Tahun',
            'jumlah as Nilai'
        ])
        ->orderBy('negara')
        ->orderBy('kode_hs')
        ->orderBy('tahun')
        ->get();
    }

    /**
     * Get monthly pivot data (Question 1c equivalent)
     * This would need additional month field for proper implementation
     */
    public static function getMonthlyPivotData()
    {
        return self::selectRaw('
            negara as Negara,
            kode_hs as "HS Code", 
            label as Label,
            SUM(CASE WHEN MONTH(created_at) = 1 THEN jumlah ELSE 0 END) as Januari,
            SUM(CASE WHEN MONTH(created_at) = 2 THEN jumlah ELSE 0 END) as Febuari,
            SUM(CASE WHEN MONTH(created_at) = 3 THEN jumlah ELSE 0 END) as Maret,
            SUM(CASE WHEN MONTH(created_at) = 4 THEN jumlah ELSE 0 END) as April,
            SUM(CASE WHEN MONTH(created_at) = 5 THEN jumlah ELSE 0 END) as Mei
        ')
        ->groupBy('negara', 'kode_hs', 'label')
        ->orderBy('negara')
        ->get();
    }

    /**
     * Get summary statistics for dashboard
     */
    public static function getSummaryStats(): array
    {
        $stats = self::selectRaw('
            COUNT(*) as total_records,
            COUNT(DISTINCT negara) as total_countries,
            COUNT(DISTINCT kode_hs) as total_hs_codes,
            COUNT(DISTINCT tahun) as total_years,
            SUM(jumlah) as total_value,
            AVG(jumlah) as avg_value,
            MAX(scraped_at) as last_update
        ')->first();

        return [
            'total_records' => $stats->total_records ?? 0,
            'total_countries' => $stats->total_countries ?? 0, 
            'total_hs_codes' => $stats->total_hs_codes ?? 0,
            'total_years' => $stats->total_years ?? 0,
            'total_value' => number_format($stats->total_value ?? 0, 2),
            'avg_value' => number_format($stats->avg_value ?? 0, 2),
            'last_update' => $stats->last_update ?? null
        ];
    }

    /**
     * Get top products by value
     */
    public static function getTopProducts(int $limit = 10)
    {
        return self::select('kode_hs', 'label')
            ->selectRaw('SUM(jumlah) as total_value, COUNT(*) as record_count')
            ->groupBy('kode_hs', 'label')
            ->orderByDesc('total_value')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trade data by year for charts
     */
    public static function getYearlyData()
    {
        return self::select('tahun')
            ->selectRaw('SUM(jumlah) as total_value, COUNT(*) as record_count')
            ->groupBy('tahun')
            ->orderBy('tahun')
            ->get();
    }
}