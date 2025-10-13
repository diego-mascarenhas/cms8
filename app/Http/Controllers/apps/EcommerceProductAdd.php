<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class EcommerceProductAdd extends Controller
{
    public function index()
    {
        return view('content.apps.app-ecommerce-product-add');
    }
}
