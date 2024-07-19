<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processed Data</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <style>
        /* ... (previous styles remain the same) ... */
        .summary-box {
            background-color: #e9f7ef;
            border: 1px solid #28a745;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <h1>Processed Data</h1>

    <div class="summary-box">
        <h2>Summary</h2>
        <p>Average Response Time (All): {{ $averageResponseTime['formatted'] }} ({{ $averageResponseTime['minutes'] }}
            minutes)</p>
        <p>Average Response Time (Completed Tasks): {{ $averageCompletedResponseTime['formatted'] }}
            ({{ $averageCompletedResponseTime['minutes'] }} minutes)</p>
    </div>

    <h2>Status Counts</h2>
    <ul>
        @foreach ($statusCounts as $status => $count)
            <li>{{ $status }}: {{ $count }}</li>
        @endforeach
    </ul>

    <h2>Petugas Counts</h2>
    <ul>
        @foreach ($petugasCounts as $petugas => $count)
            <li>{{ $petugas }}: {{ $count }}</li>
        @endforeach
    </ul>

    @if (!empty($processedData))
        <button onclick="downloadProcessedData()">Download Processed Data</button>

        <pre><code>
        {{ print_r($processedData) }}
        </code></pre>
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
