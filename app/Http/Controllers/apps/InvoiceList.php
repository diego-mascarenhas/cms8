<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class InvoiceList extends Controller
{
    public function index()
    {
        return view('content.apps.app-invoice-list');
    }
}
