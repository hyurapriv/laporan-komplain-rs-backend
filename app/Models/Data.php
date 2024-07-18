<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
                DB::raw('SUM(CASE WHEN status = "Terkirim" THEN 1 ELSE 0 END) as terkirim'),
                DB::raw('SUM(CASE WHEN status = "Dalam Pengerjaan / Pengecekan Petugas" THEN 1 ELSE 0 END) as dalam_pengerjaan'),
                DB::raw('SUM(CASE WHEN status = "Selesai" THEN 1 ELSE 0 END) as selesai'),
                DB::raw('SUM(CASE WHEN is_pending = 1 THEN 1 ELSE 0 END) as pending')
            )
            ->first()
            ->toArray(); 

        // Menambahkan log untuk status counts
        Log::info('Status Counts:', $statusCounts);

        // Pastikan semua kunci yang diharapkan ada dalam array
        $statusCounts = array_merge([
            'terkirim' => 0,
            'dalam_pengerjaan' => 0,
            'selesai' => 0,
            'pending' => 0,
        ], $statusCounts);

        Log::info('Merged Status Counts:', $statusCounts);

        return $statusCounts;
    }
}
