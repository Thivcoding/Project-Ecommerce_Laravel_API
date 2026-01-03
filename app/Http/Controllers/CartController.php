<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * GET /api/cart
     * List all cart items of authenticated user with product details
     */
    public function index(Request $request)
    {
        $cartItems = CartItem::with('product')
            ->where('user_id', $request->user()->id)
            ->get();

        $total = $cartItems->sum(function($item) {
            return $item->quantity * $item->product->price;
        });

        return response()->json([
            'success' => true,
            'data' => $cartItems,
            'total_price' => $total
        ], 200);
    }

    /**
     * POST /api/cart
     * Add product to cart or increase quantity if exists
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $data['user_id'] = $request->user()->id;

        $cartItem = CartItem::where('user_id', $data['user_id'])
            ->where('product_id', $data['product_id'])
            ->first();

        if ($cartItem) {
            // Increase quantity
            $cartItem->quantity += $data['quantity'];
            $cartItem->save();
        } else {
            // Create new cart item
            $cartItem = CartItem::create($data);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart item added/updated successfully',
            'data'    => $cartItem
        ], 201);
    }

    /**
     * PUT /api/cart/{id}
     * Update cart item quantity
     */
    public function update(Request $request, string $id)
    {
        $cartItem = CartItem::where('user_id', $request->user()->id)->find($id);

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Cart item not found'
            ], 404);
        }

        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cartItem->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cart item updated successfully',
            'data'    => $cartItem
        ], 200);
    }

    /**
     * DELETE /api/cart/product/{productId}
     * Remove cart item by product ID
     */
    public function destroyByProduct(Request $request, $productId)
    {
        $deleted = CartItem::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in cart'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product removed from cart'
        ], 200);
    }

    /**
     * DELETE /api/cart/clear
     * Clear all cart items for authenticated user
     */
    public function clear(Request $request)
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully'
        ], 200);
    }
}
