<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class Data extends Model
{
    use HasFactory;

    protected $table = 'form_values';

    protected $fillable = [
        'id',
        'json',
        'petugas',
        'created_at',
        'datetime_masuk',
        'datetime_pengerjaan',
        'datetime_selesai',
        'status',
        'is_pending'
    ];

    protected $casts = [
        'json' => 'array'
    ];

    protected $dates = [
        'created_at',
        'datetime_masuk',
        'datetime_pengerjaan',
        'datetime_selesai',
    ];

    public static function countStatusForForm3()
    {
        $statusCounts = self::where('form_id', 3)
            ->select(
                DB::raw('SUM(CASE WHEN status = "terkirim" THEN 1 ELSE 0 END) as terkirim'),
                DB::raw('SUM(CASE WHEN status = "Dalam Pengerjaan / Pengecekan Petugas" THEN 1 ELSE 0 END) as dalam_pengerjaan'),
                DB::raw('SUM(CASE WHEN status = "selesai" THEN 1 ELSE 0 END) as selesai'),
                DB::raw('SUM(CASE WHEN is_pending = 1 THEN 1 ELSE 0 END) as pending')
            )
            ->first();

        $result = array_map('intval', $statusCounts->toArray());
        $result['total'] = array_sum($result);

        return $result;
    }
}