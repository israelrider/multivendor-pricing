<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with(['category', 'offers' => fn ($query) => $query->active()->with('vendor')])
            ->orderBy('name')
            ->get();

        return ProductResource::collection($products);
    }
}
