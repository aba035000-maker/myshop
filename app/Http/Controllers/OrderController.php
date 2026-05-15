<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Models\OrderItem;
use App\Jobs\SendInvoiceJob;
use App\Jobs\SendNotificationJob;

class OrderController extends Controller
{
    public function checkout()
    {
        $userId =auth()->id();

        $cart = Cart::where('user_id', $userId)->first();
        if (!$cart) {
            return response()->json(['message' => 'Cart is empty']);
        }

        $items = CartItem::where('cart_id', $cart->id)->with('product')->get();
        if ($items->count() == 0) {
            return response()->json(['message' => 'Cart is empty']);
        }

        DB::beginTransaction();

        try {
            $total = 0;

            // حساب المجموع + قفل المخزون
            foreach ($items as $item) {

                $product = DB::table('products')
                    ->where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($product->quantity < $item->quantity) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Not enough stock for product: ' . $product->name
                    ]);
                }

                // خصم المخزون
                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'quantity' => $product->quantity - $item->quantity
                    ]);

                $total += $product->price * $item->quantity;
            }

            // 🔥 الدفع (محاكاة)
            $user = User::find($userId);

            sleep(2); // محاكاة عملية الدفع

            if ($user->balance < $total) {
                DB::rollBack();
                return response()->json([
                    'message' => 'رصيدك غير كافٍ لإتمام عملية الدفع'
                ], 400);
            }

            // خصم الرصيد
            $user->balance -= $total;
            $user->save();

            // إنشاء الطلب
            $order = Order::create([
                'user_id' => $userId,
                'total_price' => $total
            ]);

            // إنشاء عناصر الطلب
            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price
                ]);
            }

            // تفريغ السلة
            CartItem::where('cart_id', $cart->id)->delete();

            // إرسال الفاتورة والإشعار
            dispatch(new SendInvoiceJob($order->id));
            dispatch(new SendNotificationJob($order->id));

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully with payment simulation',
                'order_id' => $order->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function checkoutConcurrent(Request $request)
    {
        $productId = $request->input('product_id');
        $quantity  = $request->input('quantity', 1);

        return DB::transaction(function () use ($productId, $quantity) {

            $product = DB::table('products')
                ->where('id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return response()->json(['message' => 'Product not found'], 404);
            }

            if ($product->quantity < $quantity) {
                return response()->json([
                    'message' => 'Not enough stock'
                ], 400);
            }

            DB::table('products')
                ->where('id', $productId)
                ->update([
                    'quantity' => $product->quantity - $quantity
                ]);

            return response()->json([
                'message' => 'Order placed successfully'
            ], 200);
        });
    }
    public function checkoutAsync()
    {
        // محاكاة إنشاء طلب
        $orderId = 1;

        dispatch(new SendInvoiceJob($orderId));
        dispatch(new SendNotificationJob($orderId));

        return response()->json([
            'message' => 'Checkout async triggered'
        ], 200);
    }
    public function processBatch()
    {
        $products = \App\Models\Product::all();

        $chunks = $products->chunk(100);

        foreach ($chunks as $chunk) {
            dispatch(new \App\Jobs\ProcessProductsBatchJob($chunk));
        }

        return response()->json([
            'message' => 'Batch processing started'
        ], 200);
    }
    public function simulateServerLoad(Request $request)
    {
        $serverName = $request->header('X-Server-Name', 'Unknown');

        return response()->json([
            'server' => $serverName,
            'status' => 'OK'
        ], 200);
    }

}
