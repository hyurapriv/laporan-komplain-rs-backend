<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NewDataController extends Controller
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
        'Virgie dikq' => 'Virgie, Adika'
    ];

    private const UNIT_CATEGORIES = [
        'Kategori IGD' => ['Ambulance', 'IGD'],
        'Kategori Rawat Jalan' => [
            'Klinik Anak',
            'Klinik Bedah',
            'Klinik Gigi',
            'Klinik Jantung',
            'Klinik Konservasi',
            'Klinik Kulit',
            'Klinik Kusta',
            'Klinik Mata',
            'Klinik Obgyn',
            'Klinik Ortopedy',
            'Klinik Penyakit Dalam',
            'Klinik TB',
            'Klinik THT',
            'Klinik Umum'
        ],
        'Kategori Rawat Inap' => [
            'Irna Atas',
            'Irna Bawah',
            'IBS',
            'VK',
            'Perinatology'
        ],
        'Kategori Penunjang Medis' => [
            'Farmasi',
            'Laboratorium',
            'Admisi / Rekam Medis',
            'Rehab Medik'
        ],
        'Kategori Lainnya' => ['Lainnya']
    ];

    public function getComplaintData(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->format('m'));

        $data = $this->fetchComplaintData($year, $month);

        $result = $this->processComplaintData($data);

        $availableMonths = $this->getAvailableMonths($data);

        return response()->json([
            'success' => true,
            'data' => $result,
            'availableMonths' => $availableMonths,
        ]);
    }

    private function fetchComplaintData($year, $month)
    {
        return DB::table('form_values')
            ->where('form_id', 3)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->select('id', 'json', 'status', 'datetime_masuk', 'datetime_pengerjaan', 'datetime_selesai', 'petugas', 'is_pending')
            ->get();
    }

    private function getUnitCategory($unitValue)
    {
        foreach (self::UNIT_CATEGORIES as $category => $units) {
            if (in_array($unitValue, $units)) {
                return $category;
            }
        }
        return 'Kategori Lainnya';
    }

    private function processComplaintData(Collection $data)
    {
        $categories = array_fill_keys(array_keys(self::UNIT_CATEGORIES), []);
        $categoryTotals = array_fill_keys(array_keys(self::UNIT_CATEGORIES), [
            'Total' => 0,
            'Status' => array_fill_keys(self::STATUS_LIST, 0)
        ]);
        $petugasCounts = array_fill_keys(self::PETUGAS_LIST, 0);
        $petugasCounts['Lainnya'] = 0;
        $totalStatus = array_fill_keys(self::STATUS_LIST, 0);
        $totalResponTime = 0;
        $totalResponCount = 0;
        $totalComplaints = 0;

        foreach ($data as $item) {
            if ($item->id == 1404) {
                continue;
            }

            $jsonData = json_decode($item->json, true)[0] ?? [];
            
            $reporterName = $this->getValueFromJson($jsonData, 'Nama (Yang Membuat Laporan)');
            Log::info("Nama Pelapor: " . $reporterName);
            if (strtolower(trim($reporterName)) === 'tes') {
                continue;
            }

            $unitValue = $this->getUnitValue($jsonData);
            $category = $this->getUnitCategory($unitValue);

            if ($category === 'Kategori Lainnya' && $unitValue !== 'Lainnya') {
                continue;
            }

            $status = $this->getFinalStatus($item);

            $this->updateCategoryStats($categories[$category], $unitValue, $status, $item);
            $this->updateCategoryTotals($categoryTotals[$category], $status);
            $this->updatePetugasCounts($petugasCounts, $item->petugas);
            $totalStatus[$status]++;
            $totalComplaints++;

            if ($status !== 'Pending') {
                $responTime = $this->calculateResponTime($item);
                $totalResponTime += $responTime;
                $totalResponCount++;
            }
        }

        $this->calculateAverageResponTimes($categories);
        $overallAverageResponTime = $totalResponCount > 0 ? $this->formatResponTime($totalResponTime / $totalResponCount) : null;

        if ($petugasCounts['Lainnya'] === 0) {
            unset($petugasCounts['Lainnya']);
        }

        return compact('categories', 'categoryTotals', 'totalStatus', 'petugasCounts', 'overallAverageResponTime', 'totalComplaints');
    }

    private function updateCategoryStats(&$category, $unitValue, $status, $item)
    {
        if (!isset($category[$unitValue])) {
            $category[$unitValue] = array_fill_keys(self::STATUS_LIST, 0) + ['Total' => 0, 'totalResponTime' => 0, 'responCount' => 0];
        }
        $category[$unitValue][$status]++;
        $category[$unitValue]['Total']++;

        if ($status !== 'Pending') {
            $responTime = $this->calculateResponTime($item);
            $category[$unitValue]['totalResponTime'] += $responTime;
            $category[$unitValue]['responCount']++;
        }
    }

    private function updateCategoryTotals(&$categoryTotal, $status)
    {
        $categoryTotal['Total']++;
        $categoryTotal['Status'][$status]++;
    }

    private function getFinalStatus($item)
    {
        $status = $this->getValueFromJson(json_decode($item->json, true)[0] ?? [], 'Status');
        return $item->is_pending == 1 && in_array($status, ['Dalam Pengerjaan / Pengecekan Petugas', 'Terkirim'])
            ? 'Pending'
            : $status;
    }

    private function getUnitValue($jsonData)
    {
        $unitData = collect($jsonData)->firstWhere('name', 'select-1722845859503-0');
        if (!$unitData) {
            return null;
        }
        $selectedValue = collect($unitData['values'])->firstWhere('selected', 1);
        return $selectedValue ? $selectedValue['label'] : null;
    }

    private function updateUnitStats(&$units, $unitValue, $status, $item)
    {
        if (!isset($units[$unitValue])) {
            $units[$unitValue] = array_fill_keys(self::STATUS_LIST, 0) + ['Total' => 0, 'totalResponTime' => 0, 'responCount' => 0];
        }
        $units[$unitValue][$status]++;
        $units[$unitValue]['Total']++;

        if ($status !== 'Pending') {
            $responTime = $this->calculateResponTime($item);
            $units[$unitValue]['totalResponTime'] += $responTime;
            $units[$unitValue]['responCount']++;
        }
    }

    private function updatePetugasCounts(&$petugasCounts, $petugas)
    {
        $petugasList = array_unique(explode(', ', $this->normalizePetugasNames($petugas)));
        foreach ($petugasList as $petugas) {
            if (in_array($petugas, self::PETUGAS_LIST)) {
                $petugasCounts[$petugas]++;
            } elseif (!empty($petugas)) {
                $petugasCounts['Lainnya']++;
            }
        }
    }

    private function calculateResponTime($item)
    {
        $datetimeMasuk = Carbon::parse($item->datetime_masuk);
        $datetimeSelesai = $item->is_pending ? Carbon::now() : Carbon::parse($item->datetime_selesai);
        return $datetimeSelesai->diffInMinutes($datetimeMasuk);
    }

    private function calculateAverageResponTimes(&$categories)
    {
        foreach ($categories as &$category) {
            foreach ($category as &$data) {
                if ($data['responCount'] > 0) {
                    $data['averageResponTime'] = $this->formatResponTime($data['totalResponTime'] / $data['responCount']);
                } else {
                    $data['averageResponTime'] = null;
                }
                unset($data['totalResponTime'], $data['responCount']);
            }
        }
    }

    private function formatResponTime($minutes)
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        return $hours > 0 ? ($remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h") : "{$minutes}m";
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
            $normalizedName = self::PETUGAS_REPLACEMENTS[trim($name)] ?? trim($name);
            return in_array($normalizedName, self::PETUGAS_LIST) ? $normalizedName : 'Lainnya';
        }, $petugasList);

        return implode(', ', array_unique($normalizedList));
    }

    private function getAvailableMonths($data)
    {
        $availableMonths = [];

        $groupedData = $data->groupBy(function ($item) {
            return Carbon::parse($item->datetime_masuk)->format('Y-m');
        });

        foreach ($groupedData as $month => $items) {
            $hasValidUnits = $items->contains(function ($item) {
                $jsonData = json_decode($item->json, true)[0] ?? [];
                $unitValue = $this->getUnitValue($jsonData);

                return $unitValue !== 'Tidak Ditentukan' && $unitValue !== null;
            });

            if ($hasValidUnits) {
                $availableMonths[] = $month;
            }
        }

        return $availableMonths;
    }

    // Controller
    public function showComplaintData(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = str_pad($request->input('month', Carbon::now()->month), 2, '0', STR_PAD_LEFT);

        $data = $this->fetchComplaintData($year, $month);

        if ($data->isEmpty()) {
            return view('index', ['error' => 'Data tidak ditemukan']);
        }

        $processedData = $this->processComplaintData($data);

        $formattedData = $data->map(function ($item) {
            // Decode JSON
            $jsonData = json_decode($item->json, true);
        
            // Check if JSON data is valid
            if (is_array($jsonData) && count($jsonData) > 0) {
                // Flatten JSON structure to get values
                $dataArray = $jsonData[0];
        
                // Extract relevant information
                $nama_pelapor = '';
                $unit = '';
                $status = '';
        
                foreach ($dataArray as $data) {
                    if ($data['type'] == 'text' && $data['label'] == 'Nama (Yang Membuat Laporan)') {
                        $nama_pelapor = $data['value'];
                    }
                    if ($data['type'] == 'select') {
                        foreach ($data['values'] as $value) {
                            if (isset($value['selected']) && $value['selected'] == 1) {
                                $unit = $value['label'];
                            }
                        }
                    }
                    if ($data['type'] == 'hidden' && $data['name'] == 'Status') {
                        $status = $data['value'];
                    }
                }
            } else {
                $nama_pelapor = $unit = $status = 'N/A';
            }
        
            return [
                'id' => $item->id ?? 'N/A',
                'nama_pelapor' => $nama_pelapor,
                'unit' => $unit,
                'petugas' => $this->normalizePetugasNames($item->petugas) ?? 'N/A',
                'status' => $status,
                'datetime_masuk' => $item->datetime_masuk ?? 'N/A',
                'datetime_pengerjaan' => $item->datetime_pengerjaan ?? 'N/A',
                'datetime_selesai' => $item->datetime_selesai ?? 'N/A',
                'is_pending' => $item->is_pending === 1 ? 'Yes' : 'No',
            ];
        });

        return view('index', [
            'formattedData' => $formattedData, 
            'year' => $year, 
            'month' => $month,
            'categoryTotals' => $processedData['categoryTotals'],
            'totalStatus' => $processedData['totalStatus'],
            'totalComplaints' => $processedData['totalComplaints']
        ]);
    }
}