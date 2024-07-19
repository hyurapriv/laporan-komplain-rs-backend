<?php

namespace App\Http\Controllers;

use App\Models\Data;
use Illuminate\Support\Facades\Storage;

class DataController extends Controller
{
    public function index()
    {
        $processedData = $this->getProcessedData();
        $statusCounts = $this->getStatusCounts($processedData);
        $petugasCounts = $this->getPetugasCounts($processedData);
        $unitCounts = $this->getCountsByKey($processedData, 'Nama Unit/Poli');

        return view('index', compact('processedData', 'statusCounts', 'petugasCounts', 'unitCounts'));
    }

    public function download()
    {
        $processedData = $this->getProcessedData();
        $fileName = 'processed_data.json';
        Storage::put('public/' . $fileName, json_encode($processedData, JSON_PRETTY_PRINT));

        return response()->download(storage_path('app/public/' . $fileName))->deleteFileAfterSend(true);
    }

    private function getProcessedData()
    {
        return Data::where('form_id', 3)->get()->map(function ($data) {
            $parsedJson = $data->json[0] ?? [];
            $extractedData = $this->extractDataFromJson($parsedJson);

            return [
                'id' => $data->id,
                'Nama Pelapor' => $extractedData['namaPelapor'],
                'Nama Petugas' => $this->normalizePetugasNames($data->petugas),
                'created_at' => $this->formatDateTime($data->created_at),
                'datetime_masuk' => $this->formatDateTime($data->datetime_masuk),
                'datetime_pengerjaan' => $this->formatDateTime($data->datetime_pengerjaan),
                'datetime_selesai' => $this->formatDateTime($data->datetime_selesai),
                'status' => $extractedData['status'] ?? $data->status ?? '',
                'is_pending' => $data->is_pending,
                'Nama Unit/Poli' => $extractedData['namaUnit']
            ];
        })->toArray();
    }

    private function extractDataFromJson($parsedJson)
    {
        $data = ['namaPelapor' => '', 'namaUnit' => '', 'status' => ''];
        foreach ($parsedJson as $item) {
            if (isset($item['name'], $item['value'])) {
                switch ($item['name']) {
                    case 'text-1709615631557-0':
                        $data['namaPelapor'] = $item['value'];
                        break;
                    case 'text-1709615712000-0':
                        $data['namaUnit'] = $item['value'];
                        break;
                    case 'Status':
                        $data['status'] = $item['value'];
                        break;
                }
            }
        }
        return $data;
    }

    private function normalizePetugasNames($petugas)
    {
        $replacements = [
            'Adi' => 'Adika', 'Adika Wicaksana' => 'Adika', 'Adikaka' => 'Adika',
            'adikaka' => 'Adika', 'dika' => 'Adika', 'Dika' => 'Adika',
            'dikq' => 'Adika', 'Dikq' => 'Adika', 'AAdika' => 'Adika',
            'virgie' => 'Virgie', 'Vi' => 'Virgie', 'vi' => 'Virgie',
            'Virgie Dika' => 'Virgie, Adika', 'Virgie dikq' => 'Virgie, Adika',
        ];

        $petugasList = preg_split('/\s*[,&]\s*|\s+dan\s+/i', $petugas);
        $normalizedList = array_map(function($name) use ($replacements) {
            return $replacements[trim($name)] ?? trim($name);
        }, $petugasList);

        return implode(', ', array_unique($normalizedList));
    }

    private function getPetugasCounts($processedData)
    {
        $petugasCounts = array_fill_keys(['Ganang', 'Agus', 'Ali Muhson', 'Virgie', 'Bayu', 'Adika'], 0);

        foreach ($processedData as $data) {
            $petugasList = array_unique(explode(', ', $data['Nama Petugas']));
            foreach ($petugasList as $petugas) {
                if (isset($petugasCounts[$petugas])) {
                    $petugasCounts[$petugas]++;
                }
            }
        }

        return array_filter($petugasCounts);
    }

    private function getStatusCounts($processedData)
    {
        $statusCounts = ['pending' => 0, 'Selesai' => 0];

        foreach ($processedData as $data) {
            if ($data['is_pending']) {
                if ($data['status'] === 'Selesai') {
                    $statusCounts['Selesai']++;
                } else {
                    $statusCounts['pending']++;
                }
            } else {
                $status = $data['status'];
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            }
        }

        return $statusCounts;
    }

    private function getCountsByKey($processedData, $key)
    {
        $counts = [];
        foreach ($processedData as $data) {
            $value = $data[$key] ?? '';
            if (!empty($value)) {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }
        return $counts;
    }

    private function formatDateTime($dateTime)
    {
        return $dateTime instanceof \Carbon\Carbon ? $dateTime->toDateTimeString() : $dateTime;
    }
}