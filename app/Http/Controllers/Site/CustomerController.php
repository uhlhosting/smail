<?php

namespace Acelle\Http\Controllers\Site;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log as LaravelLog;
use Acelle\Http\Controllers\Controller;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        return view('site.customers.index');
    }
}
