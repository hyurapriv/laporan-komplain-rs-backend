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

    public static function getStatusFromJson($formId)
    {
        // Mendapatkan nilai JSON dari kolom 'json' dalam tabel 'form_values'
        $jsonValue = self::where('id', $formId)->value('json');

        // Dekode JSON menjadi array asosiatif
        $decodedJson = json_decode($jsonValue, true);

        // Jika tidak ada status dalam JSON, kembalikan null
        if (!isset($decodedJson['status'])) {
            return null;
        }

        // Mendapatkan enum berdasarkan nilai status dari JSON
        $statusKey = Status::fromValue($decodedJson['status']);

        return $statusKey;
    }
}

// Enum Status yang sudah didefinisikan sebelumnya
enum Status: string
{
    const VALUES = [
        'DRAFT' => 'Draft',
        'IN_PROGRESS' => 'Dalam Pengerjaan',
        'CHECKING' => 'Pengecekan Petugas',
        'COMPLETED' => 'Selesai',
        'TERKIRIM' => 'Terkirim'
    ];

    public static function fromValue(string $value): ?string
    {
        $key = array_search($value, self::VALUES);
        return $key !== false ? $key : null;
    }

    public static function label(string $key): ?string
    {
        return self::VALUES[$key] ?? null;
    }
}
