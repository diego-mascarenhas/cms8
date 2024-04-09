<?php

namespace App\Http\Controllers;

use App\DataTables\BrulerDataTable;

class BrulerController extends Controller
{
    public function index(BrulerDataTable $dataTable)
    {
        return $dataTable->render('bruler.index');
    }
}
