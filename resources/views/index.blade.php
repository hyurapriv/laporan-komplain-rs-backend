<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processed Data</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f5f5f5;
        }

        h1, h2 {
            color: #333;
        }

        .summary-box {
            background-color: #e9f7ef;
            border: 1px solid #28a745;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        ul {
            list-style-type: none;
            padding: 0;
        }

        li {
            margin: 5px 0;
        }

        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <h1>Processed Data</h1>

    <div class="summary-box">
        <h2>Ringkasan</h2>
        <p>Waktu Respons Rata-rata (Semua): {{ $averageResponseTime['formatted'] }} ({{ $averageResponseTime['minutes'] }} menit)</p>
        <p>Waktu Respons Rata-rata (Tugas Selesai): {{ $averageCompletedResponseTime['formatted'] }} ({{ $averageCompletedResponseTime['minutes'] }} menit)</p>
    </div>

    <h2>Status</h2>
    <ul>
        @foreach ($statusCounts as $status => $count)
            <li>{{ $status }}: {{ $count }}</li>
        @endforeach
    </ul>

    <h2>Petugas</h2>
    <ul>
        @foreach ($petugasCounts as $petugas => $count)
            <li>{{ $petugas }}: {{ $count }}</li>
        @endforeach
    </ul>

    <h2>Unit Klinis</h2>
    @if (!empty($clinicalUnits))
        <ul>
            @foreach ($clinicalUnits as $unit => $count)
                <li>{{ $unit }}: {{ $count }}</li>
            @endforeach
        </ul>
    @else
        <p>No clinical units found.</p>
    @endif

    <h2>Unit Non-Klinis</h2>
    @if (!empty($nonClinicalUnits))
        <ul>
            @foreach ($nonClinicalUnits as $unit => $count)
                <li>{{ $unit }}: {{ $count }}</li>
            @endforeach
        </ul>
    @else
        <p>No non-clinical units found.</p>
    @endif

    <h2>Unit Lainnya</h2>
    @if (!empty($otherUnits))
        <ul>
            @foreach ($otherUnits as $unit => $count)
                <li>{{ $unit }}: {{ $count }}</li>
            @endforeach
        </ul>
    @else
        <p>No other units found.</p>
    @endif

    @if (!empty($processedData))
        <button onclick="downloadProcessedData()">Download Processed Data</button>

        <pre><code>{{ print_r($processedData, true) }}</code></pre>
    @else
        <p>Data tidak tersedia atau terjadi kesalahan dalam pemrosesan.</p>
    @endif

    <script>
        function downloadProcessedData() {
            fetch('{{ route('data.download') }}')
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'processed_data.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);

                    Swal.fire({
                        icon: 'success',
                        title: 'Data berhasil diunduh',
                        text: 'File processed_data.json berhasil diunduh!',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: 'Terjadi kesalahan saat mengunduh data!',
                    });
                });
        }
    </script>
</body>

</html>
