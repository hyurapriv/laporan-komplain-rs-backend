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
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        h1, h2 {
            color: #2c3e50;
        }

        .summary-box {
            background-color: #fff;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .month-selector {
            margin-bottom: 20px;
        }

        select {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        pre {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
        }

        button {
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            background-color: #3498db;
            color: #fff;
            cursor: pointer;
        }

        button:hover {
            background-color: #2980b9;
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
        <h2>Ringkasan untuk {{ \Carbon\Carbon::createFromFormat('Y-m', $selectedMonth)->format('F Y') }}</h2>
        {{-- Tampilkan ringkasan statistik jika diperlukan --}}
    </div>

    <h2>Data Yang Diproses</h2>
    @if (empty($data))
        <p>Data tidak tersedia atau terjadi kesalahan dalam pemrosesan.</p>
    @else
        <pre><code>{{ json_encode($data, JSON_PRETTY_PRINT) }}</code></pre>
    @endif

    <button onclick="downloadProcessedData()">Download Processed Data</button>

    <script>
        function downloadProcessedData() {
            const data = @json($data);
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'processed_data_{{ $selectedMonth }}.json';
            a.click();
            URL.revokeObjectURL(url);

            Swal.fire({
                icon: 'success',
                title: 'Data berhasil diunduh',
                text: 'File processed_data_{{ $selectedMonth }}.json berhasil diunduh!',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false
            });
        }
    </script>
</body>

</html>
