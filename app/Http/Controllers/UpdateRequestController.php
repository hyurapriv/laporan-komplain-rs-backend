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
        'Virgie Dika' => 'Virgie, Adika',
        'Virgie dikq' => 'Virgie, Adika',
    ];

    public function getUpdateRequestData(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->format('m'));

        // Ambil data dari database
        $data = $this->fetchUpdateRequestData($year, $month);

        // Proses dan format data
        $result = $this->processUpdateRequestData($data);

        // Dapatkan bulan yang tersedia
        $availableMonths = $this->getAvailableMonths($year);

        // Dapatkan data detail update
        $detailData = $this->getDetailUpdateData($data);

        // Log data detail untuk debugging
        Log::info('Detail Data:', [
            'detailDataTerkirim' => $detailData['updateDataTerkirim'],
            'detailDataProses' => $detailData['updateDataProses'],
            'detailDataPending' => $detailData['updateDataPending']
        ]);

        // Kembalikan respons JSON
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
            ->where('form_id', 4) // Assuming form_id 4 is for update requests
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

        $reporterName = $this->getValueFromJson($jsonData, 'Nama (Yang Membuat Laporan)');
        if (strtolower(trim($reporterName)) === 'tes') {
            continue;
        }

        $status = $this->getFinalStatus($item);

        $normalizedPetugas = $this->normalizePetugasNames($item->petugas);
        $petugasList = array_filter(explode(', ', $normalizedPetugas), function($petugas) {
            return in_array($petugas, self::PETUGAS_LIST);
        });
        
        if (empty($petugasList)) {
            continue;
        }

        $petugasCount = count($petugasList);
        foreach ($petugasList as $petugas) {
            $petugasCounts[$petugas] += (1 / $petugasCount);
        }
        
        $totalStatus[$status]++;
        $totalRequests++;

        $date = Carbon::parse($item->datetime_masuk)->format('Y-m-d');
        $dailyRequests[$date] = ($dailyRequests[$date] ?? 0) + 1;

        $this->calculateResponseTimes($item, $responseTimes, $completedResponseTimes);
    }

    // Round the petugas counts to whole numbers
    $petugasCounts = array_map(function($count) {
        return round($count);
    }, $petugasCounts);

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

    private function updatePetugasCounts(&$petugasCounts, $petugas)
    {
        $petugasList = array_unique(explode(', ', $this->normalizePetugasNames($petugas)));
        foreach ($petugasList as $petugas) {
            if (in_array($petugas, self::PETUGAS_LIST)) {
                $petugasCounts[$petugas]++;
            }
        }
    }

    private function getValueFromJson($jsonData, $key)
    {
        $item = collect($jsonData)->firstWhere('name', $key);
        return $item ? $item['value'] : null;
    }

    private function normalizePetugasNames($petugas)
{
    if (empty($petugas)) return null;

    $petugasList = preg_split('/\s*[,&]\s*|\s+dan\s+/i', $petugas);

    $normalizedList = array_map(function ($name) {
        $normalizedName = strtolower(trim($name));

        $finalName = null;
        foreach (self::PETUGAS_REPLACEMENTS as $key => $replacement) {
            if (strtolower(trim($key)) === $normalizedName) {
                $finalName = $replacement;
                break;
            }
        }

        if (!$finalName) {
            $finalName = ucwords($normalizedName);
        }

        return in_array($finalName, self::PETUGAS_LIST) ? $finalName : 'Lainnya';
    }, $petugasList);

    return implode(', ', array_unique($normalizedList));
}

    private function getAvailableMonths($year)
    {
        $availableMonths = DB::table('form_values')
            ->where('form_id', 4) // Pastikan ini adalah form_id yang benar untuk update requests
            ->whereYear('created_at', $year)
            ->selectRaw('DISTINCT DATE_FORMAT(created_at, "%Y-%m") as month')
            ->orderBy('month')
            ->pluck('month')
            ->toArray();

        return $availableMonths;
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
            // Decode JSON data
            $jsonData = json_decode($item->json, true);
    
            $nama_pelapor = 'N/A';
            if (is_array($jsonData)) {
                // Coba cari nama pelapor di struktur JSON yang berbeda
                $nama_pelapor = $this->findReporterName($jsonData);
            }
    
            // Log untuk debugging
            Log::info("Processing item ID: " . $item->id . ", Nama Pelapor: " . $nama_pelapor);
    
            // Skip data where the reporter name is 'tes'
            if (strtolower(trim($nama_pelapor)) === 'tes') {
                continue;
            }
    
            // Create detail item
            $detailItem = [
                'id' => $item->id,
                'namaPelapor' => $nama_pelapor,
                'petugas' => $this->normalizePetugasNames($item->petugas),
                'datetime_masuk' => $item->datetime_masuk,
            ];
    
            // Determine status category
            if ($item->datetime_selesai === null && $item->petugas === null) {
                $updateDataTerkirim[] = $detailItem;
            } elseif ($item->datetime_selesai === null && $item->petugas !== null && !$item->is_pending) {
                $detailItem['datetime_pengerjaan'] = $item->datetime_pengerjaan;
                $updateDataProses[] = $detailItem;
            } elseif ($item->datetime_selesai === null && $item->petugas !== null && $item->is_pending) {
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
        // Fungsi rekursif untuk mencari nama pelapor
        $search = function($data) use (&$search) {
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if ($key === 'label' && $value === 'Nama (Yang Membuat Laporan)' && isset($data['value'])) {
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
