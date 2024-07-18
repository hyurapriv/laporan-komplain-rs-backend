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
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h1, h2 {
            color: #333;
        }
        pre {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            overflow-x: auto;
        }
        button {
            background-color: #4CAF50;
            border: none;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
            border-radius: 5px;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <h1>Processed Data</h1>
    
    <h2>Status Counts</h2>
    <ul>
        <li>Terkirim: {{ $statusCounts['terkirim'] }}</li>
        <li>Dalam Pengerjaan: {{ $statusCounts['dalam_pengerjaan'] }}</li>
        <li>Selesai: {{ $statusCounts['selesai'] }}</li>
        <li>Pending: {{ $statusCounts['pending'] }}</li>
    </ul>

    <button onclick="downloadProcessedData()">Download Processed Data</button>

    <pre><code>
        <?php echo htmlspecialchars(json_encode($processedData, JSON_PRETTY_PRINT)); ?>
    </code></pre>

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
                        title: 'Data berhasil ditambahkan',
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
