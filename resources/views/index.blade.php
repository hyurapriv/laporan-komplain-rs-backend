<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Komplain</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        pre {
            background-color: #f4f4f4;
            padding: 20px;
            border-radius: 4px;
            overflow: auto;
        }
        h1, h2 {
            margin: 20px 0;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Daftar Komplain</h1>

        <!-- Tampilkan Daftar Komplain dalam Format JSON -->
        <h2>Data Komplain</h2>
        <pre>{{ json_encode($processedData, JSON_PRETTY_PRINT) }}</pre>

        <!-- Tampilkan Statistik Petugas dalam Format JSON -->
        <h2>Statistik Petugas</h2>
        <pre>{{ json_encode($petugasCounts, JSON_PRETTY_PRINT) }}</pre>
    </div>
</body>
</html>
