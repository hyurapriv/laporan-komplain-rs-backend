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

        $data = $this->fetchUpdateRequestData($year, $month);

        $result = $this->processUpdateRequestData($data);

        $availableMonths = $this->getAvailableMonths($year);

        return response()->json([
            'success' => true,
            'data' => $result,
            'availableMonths' => $availableMonths,
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
        $petugasCounts['Lainnya'] = 0;
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
            if (strpos($normalizedPetugas, 'Lainnya') !== false) {
                continue;
            }

            $this->updatePetugasCounts($petugasCounts, $item->petugas);
            $totalStatus[$status]++;
            $totalRequests++;

            $date = Carbon::parse($item->datetime_masuk)->format('Y-m-d');
            $dailyRequests[$date] = ($dailyRequests[$date] ?? 0) + 1;

            // Calculate response times
            $this->calculateResponseTimes($item, $responseTimes, $completedResponseTimes);
        }

        if ($petugasCounts['Lainnya'] === 0) {
            unset($petugasCounts['Lainnya']);
        }

        // Calculate average response times
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

    public function showUpdateRequestData(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = str_pad($request->input('month', Carbon::now()->month), 2, '0', STR_PAD_LEFT);

        $data = $this->fetchUpdateRequestData($year, $month);

        if ($data->isEmpty()) {
            return view('update_requests', ['error' => 'Data tidak ditemukan']);
        }

        $processedData = $this->processUpdateRequestData($data);

        $formattedData = $data->map(function ($item) {
            $jsonData = json_decode($item->json, true);

            if (is_array($jsonData) && count($jsonData) > 0) {
                $dataArray = $jsonData[0];
                $nama_pelapor = '';
                $status = '';

                foreach ($dataArray as $data) {
                    if ($data['type'] == 'text' && $data['label'] == 'Nama (Yang Membuat Laporan)') {
                        $nama_pelapor = $data['value'];
                    }
                    if ($data['type'] == 'hidden' && $data['name'] == 'Status') {
                        $status = $data['value'];
                    }
                }
            } else {
                $nama_pelapor = $status = 'N/A';
            }

            return [
                'id' => $item->id ?? 'N/A',
                'nama_pelapor' => $nama_pelapor,
                'status' => $status,
                'datetime_masuk' => $item->datetime_masuk ?? 'N/A',
                'datetime_pengerjaan' => $item->datetime_pengerjaan ?? 'N/A',
                'datetime_selesai' => $item->datetime_selesai ?? 'N/A',
            ];
        });

        return view('update_requests', [
            'data' => $formattedData,
            'petugasCounts' => $processedData['petugasCounts'],
            'totalStatus' => $processedData['totalStatus'],
            'totalRequests' => $processedData['totalRequests'],
            'dailyRequests' => $processedData['dailyRequests'],
            'averageResponseTime' => $processedData['averageResponseTime'],
            'averageCompletedResponseTime' => $processedData['averageCompletedResponseTime'],
        ]);
    }

    private function formatTime($minutes)
    {
        if ($minutes < 60) {
            return $minutes . ' menit';
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours . ' jam';
        }

        return $hours . ' jam ' . $remainingMinutes . ' menit';
    }
}
