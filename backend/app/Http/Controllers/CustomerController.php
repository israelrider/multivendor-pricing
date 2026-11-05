<?php

namespace App\Http\Controllers;

use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The demo customers a visitor can act as. There is no auth (README section 9).
 */
class CustomerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CustomerResource::collection(Customer::query()->orderBy('name')->get());
    }
}
