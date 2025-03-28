<?php

namespace App\Http\Controllers;

use App\Models\FormValue;
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
        'ali' => 'Ali Muhson',
        'muhson' => 'Ali Muhson',
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

        $detailData = $this->getDetailData($data);

        $availableMonths = $this->getAvailableMonths($data);

        return response()->json([
            'success' => true,
            'data' => $result,
            'detailData' => $detailData,
            'availableMonths' => $availableMonths,
        ]);
    }

    private function getDetailData(Collection $data)
    {
        $detailDataTerkirim = [];
        $detailDataProses = [];
        $detailDataSelesai = [];
        $detailDataPending = [];

        foreach ($data as $item) {
            // Decode JSON data
            $jsonData = json_decode($item->json, true);

            $nama_pelapor = '';
            if (is_array($jsonData) && count($jsonData) > 0) {
                $dataArray = $jsonData[0]; // Assuming the relevant data is in the first element

                foreach ($dataArray as $data) {
                    if ($data['type'] == 'text' && $data['label'] == 'Nama (Yang Membuat Laporan)') {
                        $nama_pelapor = $data['value'];
                        break; // No need to continue the loop once we have found the name
                    }
                }
            }

            // Skip data where the reporter name is 'tes'
            if (strtolower(trim($nama_pelapor)) === 'tes') {
                continue;
            }

            // Create detail item
            $detailItem = [
                'id' => $item->id,
                'namaPelapor' => $nama_pelapor ?: 'N/A',
                'petugas' => $this->normalizePetugasNames($item->petugas),
                'datetime_masuk' => $item->datetime_masuk,
            ];

            // Determine status category
            if ($item->datetime_selesai === null && $item->petugas === null) {
                $detailDataTerkirim[] = $detailItem;
            } elseif ($item->datetime_selesai === null && $item->petugas !== null && !$item->is_pending) {
                $detailItem['datetime_pengerjaan'] = $item->datetime_pengerjaan;
                $detailDataProses[] = $detailItem;
            } elseif ($item->datetime_selesai === null && $item->petugas !== null && $item->is_pending) {
                $detailItem['datetime_pengerjaan'] = $item->datetime_pengerjaan;
                $detailDataPending[] = $detailItem;
            } else if ($item->datetime_selesai !== null) {
                $detailDataSelesai[] = $detailItem;
            }
        }

        return [
            'detailDataTerkirim' => $detailDataTerkirim,
            'detailDataProses' => $detailDataProses,
            'detailDataSelesai' => $detailDataSelesai,
            'detailDataPending' => $detailDataPending,
        ];
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
        if (empty($unitValue) || $unitValue === 'Pilih Unit') {
            return 'Kategori Lainnya';
        }

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

            // Jika kategori adalah 'Kategori Lainnya', selalu gunakan 'Lainnya' sebagai unitValue
            if ($category === 'Kategori Lainnya') {
                $unitValue = 'Lainnya';
            }

            $status = $this->getFinalStatus($item);

            $normalizedPetugas = $this->normalizePetugasNames($item->petugas);

            $this->updateCategoryStats($categories[$category], $unitValue, $status, $item);
            $this->updateCategoryTotals($categoryTotals[$category], $status);
            $this->updatePetugasCounts($petugasCounts, $normalizedPetugas);
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

    private function updatePetugasCounts(&$petugasCounts, $normalizedPetugas)
    {
        foreach ($normalizedPetugas as $petugas) {
            if (isset($petugasCounts[$petugas])) {
                $petugasCounts[$petugas]++;
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

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours} jam {$remainingMinutes} menit";
        } elseif ($hours > 0) {
            return "{$hours} jam";
        } else {
            return "{$minutes} menit";
        }
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


    private function getAvailableMonths()
    {
        $availableMonths = DB::table('form_values')
            ->where('form_id', 3)
            ->select(DB::raw('DISTINCT DATE_FORMAT(datetime_masuk, "%Y-%m") as month'))
            ->orderBy('month', 'desc')
            ->pluck('month')
            ->toArray();

        return $availableMonths;
    }

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
            $jsonData = json_decode($item->json, true);

            if (is_array($jsonData) && count($jsonData) > 0) {
                $dataArray = $jsonData[0];

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

    public function showSkippedData()
    {
        // Logika untuk mengambil data keluhan
        $allComplaints = FormValue::all(); // Contoh: ambil semua data keluhan

        // Mengumpulkan data yang diskip
        $skippedData = [];

        foreach ($allComplaints as $complaint) {
            if ($this->shouldSkip($complaint)) {
                $skippedData[] = $complaint;
            }
        }

        return view('unsent_data', compact('skippedData'));
    }

    private function shouldSkip($complaint)
    {
        // Contoh logika untuk menentukan apakah data harus diskip
        return $complaint->is_pending;
    }
}
