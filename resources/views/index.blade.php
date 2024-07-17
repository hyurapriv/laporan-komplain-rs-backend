<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Processed Data</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Processed Data</h1>
        
        <button onclick="downloadProcessedData()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mb-6">
            Download Processed Data
        </button>

        <div class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4 overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">Nama Pelapor</th>
                        <th class="px-4 py-2">Nama Petugas</th>
                        <th class="px-4 py-2">Created At</th>
                        <th class="px-4 py-2">Datetime Masuk</th>
                        <th class="px-4 py-2">Datetime Pengerjaan</th>
                        <th class="px-4 py-2">Datetime Selesai</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Is Pending</th>
                        <th class="px-4 py-2">Nama Unit/Poli</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($processedData as $data)
                    <tr>
                        <td class="border px-4 py-2">{{ $data['id'] }}</td>
                        <td class="border px-4 py-2">{{ $data['Nama Pelapor'] }}</td>
                        <td class="border px-4 py-2">{{ $data['Nama Petugas'] }}</td>
                        <td class="border px-4 py-2">{{ $data['created_at'] }}</td>
                        <td class="border px-4 py-2">{{ $data['datetime_masuk'] }}</td>
                        <td class="border px-4 py-2">{{ $data['datetime_pengerjaan'] }}</td>
                        <td class="border px-4 py-2">{{ $data['datetime_selesai'] }}</td>
                        <td class="border px-4 py-2">{{ $data['status'] }}</td>
                        <td class="border px-4 py-2">{{ $data['is_pending'] ? 'Yes' : 'No' }}</td>
                        <td class="border px-4 py-2">{{ $data['Nama Unit/Poli'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function downloadProcessedData() {
        fetch('{{ route('processed-data.download') }}')
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