<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormValueUpdate extends Model
{
    use HasFactory;

    protected $table = 'form_values';

    protected $fillable = [
        'json',
        'datetime_pengerjaan',
        'datetime_selesai',
        'petugas',
        'is_pending',
        'form_id',
        'created_at',
    ];

    public $timestamps = true;

    // Scope untuk mengambil form_id = 4
    public function scopeWithFormIdFour($query)
    {
        return $query->where('form_id', 4);
    }
}
