<?php

namespace App\Http\Controllers;

use App\Http\Requests\CartQuoteRequest;
use App\Services\Cart\CartService;
use App\Traits\ApiResponse;

class CartController extends Controller
{
    use ApiResponse;

    public function quote(CartQuoteRequest $request, CartService $cartService)
    {
        $quote = $cartService->quote($request->validated('items'));

        if (! $quote['success']) {
            return $this->error(
                $quote['message'],
                422,
                $quote['errors'],
            );
        }

        return $this->success(
            $quote['data'],
            $quote['message'],
        );
    }
}
