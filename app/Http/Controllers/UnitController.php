<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index()
    {
        return view('unit.index');
    }

    public function edit()
    {
        return view('unit.edit');
    }

    public function info()
    {
        return view('unit.info');
    }
}
