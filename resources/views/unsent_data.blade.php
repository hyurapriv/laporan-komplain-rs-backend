<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Data Tidak Terkirim</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>

<body>
    <div class="container">
        <h2>Data yang Diskip</h2>
        @if (count($skippedData) > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <!-- Kolom lain yang relevan -->\
                    </tr>
                </thead>
                <tbody>
                    @foreach ($skippedData as $data)
                        <tr>
                            <td>{{ $data->id }}</td>
                            <td>{{ $data->nama }}</td>
                            <td>{{ $data->status }}</td>
                            <!-- Kolom lain yang relevan -->
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p>Tidak ada data yang diskip.</p>
        @endif
    </div>
</body>

</html>
