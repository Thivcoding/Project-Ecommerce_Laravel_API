<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // បង្ហាញ orders របស់អ្នកប្រើប្រាស់
    public function index(Request $request)
    {
        $orders = Order::with('orderItems.product', 'user')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    // បង្កើត order ថ្មីពី cart
    public function store(Request $request)
    {
        $user = $request->user();

        // validate input
        $request->validate([
            'phone'   => 'required|string|min:8|max:20',
            'address' => 'required|string|min:5',
        ]);

        $cartItems = CartItem::with('product')
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Cart is empty'
            ], 400);
        }

        // គណនាតម្លៃសរុប
        $total = $cartItems->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        // បង្កើត order (បន្ថែម phone & address)
        $order = Order::create([
            'user_id'     => $user->id,
            'phone'       => $request->phone,
            'address'     => $request->address,
            'total_price' => $total,
            'status'      => 'pending'
        ]);

        // បង្កើត order items
        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
                'price'      => $item->product->price
            ]);
        }

        // clear cart
        CartItem::where('user_id', $user->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data'    => $order->load('orderItems.product', 'user')
        ], 201);
    }


    // បង្ហាញ order តែមួយ
    public function show(Request $request, $id)
    {
        $order = Order::with('orderItems.product', 'user')
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    // update status
    public function update(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $request->validate([
            'status' => 'required|in:pending,completed,cancelled'
        ]);

        $order->status = $request->status;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->load('orderItems.product', 'user')
        ]);
    }

    // cancel order
    public function destroy(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Option 1: Cancel by changing status
        $order->status = 'cancelled';
        $order->save();

        // Option 2: Delete completely
        // $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => $order
        ]);
    }


}
