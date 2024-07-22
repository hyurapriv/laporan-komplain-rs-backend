<?php

namespace App\Http\Controllers;

use App\Models\Data;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class DataController extends Controller
{
    public function index()
    {
        $processedData = [
            // Contoh data
            ['Nama Unit/Poli' => 'Poli Mata'],
            ['Nama Unit/Poli' => 'Poli Bedah'],
            ['Nama Unit/Poli' => 'Rekam Medis'],
            ['Nama Unit/Poli' => 'ICU'],
            // Tambahkan data lain sesuai kebutuhan
        ];

        $processedData = $this->getProcessedData();
        $statusCounts = $this->getStatusCounts($processedData);
        $petugasCounts = $this->getPetugasCounts($processedData);
        $unitCounts = $this->getUnitCounts($processedData);
        $averageResponseTime = $this->calculateAverageResponseTime($processedData);
        $averageCompletedResponseTime = $this->calculateAverageCompletedResponseTime($processedData);

        $clinicalUnits = array_filter($unitCounts['Klinis'], function ($count) {
            return $count > 0;
        });
    
        $nonClinicalUnits = array_filter($unitCounts['Non-Klinis'], function ($count) {
            return $count > 0;
        });
    
        $otherUnits = $unitCounts['Lainnya'] ?? 0;
    
        // Use dd() to debug and check if the variables have the expected data
        
    

       
        return view('index', compact(
            'processedData',
            'statusCounts',
            'petugasCounts',
            'clinicalUnits',
            'nonClinicalUnits',
            'otherUnits',
            'unitCounts',
            'averageResponseTime',
            'averageCompletedResponseTime'
        ));
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

            $responTime = $this->calculateResponseTime($data->datetime_masuk, $data->datetime_selesai);
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
                'Nama Unit/Poli' => $this->normalizeUnitNames($extractedData['namaUnit']),
                'respon_time' => $responTime['formatted'],
                'respon_time_minutes' => $responTime['minutes']
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
        $normalizedList = array_map(function ($name) use ($replacements) {
            return $replacements[trim($name)] ?? trim($name);
        }, $petugasList);

        return implode(', ', array_unique($normalizedList));
    }

    private function normalizeUnitNames($unit)
    {
        // Regex pattern untuk mencocokkan frasa yang mengandung "poli mata"
        $pattern = '/\b(?:poli\s*mata(?:\s*[\w\s]*)?)\b/i';

        // Jika unit sesuai dengan pola, kembalikan "Poli Mata"
        if (preg_match($pattern, strtolower($unit))) {
            return 'Poli Mata';
        }

        // Jika tidak sesuai dengan pola, kembalikan unit dengan kapitalisasi awal
        return ucfirst(strtolower($unit));
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

    private function getUnitCounts($processedData)
    {
        // Definisikan kata kunci utama untuk setiap unit/poli berdasarkan kelompok
        $keywords = [
            // Kelompok Unit Klinis
            'Klinis' => [
                'Rekam Medis' => ['rekam medis', 'rm'],
                'Poli Mata' => ['mata'],
                'Poli Bedah' => ['bedah'],
                'Poli Obgyn' => ['obgyn'],
                'Poli THT' => ['tht'],
                'Poli Orthopedi' => ['orthopedi', 'ortopedi'],
                'Poli Jantung' => ['jantung'],
                'Poli Gigi' => ['gigi'],
                'ICU' => ['icu'],
                'Radiologi' => ['radiologi'],
                'Perinatologi' => ['perinatologi', 'perina'],
                'Rehabilitasi Medik' => ['rehabilitasi medik'],
                'IGD' => ['igd'],
            ],
            // Kelompok Unit Non-Klinis
            'Non-Klinis' => [
                'Kesehatan Lingkungan' => ['kesehatan lingkungan', 'kesling'],
                'Farmasi' => ['farmasi'],
                'IBS' => ['ibs'],
                'UKM' => ['ukm'],
                'Litbang' => ['litbang'],
                'Laboratorium & Pelayanan Darah' => ['laboratorium & pelayanan darah', 'laboratorium'],
                'Kasir' => ['kasir'],
                'IT' => ['it'],
                'Jamkes/Pojok JKN' => ['jamkes', 'pojok jkn', 'pojok jkn / loket bpjs', 'jamkes / pojok jkn'],
                'Loket TPPRI' => ['loket tppri', 'tppri', 'tppri timur'],
                'Anggrek' => ['anggrek', 'unit anggrek'],
                'Gizi' => ['gizi'],
                'Ruang Akreditasi' => ['ruang akreditasi'],
                'Ranap' => ['ranap'],
                'Bugenvil' => ['bugenvil'],
                'Tes' => ['tes'],
            ],
            // Kemlompok Unit Lainnya
            'Unit Lainnya' => [
                'IT' => ['it'],
                'TSE' => ['tse'],
                'Maxime Deserunt Cumq' => ['maxime deserunt cumq'],
                'Veritatis Voluptatem' => ['veritatis voluptatem'],
                'Voluptas Enim Cupida' => ['voluptas enim cupida'],
                'Enim Id Unde Sequi E' => ['enim id unde sequi e'],
                'Est Iste Quam Dolore' => ['est iste quam dolore'],
            ],
        ];

        // Inisialisasi unitCounts dengan semua kelompok dan unit
        $unitCounts = [
            'Klinis' => array_fill_keys(array_keys($keywords['Klinis']), 0),
            'Non-Klinis' => array_fill_keys(array_keys($keywords['Non-Klinis']), 0),
            'Lainnya' => 0,
        ];

        // Proses setiap data
        foreach ($processedData as $data) {
            $unitName = strtolower($data['Nama Unit/Poli']);
            $matched = false;

            // Cek setiap kata kunci di kelompok Klinis
            foreach ($keywords['Klinis'] as $unit => $words) {
                foreach ($words as $word) {
                    if (strpos($unitName, $word) !== false) {
                        $unitCounts['Klinis'][$unit]++;
                        $matched = true;
                        break 2; // Hentikan pencarian setelah menemukan kecocokan
                    }
                }
            }

            // Cek setiap kata kunci di kelompok Non-Klinis jika belum cocok
            if (!$matched) {
                foreach ($keywords['Non-Klinis'] as $unit => $words) {
                    foreach ($words as $word) {
                        if (strpos($unitName, $word) !== false) {
                            $unitCounts['Non-Klinis'][$unit]++;
                            $matched = true;
                            break 2; // Hentikan pencarian setelah menemukan kecocokan
                        }
                    }
                }
            }

            // Jika tidak ada kecocokan, masukkan ke kategori 'Lainnya'
            if (!$matched) {
                $unitCounts['Lainnya']++;
            }
        }

        return $unitCounts;
    }

    private function getCountsByKey($processedData, $key)
    {
        $counts = [];
        foreach ($processedData as $data) {
            $value = $data[$key] ?? '';
            if (!empty($value)) {
                $normalizedValue = strtolower($value);
                $counts[$normalizedValue] = ($counts[$normalizedValue] ?? 0) + 1;
            }
        }
        return $counts;
    }

    private function formatDateTime($dateTime)
    {
        return $dateTime instanceof Carbon ? $dateTime->toDateTimeString() : $dateTime;
    }

    private function calculateResponseTime($datetimeMasuk, $datetimeSelesai)
    {
        if (!$datetimeMasuk || !$datetimeSelesai) {
            return ['minutes' => null, 'formatted' => 'N/A'];
        }

        $masuk = Carbon::parse($datetimeMasuk);
        $selesai = Carbon::parse($datetimeSelesai);

        // Calculate the difference in minutes
        $diffInMinutes = $masuk->diffInMinutes($selesai);

        return [
            'minutes' => $diffInMinutes,
            'formatted' => $this->formatMinutes($diffInMinutes)
        ];
    }

    private function calculateAverageResponseTime($processedData)
    {
        $totalResponseTime = 0;
        $countValidResponseTimes = 0;

        foreach ($processedData as $data) {
            if ($data['respon_time_minutes'] !== null) {
                $totalResponseTime += $data['respon_time_minutes'];
                $countValidResponseTimes++;
            }
        }

        $averageMinutes = $countValidResponseTimes > 0 ? $totalResponseTime / $countValidResponseTimes : 0;

        return [
            'minutes' => round($averageMinutes, 2),
            'formatted' => $this->formatMinutes(round($averageMinutes))
        ];
    }

    private function calculateAverageCompletedResponseTime($processedData)
    {
        $totalResponseTime = 0;
        $countCompleted = 0;

        foreach ($processedData as $data) {
            if ($data['status'] === 'Selesai' && $data['respon_time_minutes'] !== null) {
                $totalResponseTime += $data['respon_time_minutes'];
                $countCompleted++;
            }
        }

        if ($countCompleted === 0) {
            return ['minutes' => 0, 'formatted' => 'N/A'];
        }

        $averageMinutes = $totalResponseTime / $countCompleted;

        return [
            'minutes' => round($averageMinutes, 2),
            'formatted' => $this->formatMinutes(round($averageMinutes))
        ];
    }

    public function getKomplainData()
    {
        // Caching data for 5 minutes
        $cachedData = Cache::remember('komplain-data', 5 * 60, function () {
            $processedData = $this->getProcessedData();
            $statusCounts = $this->getStatusCounts($processedData);
            $averageResponseTime = $this->calculateAverageResponseTime($processedData);
            $totalComplaints = count($processedData); // Calculate total complaints

            return [
                'terkirim' => $statusCounts['Terkirim'] ?? 0,
                'proses' => $statusCounts['Dalam Pengerjaan / Pengecekan Petugas'] ?? 0,
                'selesai' => $statusCounts['Selesai'] ?? 0,
                'pending' => $statusCounts['pending'] ?? 0,
                'responTime' => $averageResponseTime['formatted'],
                'total' => $totalComplaints,
            ];
        });

        return response()->json($cachedData);
    }

    private function formatMinutes($minutes)
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours > 0) {
            return sprintf("%d jam %d menit", $hours, $remainingMinutes);
        } else {
            return sprintf("%d menit", $remainingMinutes);
        }
    }
}
