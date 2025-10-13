<?php

namespace App\Http\Controllers\apps;

use App\Http\Controllers\Controller;

class EcommerceCustomerDetailsOverview extends Controller
{
    public function index()
    {
        return view('content.apps.app-ecommerce-customer-details-overview');
    }
}
