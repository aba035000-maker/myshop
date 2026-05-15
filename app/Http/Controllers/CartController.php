<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function addToCart(Request $request)
    {
        $userId = 1; // مؤقتاً

        // الحصول على سلة المستخدم أو إنشاؤها
        $cart = Cart::firstOrCreate([
            'user_id' => $userId
        ]);

        // التأكد من وجود المنتج
        $product = Product::findOrFail($request->product_id);

        // إضافة أو تحديث العنصر داخل السلة
        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();

        if ($item) {
            $item->quantity += $request->quantity;
            $item->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $request->quantity
            ]);
        }

        return response()->json([
            'message' => 'Product added to cart successfully'
        ]);
    }
    public function getCart()
    {
        $userId = 1; // مؤقتاً

        $cart = Cart::where('user_id', $userId)->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart is empty',
                'items' => []
            ]);
        }

        $items = CartItem::where('cart_id', $cart->id)
            ->with('product')
            ->get();

        return response()->json([
            'cart_id' => $cart->id,
            'items' => $items
        ]);
    }
}
