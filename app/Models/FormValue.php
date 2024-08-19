<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormValue extends Model
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

    // Scope untuk mengambil form_id = 3
    public function scopeWithFormIdThree($query)
    {
        return $query->where('form_id', 3);
    }
}
