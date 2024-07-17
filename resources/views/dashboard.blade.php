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
    <div class="container mx-auto p-2">
        <div class="bg-green rounded-lg shadow-md w-full h-lvh p-2">
            <h1 class="text-3xl font-bold text-gray-800 mb-4 mt-10 text-center">Data Pengaduan RSUD Daha Husada</h1>
            <div class="flex justify-between px-14 mt-10">
                <img src="{{ asset('img/logo.png') }}" width="70" height="65" alt="">
                <div class="flex gap-3">
                    <div class="relative inline-block text-left">
                        <div>
                            <button type="button"
                                class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-gray-300 px-4 py-1 text-md font-bold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                                id="menu-button" aria-expanded="true" aria-haspopup="true">
                                Juli 204
                                <svg class="-mr-1 h-5 w-5 text-slate-800" viewBox="0 0 20 20" fill="currentColor"
                                    aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        {{-- <div class="absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                            role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1">
                            <div class="py-1" role="none">
                                <!-- Active: "bg-gray-100 text-gray-900", Not Active: "text-gray-700" -->
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem"
                                    tabindex="-1" id="menu-item-0">Juni 2024</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem"
                                    tabindex="-1" id="menu-item-1">Mei 2024</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem"
                                    tabindex="-1" id="menu-item-2">April 2024</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem"
                                    tabindex="-1" id="menu-item-2">Maret 2024</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem"
                                    tabindex="-1" id="menu-item-2">Feb 2024</a>
                                <a href="#" class="block px-4 py-2 text-sm text-gray-700" role="menuitem"
                                    tabindex="-1" id="menu-item-2">Jan 2024</a>
                            </div>
                        </div> --}}
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">Selasa, 9 Juli 2024 12:30:47</h3>
                </div>
            </div>
            <div class="card grid grid-cols-6 gap-4 mt-14 px-14">
                <div class="flex flex-col items-center px-4 py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/total.png') }}" width="20" height="20" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-lg">Total</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-2xl mt-2">150</h3>
                </div>
                <div class="flex flex-col items-center px-4 py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/terkirim.png') }}" width="20" height="20" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-lg">Terkirim</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-2xl mt-2">150</h3>
                </div>
                <div class="flex flex-col items-center px-4 py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/terkirim.png') }}" width="20" height="20" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-lg">Terkirim</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-2xl mt-2">150</h3>
                </div>
                <div class="flex flex-col items-center px-4 py-4 rounded-lg shadow-lg bg-white">
                    <div class="flex items-center space-x-2">
                        <div class="bg-green-sec rounded-full p-1.5 flex justify-center items-center">
                            <img src="{{ asset('img/icon/terkirim.png') }}" width="20" height="20" alt="">
                        </div>
                        <h3 class="text-gray-900 font-bold text-lg">Terkirim</h3>
                    </div>
                    <h3 class="text-gray-900 font-bold text-2xl mt-2">150</h3>
                </div>
            </div> 
        </div>
    </div>
</body>

</html>
