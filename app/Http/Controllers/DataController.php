<?php

namespace App\Http\Controllers;

use App\Models\Data;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class DataController extends Controller
{
    public function index()
    {
        $rawData = Data::where('form_id', 3)->limit(300)->get();
        $processedData = $this->processData($rawData);
        $statusCounts = Data::countStatusForForm3();

        return view('index', compact('processedData', 'statusCounts'));
    }

    public function download()
    {
        $rawData = Data::where('form_id', 3)->limit(200)->get();
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
            $statusFromJson = $this->getValueFromJson($parsedJson, 'status');
            $statusEnum = $statusFromJson ? Data::getStatusFromJson($data->id) : null;

            $namaPelapor = '';
            $namaUnit = '';
            $status = $data->status ?? $statusEnum;

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
                'Nama Petugas' => $data->petugas,
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

    private function getValueFromJson($jsonData, $key)
    {
        // Memastikan nilai yang diberikan adalah string sebelum melakukan json_decode
        if (is_string($jsonData)) {
            $decodedJson = json_decode($jsonData, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedJson[$key] ?? null;
            }
        }
        return null;
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
