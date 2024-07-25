<?php

namespace App\Http\Controllers;

use App\Models\Data;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DataController extends Controller
{
    // Menampilkan data dan statistik ke tampilan index
    public function index()
    {
        $processedData = $this->getProcessedData(); // Ambil data yang telah diproses
        $statusCounts = $this->getStatusCounts($processedData); // Hitung jumlah status
        $petugasCounts = $this->getPetugasCounts($processedData); // Hitung jumlah petugas
        $unitCounts = $this->getUnitCounts($processedData); // Hitung jumlah unit
        $averageResponseTime = $this->calculateAverageResponseTime($processedData); // Hitung rata-rata waktu respons
        $averageCompletedResponseTime = $this->calculateAverageCompletedResponseTime($processedData); // Hitung rata-rata waktu respons untuk yang selesai

        // Filter unit klinis dan non-klinis
        $clinicalUnits = array_filter($unitCounts['Klinis'], function ($count) {
            return $count > 0;
        });

        $nonClinicalUnits = array_filter($unitCounts['Non-Klinis'], function ($count) {
            return $count > 0;
        });

        $otherUnits = $unitCounts['Lainnya'] ?? 0;

        // Kirimkan data ke tampilan index
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

    // Mengunduh data yang telah diproses dalam format JSON
    public function download()
    {
        $processedData = $this->getProcessedData(); // Ambil data yang telah diproses
        $fileName = 'processed_data.json'; // Nama file JSON
        Storage::put('public/' . $fileName, json_encode($processedData, JSON_PRETTY_PRINT)); // Simpan data ke file JSON

        // Unduh file dan hapus setelah dikirim
        return response()->download(storage_path('app/public/' . $fileName))->deleteFileAfterSend(true);
    }

    // Mengambil dan memproses data dari model Data
    private function getProcessedData()
    {
        return Data::where('form_id', 3)->get()->map(function ($data) {
            $parsedJson = $data->json[0] ?? []; // Ambil JSON yang ada
            $extractedData = $this->extractDataFromJson($parsedJson); // Ekstrak data dari JSON

            $responTime = $this->calculateResponseTime($data->datetime_masuk, $data->datetime_selesai); // Hitung waktu respons
            return [
                'id' => $data->id,
                'Nama Pelapor' => $extractedData['namaPelapor'],
                'Nama Petugas' => $this->normalizePetugasNames($data->petugas), // Normalisasi nama petugas
                'created_at' => $this->formatDateTime($data->created_at),
                'datetime_masuk' => $this->formatDateTime($data->datetime_masuk),
                'datetime_pengerjaan' => $this->formatDateTime($data->datetime_pengerjaan),
                'datetime_selesai' => $this->formatDateTime($data->datetime_selesai),
                'status' => $extractedData['status'] ?? $data->status ?? '',
                'is_pending' => $data->is_pending,
                'Nama Unit/Poli' => $this->normalizeUnitNames($extractedData['namaUnit']), // Normalisasi nama unit
                'respon_time' => $responTime['formatted'],
                'respon_time_minutes' => $responTime['minutes']
            ];
        })->toArray(); // Mengembalikan data yang telah diproses
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

    // Menormalisasi nama-nama petugas
    private function normalizePetugasNames($petugas)
    {
        $replacements = [
            'Adi' => 'Adika', 'Adika Wicaksana' => 'Adika', 'Adikaka' => 'Adika',
            'adikaka' => 'Adika', 'dika' => 'Adika', 'Dika' => 'Adika',
            'dikq' => 'Adika', 'Dikq' => 'Adika', 'AAdika' => 'Adika',
            'virgie' => 'Virgie', 'Vi' => 'Virgie', 'vi' => 'Virgie',
            'Virgie Dika' => 'Virgie, Adika', 'Virgie dikq' => 'Virgie, Adika',
        ];

        $petugasList = preg_split('/\s*[,&]\s*|\s+dan\s+/i', $petugas); // Pisahkan nama petugas
        $normalizedList = array_map(function ($name) use ($replacements) {
            return $replacements[trim($name)] ?? trim($name);
        }, $petugasList);

        return implode(', ', array_unique($normalizedList)); // Gabungkan dan kembalikan nama petugas yang dinormalisasi
    }

    // Menormalisasi nama unit/poli
    private function normalizeUnitNames($unit)
    {
        // Pola regex untuk mencocokkan frasa yang mengandung "poli mata"
        $pattern = '/\b(?:poli\s*mata(?:\s*[\w\s]*)?)\b/i';

        // Jika unit sesuai dengan pola, kembalikan "Poli Mata"
        if (preg_match($pattern, strtolower($unit))) {
            return 'Poli Mata';
        }

        // Jika tidak sesuai dengan pola, kembalikan unit dengan kapitalisasi awal
        return ucfirst(strtolower($unit));
    }

    // Menghitung jumlah petugas berdasarkan data yang diproses
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

        return array_filter($petugasCounts); // Kembalikan jumlah petugas yang telah dihitung
    }

    // Menghitung jumlah status dari data yang diproses
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

        return $statusCounts; // Kembalikan jumlah status yang telah dihitung
    }

    // Menghitung jumlah unit berdasarkan kategori klinis, non-klinis, dan lainnya
    private function getUnitCounts($processedData)
    {
        Log::info('Starting getUnitCounts function'); // Log untuk debugging
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
            ],
            'Non-Klinis' => [
                'Farmasi' => ['farmasi'],
                'Laboratorium' => ['laboratorium'],
                'Radiologi' => ['radiologi'],
                'Administrasi' => ['administrasi'],
            ]
        ];

        $unitCounts = ['Klinis' => [], 'Non-Klinis' => [], 'Lainnya' => 0];
        
        foreach ($processedData as $data) {
            $unit = $data['Nama Unit/Poli'];
            $matched = false;
            
            foreach ($keywords as $category => $units) {
                foreach ($units as $unitName => $unitKeywords) {
                    foreach ($unitKeywords as $keyword) {
                        if (stripos($unit, $keyword) !== false) {
                            $unitCounts[$category][$unitName] = ($unitCounts[$category][$unitName] ?? 0) + 1;
                            $matched = true;
                            break 2; // Keluar dari loop keyword dan unit
                        }
                    }
                }
            }
            
            if (!$matched) {
                $unitCounts['Lainnya']++;
            }
        }

        Log::info('Unit counts:', $unitCounts);
        return $unitCounts; // Kembalikan jumlah unit yang telah dihitung
    }

    // Menghitung jumlah berdasarkan kunci tertentu dari data yang diproses
    private function getCountsByKey($data, $key)
    {
        $counts = [];
        foreach ($data as $item) {
            $value = $item[$key] ?? 'Unknown';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        return $counts; // Kembalikan jumlah berdasarkan kunci yang dihitung
    }

    // Memformat objek Carbon atau string menjadi format tanggal dan waktu
    private function formatDateTime($dateTime)
    {
        return $dateTime instanceof Carbon ? $dateTime->format('d-m-Y H:i:s') : $dateTime;
    }

    // Menghitung waktu respons antara dua waktu
    private function calculateResponseTime($start, $end)
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);
        $diff = $end->diff($start);

        return [
            'formatted' => $this->formatMinutes($diff->days * 24 * 60 + $diff->h * 60 + $diff->i), // Format waktu respons
            'minutes' => $diff->days * 24 * 60 + $diff->h * 60 + $diff->i
        ];
    }

    // Menghitung rata-rata waktu respons dari data yang diproses
    private function calculateAverageResponseTime($processedData)
    {
        $totalMinutes = 0;
        $count = 0;

        foreach ($processedData as $data) {
            if ($data['respon_time_minutes']) {
                $totalMinutes += $data['respon_time_minutes'];
                $count++;
            }
        }

        return $count ? $this->formatMinutes($totalMinutes / $count) : 'N/A'; // Hitung rata-rata waktu respons
    }

    // Menghitung rata-rata waktu respons untuk data yang selesai
    private function calculateAverageCompletedResponseTime($processedData)
    {
        $totalMinutes = 0;
        $count = 0;

        foreach ($processedData as $data) {
            if (!$data['is_pending'] && $data['respon_time_minutes']) {
                $totalMinutes += $data['respon_time_minutes'];
                $count++;
            }
        }

        return $count ? $this->formatMinutes($totalMinutes / $count) : 'N/A'; // Hitung rata-rata waktu respons untuk yang selesai
    }

    // Mengambil data komplain dengan caching
    public function getKomplainData()
    {
        return Cache::remember('komplain_data', now()->addMinutes(10), function () {
            $data = Data::where('form_id', 3)->get();
            $counts = [
                'Selesai' => 0,
                'Pending' => 0,
                'No Response Time' => 0,
            ];

            foreach ($data as $item) {
                $responseTime = $this->calculateResponseTime($item->datetime_masuk, $item->datetime_selesai);
                if ($item->is_pending) {
                    $counts['Pending']++;
                } else {
                    if ($responseTime['minutes']) {
                        $counts['Selesai']++;
                    } else {
                        $counts['No Response Time']++;
                    }
                }
            }

            return $counts; // Kembalikan data komplain yang telah dihitung
        });
    }

    // Memformat jumlah menit menjadi format jam dan menit
    private function formatMinutes($minutes)
    {
        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $minutes);
    }
}



//*if (stripos($data['Nama Unit/Poli'], 'it') !== false) { Log::info('Found IT:', ['unit' => $data['Nama Unit/Poli']]); }*//