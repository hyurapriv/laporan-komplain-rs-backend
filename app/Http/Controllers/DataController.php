<?php

namespace App\Http\Controllers;

use App\Models\Data;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DataController extends Controller
{
    public function index()
    {
        $rawData = Data::where('form_id', 3)->get();
        $processedData = $this->processData($rawData);
        $statusCounts = $this->getStatusCounts($processedData);
        $petugasCounts = $this->getPetugasCounts($processedData);
        $unitCounts = $this->getUnitCounts($processedData);

        return view('index', compact('processedData', 'statusCounts', 'petugasCounts','unitCounts'));
    }

    public function download()
    {
        $rawData = Data::where('form_id', 3)->get();
        $processedData = $this->processData($rawData);
        $jsonData = json_encode($processedData, JSON_PRETTY_PRINT);

        $fileName = 'processed_data.json';
        Storage::put('public/' . $fileName, $jsonData);

        return response()->download(storage_path('app/public/' . $fileName))->deleteFileAfterSend(true);
    }

    private function processData($rawDataArray)
    {
        $results = [];

        foreach ($rawDataArray as $data) {
            $parsedJson = $data->json;

            $namaPelapor = '';
            $namaUnit = '';
            $status = $data->status ?? '';
            if (is_array($parsedJson) && !empty($parsedJson) && isset($parsedJson[0])) {
                foreach ($parsedJson[0] as $item) {
                    if (isset($item['name']) && isset($item['value'])) {
                        if ($item['name'] === 'text-1709615631557-0') {
                            $namaPelapor = $item['value'];
                        } elseif ($item['name'] === 'text-1709615712000-0') {
                            $namaUnit = $item['value'];
                        } elseif ($item['name'] === 'Status') {
                            $status = $item['value'];
                        }
                    }
                }
            }

            $results[] = [
                'id' => $data->id,
                'Nama Pelapor' => $namaPelapor,
                'Nama Petugas' => $this->normalizePetugasNames($data->petugas),
                'created_at' => $this->formatDateTime($data->created_at),
                'datetime_masuk' => $this->formatDateTime($data->datetime_masuk),
                'datetime_pengerjaan' => $this->formatDateTime($data->datetime_pengerjaan),
                'datetime_selesai' => $this->formatDateTime($data->datetime_selesai),
                'status' => $status,
                'is_pending' => $data->is_pending,
                'Nama Unit/Poli' => $namaUnit
            ];
        }

        return $results;
    }

    private function normalizePetugasNames($petugas)
    {
        $replacements = [
            'Adi' => 'Adika',
            'Adika Wicaksana' => 'Adika',
            'Adikaka' => 'Adika',
            'adikaka' => 'Adika',
            'dika' => 'Adika',
            'dikq' => 'Adika',
            'AAdika' => 'Adika',
            'virgie' => 'Virgie',
        ];

        foreach ($replacements as $oldName => $newName) {
            $petugas = str_replace($oldName, $newName, $petugas);
        }

        return $petugas;
    }

    private function getPetugasCounts($processedData)
    {
        $petugasCounts = [
            'Ganang' => 0,
            'Agus' => 0,
            'Ali Muhson' => 0,
            'Virgie' => 0,
            'Bayu' => 0,
            'Adika' => 0,
        ];

        foreach ($processedData as $data) {
            // Mengambil nama petugas dan menghilangkan spasi berlebih
            $petugasList = array_map('trim', explode(',', $data['Nama Petugas']));
            $uniquePetugas = array_unique($petugasList);

            foreach ($uniquePetugas as $petugas) {
                if (isset($petugasCounts[$petugas])) {
                    $petugasCounts[$petugas]++;
                }
            }
        }

        return $petugasCounts;
    }

    private function getStatusCounts($processedData)
    {
        $statusCounts = [
            'pending' => 0,
            'Selesai' => 0,
            // tambahkan status lainnya jika ada
        ];

        foreach ($processedData as $data) {
            if ($data['is_pending']) {
                if ($data['status'] === 'Selesai') {
                    $statusCounts['Selesai']++;
                } elseif ($data['status'] === 'Dalam Pengerjaan' || $data['status'] === 'Pengecekan Petugas') {
                    $statusCounts['pending']++;
                } else {
                    $statusCounts['pending']++;
                }
            } else {
                if (isset($statusCounts[$data['status']])) {
                    $statusCounts[$data['status']]++;
                } else {
                    $statusCounts[$data['status']] = 1;
                }
            }
        }

        return $statusCounts;
    }

    private function getunitCounts($processedData)
    {
        $unitCounts = [
            'Farmasi' => 0,
            'LABORATORIUM & PELAYANAN DARAH' => 0,
            'IBS' => 0,
        ];

        foreach ($processedData as $data) {
            if (isset($data['Nama Unit/Poli'])) {
                $unit = $data['Nama Unit/Poli'];
                if (!empty($unit)) {
                    if (isset($unitCounts[$unit])) {
                        $unitCounts[$unit]++;
                    } else {
                        $unitCounts[$unit] = 1;
                    }
                }
            }
        }

        return $unitCounts;
    }

    private function formatDateTime($dateTime)
    {
        if ($dateTime instanceof \Carbon\Carbon) {
            return $dateTime->toDateTimeString();
        } elseif (is_string($dateTime)) {
            return $dateTime;
        }
        return null;
    }
}
