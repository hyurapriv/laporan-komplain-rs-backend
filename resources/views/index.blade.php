<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Data Detail</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.28.0/themes/prism-tomorrow.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }

        .data-container {
            margin-bottom: 20px;
        }

        .data-item {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .data-item p {
            margin: 5px 0;
        }

        h1, h2 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

    <h1 class="text-2xl font-bold mb-4">Complaint Data Details</h1>

    @if(isset($formattedData) && count($formattedData) > 0)
        @foreach ($formattedData as $item)
            <div class="data-item">
                <p><strong>ID:</strong> {{ $item['id'] }}</p>
                <p><strong>Nama Pelapor:</strong> {{ $item['nama_pelapor'] }}</p>
                <p><strong>Unit:</strong> {{ $item['unit'] }}</p>
                <p><strong>Petugas:</strong> {{ $item['petugas'] }}</p>
                <p><strong>Status:</strong> {{ $item['status'] }}</p>
                <p><strong>Datetime Masuk:</strong> {{ $item['datetime_masuk'] }}</p>
                <p><strong>Datetime Pengerjaan:</strong> {{ $item['datetime_pengerjaan'] }}</p>
                <p><strong>Datetime Selesai:</strong> {{ $item['datetime_selesai'] }}</p>
                <p><strong>Is Pending:</strong> {{ $item['is_pending'] }}</p>
            </div>
        @endforeach
    @else
        <p>Data tidak ditemukan untuk tahun {{ $year }} dan bulan {{ $month }}.</p>
    @endif

</body>
</html>
