<?php

namespace App\Http\Controllers;

use App\Models\Data;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataController extends Controller
{
    // Menampilkan data yang sudah diproses dan menyiapkan berbagai statistik
    public function index()
    {
        $processedData = $this->getProcessedData(); // Mendapatkan data yang telah diproses
        $statusCounts = $this->getStatusCounts($processedData); // Menghitung jumlah status
        $petugasCounts = $this->getPetugasCounts($processedData); // Menghitung jumlah petugas
        $unitCounts = $this->getUnitCounts($processedData); // Menghitung jumlah unit/poli
        $averageResponseTime = $this->calculateAverageResponseTime($processedData); // Menghitung waktu respon rata-rata
        $averageCompletedResponseTime = $this->calculateAverageCompletedResponseTime($processedData); // Menghitung waktu respon rata-rata untuk yang selesai

        // Mengkategorikan unit/poli ke dalam klinis dan non-klinis berdasarkan jumlah
        $clinicalUnits = array_filter($unitCounts['Klinis'], function ($count) {
            return $count > 0;
        });

        $nonClinicalUnits = array_filter($unitCounts['Non-Klinis'], function ($count) {
            return $count > 0;
        });

        $otherUnits = $unitCounts['Lainnya'] ?? 0;

        // Mengembalikan tampilan dengan data yang diproses dan statistik
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

    // Mengunduh data yang sudah diproses sebagai file JSON
    public function download()
    {
        $processedData = $this->getProcessedData(); // Mendapatkan data yang telah diproses
        $fileName = 'processed_data.json'; // Nama file yang akan diunduh
        Storage::put('public/' . $fileName, json_encode($processedData, JSON_PRETTY_PRINT)); // Menyimpan data dalam file JSON

        return response()->download(storage_path('app/public/' . $fileName))->deleteFileAfterSend(true); // Mengunduh file dan menghapusnya setelah diunduh
    }

    // Mendapatkan data yang telah diproses dari model Data
    private function getProcessedData()
    {
        return Data::where('form_id', 3)->get()->map(function ($data) {
            $parsedJson = $data->json[0] ?? []; // Mengambil JSON yang diparsing
            $extractedData = $this->extractDataFromJson($parsedJson); // Mengekstrak data dari JSON

            $responTime = $this->calculateResponseTime($data->datetime_masuk, $data->datetime_selesai); // Menghitung waktu respon
            return [
                'id' => $data->id,
                'Nama Pelapor' => $extractedData['namaPelapor'],
                'Nama Petugas' => $this->normalizePetugasNames($data->petugas), // Menormalkan nama petugas
                'created_at' => $this->formatDateTime($data->created_at), // Format tanggal dan waktu
                'datetime_masuk' => $this->formatDateTime($data->datetime_masuk),
                'datetime_pengerjaan' => $this->formatDateTime($data->datetime_pengerjaan),
                'datetime_selesai' => $this->formatDateTime($data->datetime_selesai),
                'status' => $extractedData['status'] ?? $data->status ?? '', // Status data
                'is_pending' => $data->is_pending,
                'Nama Unit/Poli' => $this->normalizeUnitNames($extractedData['namaUnit']), // Menormalkan nama unit/poli
                'respon_time' => $responTime['formatted'], // Waktu respon yang diformat
                'respon_time_minutes' => $responTime['minutes'] // Waktu respon dalam menit
            ];
        })->toArray();
    }

    // Mengekstrak data dari JSON yang diparsing
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

    // Menormalkan nama petugas
    private function normalizePetugasNames($petugas)
    {
        $replacements = [
            'Adi' => 'Adika', 'Adika Wicaksana' => 'Adika', 'Adikaka' => 'Adika',
            'adikaka' => 'Adika', 'dika' => 'Adika', 'Dika' => 'Adika',
            'dikq' => 'Adika', 'Dikq' => 'Adika', 'AAdika' => 'Adika',
            'virgie' => 'Virgie', 'Vi' => 'Virgie', 'vi' => 'Virgie',
            'Virgie Dika' => 'Virgie, Adika', 'Virgie dikq' => 'Virgie, Adika',
        ];

        $petugasList = preg_split('/\s*[,&]\s*|\s+dan\s+/i', $petugas); // Memisahkan nama petugas
        $normalizedList = array_map(function ($name) use ($replacements) {
            return $replacements[trim($name)] ?? trim($name); // Mengganti nama petugas dengan yang dinormalisasi
        }, $petugasList);

        return implode(', ', array_unique($normalizedList)); // Menggabungkan nama petugas yang dinormalisasi
    }

    // Menormalkan nama unit/poli
    private function normalizeUnitNames($unit)
    {
        $pattern = '/\b(?:poli\s*mata(?:\s*[\w\s]*)?)\b/i'; // Pola regex untuk mencocokkan "poli mata"

        if (preg_match($pattern, strtolower($unit))) { // Memeriksa apakah unit sesuai dengan pola
            return 'Poli Mata';
        }

        return ucfirst(strtolower($unit)); // Kapitalisasi awal untuk unit/poli yang tidak sesuai pola
    }

    // Menghitung jumlah petugas berdasarkan data yang telah diproses
    private function getPetugasCounts($processedData)
    {
        $petugasCounts = array_fill_keys(['Ganang', 'Agus', 'Ali Muhson', 'Virgie', 'Bayu', 'Adika'], 0); // Inisialisasi hitungan petugas

        foreach ($processedData as $data) {
            $petugasList = array_unique(explode(', ', $data['Nama Petugas'])); // Memisahkan nama petugas
            foreach ($petugasList as $petugas) {
                if (isset($petugasCounts[$petugas])) {
                    $petugasCounts[$petugas]++; // Menghitung jumlah petugas
                }
            }
        }

        return array_filter($petugasCounts); // Mengembalikan hitungan petugas yang lebih besar dari 0
    }

    // Menghitung jumlah status berdasarkan data yang telah diproses
    private function getStatusCounts($processedData)
    {
        $statusCounts = ['pending' => 0, 'Selesai' => 0]; // Inisialisasi hitungan status

        foreach ($processedData as $data) {
            if ($data['is_pending']) {
                if ($data['status'] === 'Selesai') {
                    $statusCounts['Selesai']++;
                } else {
                    $statusCounts['pending']++;
                }
            } else {
                $status = $data['status'];
                $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1; // Menghitung jumlah status
            }
        }

        return $statusCounts;
    }

    // Menghitung jumlah unit/poli berdasarkan data yang telah diproses
    private function getUnitCounts($processedData)
{
    Log::info('Starting getUnitCounts function');
    Log::info('Checking for Farmasi in input data');
    foreach ($processedData as $data) {
        if (stripos($data['Nama Unit/Poli'], 'farmasi') !== false) {
            Log::info('Found Farmasi:', ['unit' => $data['Nama Unit/Poli']]);
        }
    }
    
    $keywords = [
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
        'Non-Klinis' => [
            'Kesehatan Lingkungan' => ['kesehatan lingkungan', 'kesling'],
            'IBS' => ['ibs'],
            'Farmasi' => ['farmasi'],
            'Litbang' => ['litbang', 'ukm litbang'],
            'Ukm' => ['ukm'],
            'Laboratorium & Pelayanan Darah' => ['laboratorium & pelayanan darah', 'laboratorium'],
            'Akreditasi' => ['akreditasi'],
            'Kasir' => ['kasir'],
            'Anggrek' => ['anggrek', 'unit anggrek'],
            'Jamkes/Pojok JKN' => ['jamkes', 'pojok jkn', 'pojok jkn / loket bpjs', 'jamkes / pojok jkn'],
            'SIMRS' => ['simrs'],
            'Loket TPPRI' => ['loket tppri', 'tppri', 'tppri timur'],
            'Gizi' => ['gizi'],
            'Ranap' => ['ranap'],
            'Bugenvil' => ['bugenvil'],
            'IFRS' => ['ifrs'],
            'Veritatis voluptatem' => ['veritatis voluptatem'],
            'IT' => ['it'],
        ],
        'Lainnya' => ['Lainnya' => []]
    ];

    $unitCounts = [
        'Klinis' => array_fill_keys(array_keys($keywords['Klinis']), 0),
        'Non-Klinis' => array_fill_keys(array_keys($keywords['Non-Klinis']), 0),
        'Lainnya' => ['Lainnya' => 0]
    ];

    foreach ($processedData as $data) {
        $unitName = strtolower($data['Nama Unit/Poli']);
        $matched = false;

        Log::info('Processing unit:', ['unit' => $unitName]);
        if (stripos($unitName, 'farmasi') !== false) {
            $unitCounts['Non-Klinis']['Farmasi']++;
            $matched = true;
            Log::info('Matched Farmasi:', ['unit' => $unitName]);
            continue;  // Lanjut ke data berikutnya
        }

        foreach (['Klinis', 'Non-Klinis'] as $category) {
            foreach ($keywords[$category] as $unit => $words) {
                foreach ($words as $word) {
                    if (stripos($unitName, $word) !== false) {
                        $unitCounts[$category][$unit]++;
                        $matched = true;
                        Log::info("Matched {$category} unit:", ['unit' => $unit, 'word' => $word]);
                        break 3;
                    }
                }
            }
        }

        if (!$matched) {
            $unitCounts['Lainnya']['Lainnya']++;
            Log::info('Unmatched unit (added to Lainnya):', ['unit' => $unitName]);
        }
    }

    Log::info('Final unit counts:', ['unitCounts' => $unitCounts]);
    return $unitCounts;
}

    // Menghitung jumlah berdasarkan kunci dari data yang telah diproses
    private function getCountsByKey($processedData, $key)
    {
        $counts = [];
        foreach ($processedData as $data) {
            $value = $data[$key] ?? '';
            if (!empty($value)) {
                $normalizedValue = strtolower($value);
                $counts[$normalizedValue] = ($counts[$normalizedValue] ?? 0) + 1; // Menghitung jumlah berdasarkan kunci
            }
        }
        return $counts;
    }

    // Memformat tanggal dan waktu
    private function formatDateTime($dateTime)
    {
        return $dateTime instanceof Carbon ? $dateTime->toDateTimeString() : $dateTime; // Mengembalikan string tanggal dan waktu
    }

    // Menghitung waktu respon antara masuk dan selesai
    private function calculateResponseTime($datetimeMasuk, $datetimeSelesai)
    {
        if (!$datetimeMasuk || !$datetimeSelesai) {
            return ['minutes' => null, 'formatted' => 'N/A']; // Jika tanggal tidak ada, kembalikan nilai N/A
        }

        $masuk = Carbon::parse($datetimeMasuk);
        $selesai = Carbon::parse($datetimeSelesai);

        // Menghitung selisih waktu dalam menit
        $diffInMinutes = $masuk->diffInMinutes($selesai);

        return [
            'minutes' => $diffInMinutes,
            'formatted' => $this->formatMinutes($diffInMinutes) // Mengembalikan waktu respon yang diformat
        ];
    }

    // Menghitung rata-rata waktu respon dari data yang telah diproses
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
            'formatted' => $this->formatMinutes(round($averageMinutes)) // Mengembalikan rata-rata waktu respon yang diformat
        ];
    }

    // Menghitung rata-rata waktu respon untuk data yang sudah selesai
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
            return ['minutes' => 0, 'formatted' => 'N/A']; // Jika tidak ada data yang selesai, kembalikan nilai N/A
        }

        $averageMinutes = $totalResponseTime / $countCompleted;

        return [
            'minutes' => round($averageMinutes, 2),
            'formatted' => $this->formatMinutes(round($averageMinutes)) // Mengembalikan rata-rata waktu respon yang diformat
        ];
    }

    private function groupDataByUnitAndStatus($processedData)
{
    $groupedData = [];

    foreach ($processedData as $data) {
        $unit = $data['Nama Unit/Poli'];
        $status = $data['status'];

        if (!isset($groupedData[$unit])) {
            $groupedData[$unit] = [
                'Terkirim' => 0,
                'Dalam Pengerjaan / Pengecekan Petugas' => 0,
                'Selesai' => 0,
                'Pending' => 0,
            ];
        }

        if (isset($groupedData[$unit][$status])) {
            $groupedData[$unit][$status]++;
        } else {
            $groupedData[$unit]['Pending']++;
        }
    }

    return $groupedData;
}


    // Mendapatkan data komplain dan menyimpannya dalam cache selama 5 menit
    public function getKomplainData()
    {
        $cachedData = Cache::remember('komplain-data', 5 * 60, function () {
            $processedData = $this->getProcessedData(); // Mendapatkan data yang telah diproses
            $statusCounts = $this->getStatusCounts($processedData); // Menghitung jumlah status
            $averageResponseTime = $this->calculateAverageResponseTime($processedData); // Menghitung rata-rata waktu respon
            $totalComplaints = count($processedData); // Menghitung total komplain

            return [
                'terkirim' => $statusCounts['Terkirim'] ?? 0,
                'proses' => $statusCounts['Dalam Pengerjaan / Pengecekan Petugas'] ?? 0,
                'selesai' => $statusCounts['Selesai'] ?? 0,
                'pending' => $statusCounts['pending'] ?? 0,
                'responTime' => $averageResponseTime['formatted'],
                'total' => $totalComplaints,
            ];
        });

        return response()->json($cachedData); // Mengembalikan data dalam format JSON
    }

    // Memformat waktu dalam menit menjadi format jam dan menit
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

//*if (stripos($data['Nama Unit/Poli'], 'it') !== false) { Log::info('Found IT:', ['unit' => $data['Nama Unit/Poli']]); }*//