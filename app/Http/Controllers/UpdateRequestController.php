<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UpdateRequestController extends Controller
{
    private const PETUGAS_LIST = ['Ganang', 'Agus', 'Ali Muhson', 'Virgie', 'Bayu', 'Adika'];
    private const STATUS_LIST = ['Pending', 'Dalam Pengerjaan / Pengecekan Petugas', 'Terkirim', 'Selesai'];
    private const PETUGAS_REPLACEMENTS = [
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
        'ali' => 'Ali Muhson',
        'muhson' => 'Ali Muhson',
    ];

    public function getUpdateRequestData(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->format('m'));

        $data = $this->fetchUpdateRequestData($year, $month);
        $result = $this->processUpdateRequestData($data);
        $availableMonths = $this->getAvailableMonths($year);
        $detailData = $this->getDetailUpdateData($data);

        Log::info('Detail Data:', [
            'detailDataTerkirim' => $detailData['updateDataTerkirim'],
            'detailDataProses' => $detailData['updateDataProses'],
            'detailDataPending' => $detailData['updateDataPending']
        ]);

        return response()->json([
            'success' => true,
            'data' => $result,
            'availableMonths' => $availableMonths,
            'detailDataTerkirim' => $detailData['updateDataTerkirim'],
            'detailDataProses' => $detailData['updateDataProses'],
            'detailDataPending' => $detailData['updateDataPending'],
        ]);
    }

    private function fetchUpdateRequestData($year, $month)
    {
        return DB::table('form_values')
            ->where('form_id', 4)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->select('id', 'json', 'status', 'datetime_masuk', 'datetime_pengerjaan', 'datetime_selesai', 'petugas', 'is_pending')
            ->get();
    }

    private function processUpdateRequestData(Collection $data)
    {
        $petugasCounts = array_fill_keys(self::PETUGAS_LIST, 0);
        $totalStatus = array_fill_keys(self::STATUS_LIST, 0);
        $totalRequests = 0;
        $dailyRequests = [];
        $responseTimes = [];
        $completedResponseTimes = [];

        foreach ($data as $item) {
            $jsonData = json_decode($item->json, true)[0] ?? [];

            $reporterName = $this->getValueFromJson($jsonData, 'Nama');
            if (strtolower(trim($reporterName)) === 'tes') {
                continue;
            }

            $status = $this->getFinalStatus($item);

            $totalStatus[$status]++;
            $totalRequests++;

            if ($status !== 'Terkirim') {
                $petugasList = $this->normalizePetugasNames($item->petugas);
                
                Log::info('Petugas List for Item ID ' . $item->id . ': ', ['petugasList' => $petugasList]);
                
                foreach ($petugasList as $petugas) {
                    if (isset($petugasCounts[$petugas])) {
                        $petugasCounts[$petugas]++;
                    }
                }
            }

            $date = Carbon::parse($item->datetime_masuk)->format('Y-m-d');
            $dailyRequests[$date] = ($dailyRequests[$date] ?? 0) + 1;

            $this->calculateResponseTimes($item, $responseTimes, $completedResponseTimes);
        }

        $averageResponseTime = $this->calculateAverageResponseTime($responseTimes);
        $averageCompletedResponseTime = $this->calculateAverageResponseTime($completedResponseTimes);

        return compact('petugasCounts', 'totalStatus', 'totalRequests', 'dailyRequests', 'averageResponseTime', 'averageCompletedResponseTime');
    }

    private function getFinalStatus($item)
    {
        $status = $this->getValueFromJson(json_decode($item->json, true)[0] ?? [], 'Status');
        return $item->is_pending == 1 && in_array($status, ['Dalam Pengerjaan / Pengecekan Petugas', 'Terkirim'])
            ? 'Pending'
            : $status;
    }

    private function getValueFromJson($jsonData, $key)
    {
        $item = collect($jsonData)->firstWhere('name', $key);
        return $item ? $item['value'] : null;
    }

    private function normalizePetugasNames($petugas)
    {
        if (empty($petugas)) return [];

        // First, split by obvious separators
        $roughSplit = preg_split('/\s*[,&+]\s*|\s+dan\s+/i', $petugas);

        $normalizedList = [];
        foreach ($roughSplit as $namePart) {
            $words = preg_split('/\s+/', trim($namePart));
            $currentName = '';
            foreach ($words as $word) {
                $currentName .= ($currentName ? ' ' : '') . $word;
                $normalizedName = $this->normalizeSingleName($currentName);
                if ($normalizedName) {
                    $normalizedList[] = $normalizedName;
                    $currentName = '';
                }
            }
            // Check if there's any remaining part of the name
            if ($currentName) {
                $normalizedName = $this->normalizeSingleName($currentName);
                if ($normalizedName) {
                    $normalizedList[] = $normalizedName;
                }
            }
        }

        // Remove duplicates
        return array_unique($normalizedList);
    }

    private function normalizeSingleName($name)
    {
        $lowerName = strtolower(trim($name));

        // Check PETUGAS_REPLACEMENTS first
        foreach (self::PETUGAS_REPLACEMENTS as $key => $replacement) {
            if (strpos($lowerName, strtolower($key)) !== false) {
                return $replacement;
            }
        }

        // Then check PETUGAS_LIST
        foreach (self::PETUGAS_LIST as $validPetugas) {
            if (strtolower($validPetugas) === $lowerName) {
                return $validPetugas;
            }
        }

        return null; // Return null for unrecognized names
    }

    private function getAvailableMonths($year)
    {
        return DB::table('form_values')
            ->where('form_id', 4)
            ->whereYear('created_at', $year)
            ->selectRaw('DISTINCT DATE_FORMAT(created_at, "%Y-%m") as month')
            ->orderBy('month', 'desc')
            ->pluck('month')
            ->toArray();
    }

    private function calculateResponseTimes($item, &$responseTimes, &$completedResponseTimes)
    {
        if (!empty($item->datetime_masuk) && !empty($item->datetime_pengerjaan)) {
            $responseTime = Carbon::parse($item->datetime_masuk)->diffInMinutes(Carbon::parse($item->datetime_pengerjaan));
            $responseTimes[] = $responseTime;
        }

        if (!empty($item->datetime_masuk) && !empty($item->datetime_selesai)) {
            $completedResponseTime = Carbon::parse($item->datetime_masuk)->diffInMinutes(Carbon::parse($item->datetime_selesai));
            $completedResponseTimes[] = $completedResponseTime;
        }
    }

    private function calculateAverageResponseTime($responseTimes)
    {
        if (count($responseTimes) === 0) {
            return null;
        }
        return round(array_sum($responseTimes) / count($responseTimes), 2);
    }

    private function getDetailUpdateData(Collection $data)
    {
        $updateDataTerkirim = [];
        $updateDataProses = [];
        $updateDataPending = [];

        foreach ($data as $item) {
            $jsonData = json_decode($item->json, true);

            $nama_pelapor = $this->findReporterName($jsonData);

            Log::info("Processing item ID: " . $item->id . ", Nama Pelapor: " . $nama_pelapor);

            if (strtolower(trim($nama_pelapor)) === 'tes') {
                continue;
            }

            $petugasList = $this->normalizePetugasNames($item->petugas);

            $detailItem = [
                'id' => $item->id,
                'namaPelapor' => $nama_pelapor,
                'petugas' => implode(', ', $petugasList),
                'datetime_masuk' => $item->datetime_masuk,
            ];

            $status = $this->getFinalStatus($item);
            
            if ($status === 'Terkirim') {
                $updateDataTerkirim[] = $detailItem;
            } elseif ($status === 'Dalam Pengerjaan / Pengecekan Petugas') {
                $detailItem['datetime_pengerjaan'] = $item->datetime_pengerjaan;
                $updateDataProses[] = $detailItem;
            } elseif ($status === 'Pending') {
                $detailItem['datetime_pengerjaan'] = $item->datetime_pengerjaan;
                $updateDataPending[] = $detailItem;
            }
        }

        return [
            'updateDataTerkirim' => $updateDataTerkirim,
            'updateDataProses' => $updateDataProses,
            'updateDataPending' => $updateDataPending,
        ];
    }

    private function findReporterName($jsonData)
    {
        $search = function ($data) use (&$search) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if ($key === 'label' && $value === 'Nama' && isset($data['value'])) {
                        return $data['value'];
                    }
                    if (is_array($value)) {
                        $result = $search($value);
                        if ($result !== null) {
                            return $result;
                        }
                    }
                }
            }
            return null;
        };

        $result = $search($jsonData);
        return $result !== null ? $result : 'N/A';
    }
}