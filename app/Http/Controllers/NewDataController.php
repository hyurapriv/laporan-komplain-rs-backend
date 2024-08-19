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

    public function getComplaintData(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);
        $month = $request->input('month', Carbon::now()->month);

        $data = $this->fetchComplaintData($year, $month);

        $result = $this->processComplaintData($data);

        return response()->json([
            'success' => true,
            'data' => $result + ['availableMonths' => $this->getAvailableMonths($year)],
        ]);
    }

    private function fetchComplaintData($year, $month)
    {
        return DB::table('form_values')
            ->where('form_id', 3)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->select('json', 'status', 'datetime_masuk', 'datetime_pengerjaan', 'datetime_selesai', 'petugas', 'is_pending')
            ->get();
    }

    private function processComplaintData(Collection $data)
    {
        $units = [];
        $petugasCounts = array_fill_keys(self::PETUGAS_LIST, 0);
        $petugasCounts['Lainnya'] = 0;
        $totalStatus = array_fill_keys(self::STATUS_LIST, 0);
        $totalResponTime = 0;
        $totalResponCount = 0;

        foreach ($data as $item) {
            $jsonData = json_decode($item->json, true)[0] ?? [];

            if ($this->getValueFromJson($jsonData, 'text-1709615631557-0') === 'tes') {
                continue;
            }

            $status = $this->getFinalStatus($item);
            $unitValue = $this->getUnitValue($jsonData);

            $this->updateUnitStats($units, $unitValue, $status, $item);
            $this->updatePetugasCounts($petugasCounts, $item->petugas);
            $totalStatus[$status]++;

            if ($status !== 'Pending') {
                $responTime = $this->calculateResponTime($item);
                $totalResponTime += $responTime;
                $totalResponCount++;
            }
        }

        $this->calculateAverageResponTimes($units);
        $overallAverageResponTime = $totalResponCount > 0 ? $this->formatResponTime($totalResponTime / $totalResponCount) : null;

        if ($petugasCounts['Lainnya'] === 0) {
            unset($petugasCounts['Lainnya']);
        }

        return compact('units', 'totalStatus', 'petugasCounts', 'overallAverageResponTime');
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
        return $unitData ? collect($unitData['values'])->firstWhere('selected', 1)['label'] : 'Tidak Ditentukan';
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

    private function calculateAverageResponTimes(&$units)
    {
        foreach ($units as &$data) {
            if ($data['responCount'] > 0) {
                $data['averageResponTime'] = $this->formatResponTime($data['totalResponTime'] / $data['responCount']);
            } else {
                $data['averageResponTime'] = null;
            }
            unset($data['totalResponTime'], $data['responCount']);
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

    private function getAvailableMonths()
    {
        $previousYear = Carbon::now()->subYear()->year;

        // Ambil data dari tahun lalu
        $availableMonths = DB::table('form_values')
            ->where('form_id', 3)
            ->whereYear('created_at', $previousYear)
            ->selectRaw('MONTH(created_at) as month')
            ->groupBy('month')
            ->havingRaw('SUM(json REGEXP "select-1722845859503-0") > 0')
            ->pluck('month')
            ->map(fn($month) => Carbon::create($previousYear, $month)->format('Y-m'))
            ->toArray();

        // Log untuk debugging
        Log::info('Available Months for Previous Year:', $availableMonths);

        // Jika tidak ada data, fallback ke tahun ini
        if (empty($availableMonths)) {
            $currentYear = Carbon::now()->year;
            $availableMonths = DB::table('form_values')
                ->where('form_id', 3)
                ->whereYear('created_at', $currentYear)
                ->selectRaw('MONTH(created_at) as month')
                ->groupBy('month')
                ->havingRaw('SUM(json REGEXP "select-1722845859503-0") > 0')
                ->pluck('month')
                ->map(fn($month) => Carbon::create($currentYear, $month)->format('Y-m'))
                ->toArray();

            // Log untuk debugging
            Log::info('Available Months for Current Year:', $availableMonths);
        }

        return $availableMonths;
    }
}
