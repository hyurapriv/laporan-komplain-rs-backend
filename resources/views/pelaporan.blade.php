<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Dashboard</title>
</head>

<body class="bg-gray-100 min-h-screen">
    <div class="w-full p-2">
        <div class="bg-green rounded-lg shadow-md w-full min-h-screen p-2">
            <div class="flex justify-center mt-6">
                <img src="{{ asset('img/logo.png') }}" class="w-12 h-12 sm:w-16 sm:h-16 xl:w-24 xl:h-24 object-contain pb-3" alt="Logo">
            </div>
            <h1 class="text-lg sm:text-xl xl:text-3xl font-bold text-gray-800 mb-4 mt-3 text-center">Data Komplain IT RSUD Daha Husada</h1>
            <div class="flex flex-col sm:flex-row justify-between items-center px-4 sm:px-14 mt-6 sm:mt-12">
                <div class="flex gap-2 mb-4 sm:mb-0">
                    <img src="{{ asset('img/icon/total.png') }}" class="w-5 h-5 sm:w-6 sm:h-6 mt-1" alt="">
                    <h3 class="text-sm sm:text-md text-slate-800 font-bold xl:text-lg">Total Pengaduan: 150</h3>
                </div>
                <div class="flex items-center space-x-2 sm:space-x-4">
                    <div class="relative inline-block text-left">
                        <div>
                            <button type="button"
                                class="inline-flex items-center justify-center gap-x-1.5 rounded-md bg-gray-300 px-2 py-1 sm:px-3 sm:py-2 text-xs sm:text-sm font-medium text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                id="menu-button" aria-expanded="true" aria-haspopup="true">
                                Juli 204
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 ml-1" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <h3 class="text-xs sm:text-sm lg:text-lg font-bold text-gray-900">Selasa, 9 Juli 2024 12:30:47</h3>
                </div>
            </div>
            <div class="card grid grid-cols-2 lg:grid-cols-6 gap-4 mt-8 sm:mt-14 px-4 sm:px-14">
                <div class="flex flex-col items-center px-2 sm:px-4 py-3 sm:py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1 sm:p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/total.png') }}" class="w-4 h-4 sm:w-5 sm:h-5" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-sm sm:text-lg">Total</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-lg sm:text-xl mt-2">30</h3>
                </div>
                <div class="flex flex-col items-center px-2 sm:px-4 py-3 sm:py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1 sm:p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/terkirim.png') }}" class="w-4 h-4 sm:w-5 sm:h-5" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-sm sm:text-lg">Terkirim</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-lg sm:text-xl mt-2">30</h3>
                </div>
                <div class="flex flex-col items-center px-2 sm:px-4 py-3 sm:py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1 sm:p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/proses.png') }}" class="w-4 h-4 sm:w-5 sm:h-5" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-sm sm:text-lg">Diproses</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-lg sm:text-xl mt-2">60</h3>
                </div>
                <div class="flex flex-col items-center px-2 sm:px-4 py-3 sm:py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1 sm:p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/selesai.png') }}" class="w-4 h-4 sm:w-5 sm:h-5" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-sm sm:text-lg">Selesai</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-lg sm:text-xl mt-2">50</h3>
                </div>
                <div class="flex flex-col items-center px-2 sm:px-4 py-3 sm:py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1 sm:p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/ditunda.png') }}" class="w-4 h-4 sm:w-5 sm:h-5" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-sm sm:text-lg">Ditunda</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-lg sm:text-xl mt-2">10</h3>
                </div>
                <div class="flex flex-col items-center px-2 sm:px-4 py-3 sm:py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1 sm:p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/respon-time.png') }}" class="w-4 h-4 sm:w-5 sm:h-5" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-sm sm:text-md">Respon Time</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-sm sm:text-md mt-2">144 Menit</h3>
                </div>
            </div>
        </div>
    </div>
</body>

</html>