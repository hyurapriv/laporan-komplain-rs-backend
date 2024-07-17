<?php

namespace App\Http\Controllers;

use App\Models\Petugas;
use Illuminate\Http\Request;

class PetugaController extends Controller
{
    public function index()
    {
        $data = Petugas::all();
        return view('petugas', compact('data'));
    }
}
