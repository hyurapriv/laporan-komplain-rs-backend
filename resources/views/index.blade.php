<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processed Data</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    <style>
        /* ... [Keep existing styles] ... */

        .month-selector {
            margin-bottom: 20px;
        }

        select {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
    </style>
</head>

<body>
    <h1>Data Yang Diproses</h1>

    <div class="month-selector">
        <form action="{{ route('data.index') }}" method="GET">
            <select name="month" onchange="this.form.submit()">
                @foreach($availableMonths as $value => $label)
                    <option value="{{ $value }}" {{ $value == $selectedMonth ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="summary-box">
        <h2>Ringkasan untuk {{ Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->format('F Y') }}</h2>
        <p>Waktu Respons Rata-rata (Semua): <span id="average-response-time">{{ $averageResponseTime['formatted'] }}</span> (<span>{{ $averageResponseTime['minutes'] }}</span> menit)</p>
        <p>Waktu Respons Rata-rata (Tugas Selesai): <span id="average-completed-response-time">{{ $averageCompletedResponseTime['formatted'] }}</span> (<span>{{ $averageCompletedResponseTime['minutes'] }}</span> menit)</p>
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

    @foreach (['Klinis', 'Non-Klinis', 'Lainnya'] as $category)
        <div class="unit-category">
            <h2>Unit {{ $category }}</h2>
            @if (!empty($unitCounts[$category]))
                @foreach ($unitCounts[$category] as $unit => $statusCounts)
                    <div class="unit-details">
                        <h3>{{ $unit }}</h3>
                        <ul class="status-details">
                            @foreach ($statusCounts as $status => $count)
                                <li>{{ $status }}: {{ $count }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @else
                <p>Tidak ada unit {{ strtolower($category) }} yang ditemukan.</p>
            @endif
        </div>
    @endforeach

    @if (!empty($processedData))
        <button aria-label="Download Processed Data" onclick="downloadProcessedData()">Download Processed Data</button>
        <pre><code>{{ json_encode($processedData, JSON_PRETTY_PRINT) }}</code></pre>
    @else
        <p>Data tidak tersedia atau terjadi kesalahan dalam pemrosesan.</p>
    @endif

    <script>
        function downloadProcessedData() {
            fetch('{{ route('data.download') }}?month={{ $selectedMonth }}')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.blob();
                })
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'processed_data_{{ $selectedMonth }}.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);

                    Swal.fire({
                        icon: 'success',
                        title: 'Data berhasil diunduh',
                        text: 'File processed_data_{{ $selectedMonth }}.json berhasil diunduh!',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal mengunduh data',
                        text: 'Terjadi kesalahan saat mengunduh data. Silakan coba lagi nanti.',
                    });
                });
        }
    </script>
</body>

</html>