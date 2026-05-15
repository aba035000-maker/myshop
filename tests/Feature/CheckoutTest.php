<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Jobs\SendInvoiceJob;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Queue;


class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_checkout_with_enough_balance_and_stock()
    {
        $user = User::factory()->create([
            'balance' => 1000,
        ]);

        $product = Product::factory()->create([
            'quantity' => 10,
            'price' => 50,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->be($user); // تسجيل دخول المستخدم

        $response = $this->post('/api/checkout');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'order_id',
        ]);
    }

    public function test_checkout_fails_when_balance_is_not_enough()
    {
        $user = User::factory()->create([
            'balance' => 10,
        ]);

        $product = Product::factory()->create([
            'quantity' => 10,
            'price' => 50,
        ]);

        $cart = Cart::create([
            'user_id' => $user->id,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->be($user);

        $response = $this->post('/api/checkout');

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'رصيدك غير كافٍ لإتمام عملية الدفع',
        ]);
    }
    public function test_concurrent_stock_update_is_safe()
    {
        $product = \App\Models\Product::factory()->create([
            'quantity' => 5,
            'price' => 10,
        ]);

        $responses = [];

        for ($i = 0; $i < 2; $i++) {
            $responses[] = $this->post('/api/checkout-concurrent', [
                'product_id' => $product->id,
                'quantity'   => 5,
            ]);
        }

        $successCount = 0;
        $failCount    = 0;

        foreach ($responses as $res) {
            if ($res->status() === 200) $successCount++;
            if ($res->status() === 400) $failCount++;
        }

        $this->assertEquals(1, $successCount);
        $this->assertEquals(1, $failCount);
    }
    public function test_system_limits_parallel_operations()
    {
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/api/limited-operation');
            dump("Request $i returned status: " . $response->status());
            if ($i <= 3) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429);
            }
        }
    }
    public function test_async_jobs_are_dispatched()
    {
        Queue::fake();

        $response = $this->post('/api/checkout-async');

        $response->assertStatus(200);

        Queue::assertPushed(SendInvoiceJob::class);
        Queue::assertPushed(SendNotificationJob::class);
    }
    public function test_batch_processing_dispatches_jobs()
    {
        \Illuminate\Support\Facades\Queue::fake();

        \App\Models\Product::factory()->count(250)->create();

        $response = $this->post('/api/process-batch');

        $response->assertStatus(200);

        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Jobs\ProcessProductsBatchJob::class,
            3
        );
    }
    public function test_load_is_distributed_across_servers()
    {
        $servers = ['Server-A', 'Server-B', 'Server-C'];

        foreach ($servers as $server) {
            $response = $this->get('/api/simulate-load', [
                'X-Server-Name' => $server
            ]);

            $response->assertStatus(200);
            $this->assertEquals($server, $response->json('server'));
        }
    }
}
