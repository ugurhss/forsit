<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListProductsRequest;
use App\Http\Resources\ProductResource;
use App\Services\Product\ProductService;
use App\Traits\ApiResponse;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(ListProductsRequest $request, ProductService $productService)
    {
        $products = $productService->listProducts($request->validated());

        return $this->success(
            ProductResource::collection($products->getCollection())->resolve(),
            'Products listed successfully.',
            200,
            [
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
            ],
        );
    }
}
