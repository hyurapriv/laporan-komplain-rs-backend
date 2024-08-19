<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NewDataController extends Controller
{
    private $petugasList = ['Ganang', 'Agus', 'Ali Muhson', 'Virgie', 'Bayu', 'Adika'];

    public function getComplaintData(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        $data = DB::table('form_values')
            ->where('form_id', 3)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->select('id', 'json', 'status', 'datetime_masuk', 'datetime_pengerjaan', 'datetime_selesai', 'petugas', 'is_pending')
            ->get();

        $processedData = $data->map(function ($item) {
            $jsonData = json_decode($item->json, true)[0] ?? []; // Menangani kemungkinan null
            
            $unitData = collect($jsonData)->firstWhere('name', 'select-1722845859503-0');
            $unitValue = $unitData ? collect($unitData['values'])->firstWhere('selected', 1)['label'] : null;

            return [
                'id' => $item->id,
                'nama_pelapor' => $this->getValueFromJson($jsonData, 'text-1709615631557-0'),
                'unit' => $unitValue,
                'status' => $item->status,
                'datetime_masuk' => $item->datetime_masuk,
                'datetime_pengerjaan' => $item->datetime_pengerjaan,
                'datetime_selesai' => $item->datetime_selesai,
                'petugas' => $this->normalizePetugasNames($item->petugas),
                'is_pending' => $item->is_pending,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $processedData,
            'petugasCounts' => $this->getPetugasCounts($processedData),
        ]);
    }

    private function getValueFromJson($jsonData, $key)
    {
        $item = collect($jsonData)->firstWhere('name', $key);
        return $item ? $item['value'] : null;
    }

    private function normalizePetugasNames($petugas)
    {
        if (empty($petugas)) {
            return null; // Menangani kasus null atau kosong
        }

        $replacements = [
            'Adi' => 'Adika',
            'Adika Wicaksana' => 'Adika',
            'Adikaka' => 'Adika',
            'adikaka' => 'Adika',
            'dika' => 'Adika',
            'Dika' => 'Adika',
            'dikq' => 'Adika',
            'Dikq' => 'Adika',
            'AAdika' => 'Adika',
            'virgie' => 'Virgie',
            'Vi' => 'Virgie',
            'vi' => 'Virgie',
            'Virgie Dika' => 'Virgie, Adika',
            'Virgie dikq' => 'Virgie, Adika',
        ];

        $petugasList = preg_split('/\s*[,&]\s*|\s+dan\s+/i', $petugas);
        $normalizedList = array_map(function($name) use ($replacements) {
            $normalizedName = $replacements[trim($name)] ?? trim($name);
            return in_array($normalizedName, $this->petugasList) ? $normalizedName : 'Lainnya';
        }, $petugasList);

        return implode(', ', array_unique($normalizedList));
    }

    private function getPetugasCounts($processedData)
    {
        $petugasCounts = array_fill_keys($this->petugasList, 0);
        $petugasCounts['Lainnya'] = 0;

        foreach ($processedData as $data) {
            if (empty($data['petugas'])) {
                continue; // Abaikan data dengan petugas null atau kosong
            }

            $petugasList = array_unique(explode(', ', $data['petugas']));
            foreach ($petugasList as $petugas) {
                if (in_array($petugas, $this->petugasList)) {
                    $petugasCounts[$petugas]++;
                } elseif (!empty($petugas)) {
                    $petugasCounts['Lainnya']++;
                }
            }
        }

        // Hapus kategori 'Lainnya' jika tidak ada data yang cocok
        if ($petugasCounts['Lainnya'] === 0) {
            unset($petugasCounts['Lainnya']);
        }

        return array_filter($petugasCounts);
    }
}
