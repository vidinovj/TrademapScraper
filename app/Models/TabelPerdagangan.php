<?php
// app/Models/TabelPerdagangan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TabelPerdagangan extends Model
{
    protected $table = 'tabel_perdagangan';
    protected $primaryKey = 'id_trx';
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'id_trx',
        'kode_negara',
        'hscode',
        'sektor',
        'bulan',
        'tahun',
        'nilai'
    ];

    protected $casts = [
        'bulan' => 'integer',
        'tahun' => 'integer',
        'nilai' => 'decimal:2'
    ];

    public function negara()
    {
        return $this->belongsTo(TabelNegara::class, 'kode_negara', 'kode_negara');
    }

    public function produk()
    {
        return $this->belongsTo(TabelProduk::class, 'hscode', 'hscode');
    }

    // Question 1a: JOIN query method
    public static function getJoinedData()
    {
        return self::select([
            'tabel_perdagangan.kode_negara as Kode_Negara',
            'tabel_negara.negara as Nama_Negara',
            'tabel_perdagangan.hscode as HsCode',
            'tabel_produk.label as Label',
            'tabel_perdagangan.sektor as Sektor',
            'tabel_perdagangan.bulan as Bulan',
            'tabel_perdagangan.tahun as Tahun',
            'tabel_perdagangan.nilai as Nilai'
        ])
        ->join('tabel_negara', 'tabel_perdagangan.kode_negara', '=', 'tabel_negara.kode_negara')
        ->join('tabel_produk', 'tabel_perdagangan.hscode', '=', 'tabel_produk.hscode')
        ->orderBy('tabel_perdagangan.kode_negara')
        ->orderBy('tabel_perdagangan.hscode')
        ->get();
    }

    // Question 1b: GROUP_CONCAT equivalent
    public static function getAggregatedData()
    {
        return DB::table('tabel_perdagangan as p')
            ->select([
                'n.kode_negara as Kode',
                'n.negara as Nama_Negara',
                DB::raw('GROUP_CONCAT(DISTINCT p.hscode ORDER BY p.hscode SEPARATOR ", ") as HsCode'),
                DB::raw('GROUP_CONCAT(DISTINCT pr.label ORDER BY p.hscode SEPARATOR ", ") as Label')
            ])
            ->join('tabel_negara as n', 'p.kode_negara', '=', 'n.kode_negara')
            ->join('tabel_produk as pr', 'p.hscode', '=', 'pr.hscode')
            ->groupBy('n.kode_negara', 'n.negara')
            ->orderBy('n.negara')
            ->get();
    }

    // Question 1c: Monthly pivot
    public static function getMonthlyPivot()
    {
        return DB::table('tabel_perdagangan as p')
            ->select([
                'n.negara as Negara',
                'p.hscode as HS_Code',
                'pr.label as Label',
                DB::raw('SUM(CASE WHEN p.bulan = 1 THEN p.nilai ELSE 0 END) as Januari'),
                DB::raw('SUM(CASE WHEN p.bulan = 2 THEN p.nilai ELSE 0 END) as Febuari'),
                DB::raw('SUM(CASE WHEN p.bulan = 3 THEN p.nilai ELSE 0 END) as Maret'),
                DB::raw('SUM(CASE WHEN p.bulan = 4 THEN p.nilai ELSE 0 END) as April'),
                DB::raw('SUM(CASE WHEN p.bulan = 5 THEN p.nilai ELSE 0 END) as Mei')
            ])
            ->join('tabel_negara as n', 'p.kode_negara', '=', 'n.kode_negara')
            ->join('tabel_produk as pr', 'p.hscode', '=', 'pr.hscode')
            ->groupBy('n.negara', 'p.hscode', 'pr.label')
            ->orderBy('n.negara')
            ->orderBy('p.hscode')
            ->get();
    }
}