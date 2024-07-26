<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class KomplainResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'Nama Pelapor' => $this->NamaPelapor,
            'Nama Petugas' => $this->NamaPetugas,
            'created_at' => $this->created_at,
            'datetime_masuk' => $this->datetime_masuk,
            'datetime_pengerjaan' => $this->datetime_pengerjaan,
            'datetime_selesai' => $this->datetime_selesai,
            'status' => $this->status,
            'is_pending' => $this->is_pending,
            'Nama Unit/Poli' => $this->NamaUnitPoli,
            'respon_time' => $this->respon_time,
            'respon_time_minutes' => $this->respon_time_minutes,
        ];
    }
}
