<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class EcommerceOrderDetails extends Controller
{
    public function index()
    {
        return view('content.apps.app-ecommerce-order-details');
    }
}
