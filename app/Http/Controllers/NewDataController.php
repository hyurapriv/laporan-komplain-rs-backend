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
            ->select('json', 'status', 'datetime_masuk', 'datetime_pengerjaan', 'datetime_selesai', 'petugas', 'is_pending')
            ->get();

        $units = [];
        $petugasCounts = array_fill_keys($this->petugasList, 0);
        $petugasCounts['Lainnya'] = 0;

        $totalStatus = [
            'Pending' => 0,
            'Dalam Pengerjaan / Pengecekan Petugas' => 0,
            'Terkirim' => 0,
            'Selesai' => 0,
        ];

        foreach ($data as $item) {
            $jsonData = json_decode($item->json, true)[0] ?? [];

            $namaPelapor = $this->getValueFromJson($jsonData, 'text-1709615631557-0');
            if ($namaPelapor === 'tes') {
                continue;
            }

            $status = $this->getValueFromJson($jsonData, 'Status');
            $isPending = $item->is_pending;

            $finalStatus = $isPending == 1 && ($status === 'Dalam Pengerjaan / Pengecekan Petugas' || $status === 'Terkirim') ? 'Pending' : $status;

            $unitData = collect($jsonData)->firstWhere('name', 'select-1722845859503-0');
            $unitValue = $unitData ? collect($unitData['values'])->firstWhere('selected', 1)['label'] : 'Tidak Ditentukan';

            if (!isset($units[$unitValue])) {
                $units[$unitValue] = [
                    'Pending' => 0,
                    'Dalam Pengerjaan / Pengecekan Petugas' => 0,
                    'Terkirim' => 0,
                    'Selesai' => 0,
                    'Total' => 0,
                    'totalResponTime' => 0,
                    'responTimes' => []
                ];
            }
            $units[$unitValue][$finalStatus]++;
            $units[$unitValue]['Total']++;

            $totalStatus[$finalStatus]++;

            $petugasList = array_unique(explode(', ', $this->normalizePetugasNames($item->petugas)));
            foreach ($petugasList as $petugas) {
                if (in_array($petugas, $this->petugasList)) {
                    $petugasCounts[$petugas]++;
                } elseif (!empty($petugas)) {
                    $petugasCounts['Lainnya']++;
                }
            }

            $datetimePengerjaan = Carbon::parse($item->datetime_pengerjaan);
            $datetimeSelesai = $isPending ? Carbon::now() : Carbon::parse($item->datetime_selesai);

            if ($finalStatus !== 'Pending') {
                $responTime = $datetimeSelesai->diffInMinutes($datetimePengerjaan);

                $units[$unitValue]['totalResponTime'] += $responTime;
                $units[$unitValue]['responTimes'][] = $responTime;
            }
        }

        if ($petugasCounts['Lainnya'] === 0) {
            unset($petugasCounts['Lainnya']);
        }

        $totalAverageResponTime = 0;
        $unitCount = 0;

        foreach ($units as $unit => $data) {
            if (count($data['responTimes']) > 0) {
                $averageResponTime = $data['totalResponTime'] / count($data['responTimes']);
                $units[$unit]['averageResponTime'] = $this->formatResponTime($averageResponTime);

                $totalAverageResponTime += $averageResponTime;
                $unitCount++;
            } else {
                $units[$unit]['averageResponTime'] = null;
            }
            unset($units[$unit]['responTimes']);
        }

        // Rata-rata dari semua rata-rata unit
        $overallAverageResponTime = $unitCount > 0 ? $this->formatResponTime($totalAverageResponTime / $unitCount) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'units' => $units,
                'totalStatus' => $totalStatus,
                'petugasCounts' => $petugasCounts,
                'overallAverageResponTime' => $overallAverageResponTime,
            ],
        ]);
    }

    private function formatResponTime($minutes)
    {
        if ($minutes >= 60) {
            $hours = floor($minutes / 60);
            $remainingMinutes = $minutes % 60;
            return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h";
        }
        return "{$minutes}m";
    }

    public function selectComplaint(Request $request)
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
            $jsonData = json_decode($item->json, true)[0] ?? [];

            $unitData = collect($jsonData)->firstWhere('name', 'select-1722845859503-0');
            $unitValue = $unitData ? collect($unitData['values'])->firstWhere('selected', 1)['label'] : null;

            $statusData = $this->getValueFromJson($jsonData, 'Status');

            return [
                'id' => $item->id,
                'nama_pelapor' => $this->getValueFromJson($jsonData, 'text-1709615631557-0'),
                'unit' => $unitValue,
                'status' => $statusData,
                'datetime_masuk' => $item->datetime_masuk,
                'datetime_pengerjaan' => $item->datetime_pengerjaan,
                'datetime_selesai' => $item->datetime_selesai,
                'petugas' => $this->normalizePetugasNames($item->petugas),
                'is_pending' => $item->is_pending,
            ];
        });

        return view('index', [
            'processedData' => $processedData,
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
            return null;
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
        $normalizedList = array_map(function ($name) use ($replacements) {
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
            $petugasList = array_unique(explode(', ', $data['petugas']));
            foreach ($petugasList as $petugas) {
                if (in_array($petugas, $this->petugasList)) {
                    $petugasCounts[$petugas]++;
                } elseif (!empty($petugas)) {
                    $petugasCounts['Lainnya']++;
                }
            }
        }

        if ($petugasCounts['Lainnya'] === 0) {
            unset($petugasCounts['Lainnya']);
        }

        return $petugasCounts;
    }
}
