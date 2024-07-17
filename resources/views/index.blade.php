<!DOCTYPE html>
<html>
<head>
    <title>Data Cuci Motor</title>
</head>
<body>
    <h1>Data Cuci Motor</h1>
    <h2>Status Counts</h2>
    <ul>
        <li>Terkirim: {{ $statusCounts['terkirim'] }}</li>
        <li>Dalam Pengerjaan: {{ $statusCounts['dalam_pengerjaan'] }}</li>
        <li>Selesai: {{ $statusCounts['selesai'] }}</li>
        <li>Pending: {{ $statusCounts['pending'] }}</li>
    </ul>
    <h2>Processed Data</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Pelapor</th>
                <th>Nama Petugas</th>
                <th>Created At</th>
                <th>Datetime Masuk</th>
                <th>Datetime Pengerjaan</th>
                <th>Datetime Selesai</th>
                <th>Status</th>
                <th>Is Pending</th>
                <th>Nama Unit/Poli</th>
            </tr>
        </thead>
        <tbody>
            @foreach($processedData as $data)
                <tr>
                    <td>{{ $data['id'] }}</td>
                    <td>{{ $data['Nama Pelapor'] }}</td>
                    <td>{{ $data['Nama Petugas'] }}</td>
                    <td>{{ $data['created_at'] }}</td>
                    <td>{{ $data['datetime_masuk'] }}</td>
                    <td>{{ $data['datetime_pengerjaan'] }}</td>
                    <td>{{ $data['datetime_selesai'] }}</td>
                    <td>{{ $data['status'] }}</td>
                    <td>{{ $data['is_pending'] }}</td>
                    <td>{{ $data['Nama Unit/Poli'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
