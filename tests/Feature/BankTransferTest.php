<?php

namespace Tests\Feature;

use App\Mail\RestaurantMessage;
use App\Models\{Category, Meal, Order, Photo, Setting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Mail};
use Tests\TestCase;

class BankTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
            'restaurant.paystack.secret_key' => null,
            'restaurant.send_mail' => true,
        ]);
        $this->setting([
            'email' => 'restaurant@example.test',
            'bank_transfer' => [
                'enabled' => true,
                'bank_name' => 'Fixture Bank',
                'account_name' => 'Fixture Restaurant',
                'account_number' => '0001234567',
                'instructions' => Setting::defaults()['bank_transfer']['instructions'],
            ],
        ]);
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_checkout_and_confirmed_bank_transfer_to_processing(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        [$meal, $side] = $this->meals();
        $photo = Photo::create(['path' => 'photos/checkout.webp', 'width' => 480, 'height' => 480, 'alt' => 'Meal']);
        $meal->update(['photo_id' => $photo->id]);
        $this->setting(['delivery_enabled' => true, 'delivery_areas' => ['Lekki' => 150000]]);
        $this->actingAs($customer)->withSession(['cart' => [$meal->id => 1, $side->id => 1]]);
        $this->get('/checkout')->assertOk()
            ->assertSee($photo->url(480))->assertSee('Fixture Bank')->assertSee('Bank Transfer');
        $this->post(route('cart.update', $meal), ['quantity' => 3, 'return_to_checkout' => 1])->assertRedirect('/checkout');
        $this->post(route('cart.update', $meal), ['quantity' => 2, 'return_to_checkout' => 1])->assertRedirect('/checkout');
        $this->post(route('cart.update', $side), ['quantity' => 0, 'return_to_checkout' => 1])->assertRedirect('/checkout');
        $this->post(route('cart.update', $side), ['quantity' => 1])->assertRedirect('/cart');
        $this->get('/checkout')->assertOk();
        $payload = $this->checkoutPayload() + [
            'delivery_area' => 'Lekki', 'address' => '10 Review Road, Lekki',
            'delivery_instructions' => 'Call at the gate.', 'receipt_email' => 'receipt@example.test',
            'amount' => 1, 'delivery_fee' => 0,
        ];
        $payload['fulfilment'] = 'delivery';
        $this->post('/checkout', array_replace($payload, ['receipt_email' => 'invalid', 'address' => '']))
            ->assertSessionHasErrors(['receipt_email', 'address']);
        $this->post('/checkout', $payload)->assertRedirect();
        $order = Order::sole();
        $payment = $order->payment;
        $this->assertEquals(2, $order->items()->count());
        $this->assertEquals(950000, $order->subtotal);
        $this->assertEquals(150000, $order->delivery_fee);
        $this->assertEquals(1100000, $order->total);
        $this->assertSame('receipt@example.test', $order->email);
        $this->assertSame('Call at the gate.', $order->delivery_instructions);
        $this->assertSame('bank_transfer', $payment->method);
        $this->assertSame('pending', $payment->status);
        $this->assertNotSame($order->number, $payment->reference);
        $this->assertNull($order->paid_at);
        $this->post('/checkout', $payload)->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseCount('orders', 1);

        $orderUrl = route('orders.show', $order);
        $notifyUrl = route('orders.transfer.notify', $order);
        $reviewUrl = route('admin.orders.transfer.review', $order);
        $this->get($orderUrl)->assertOk()->assertSee('Fixture Restaurant')->assertSee('0001234567')
            ->assertSee('₦11,000.00')->assertSee('Copy Account Number')->assertSee($order->number)->assertSee('I Have Made Payment');
        $this->actingAs(User::factory()->create())->post($notifyUrl)->assertForbidden();
        $this->actingAs($customer)->post($notifyUrl)->assertRedirect();
        $this->post($notifyUrl)->assertRedirect();
        $this->get($orderUrl)->assertSee('Transfer reported')->assertDontSee('I Have Made Payment');
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertCount(1, Mail::queued(RestaurantMessage::class, fn ($mail) => $mail->title === 'Bank transfer awaiting review'));
        $confirm = ['decision' => 'confirm', 'receipt_checked' => '1', 'bank_transaction_reference' => 'fixture-000123ABC'];
        $this->patch($reviewUrl, $confirm)->assertForbidden();
        $this->post(route('orders.refresh', $order))->assertNotFound();
        $this->get(route('paystack.callback', ['reference' => $payment->reference]))->assertNotFound();
        Http::assertNothingSent();

        $this->actingAs($admin)->get('/admin/payments?needs_review=1')->assertOk()->assertSee($order->number)->assertSee('Review transfer');
        $this->get('/admin/orders?method=bank_transfer&payment_status=pending')->assertOk()->assertSee($order->number);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Confirm Bank Transfer')->assertSee('Reject Bank Transfer');
        $this->patch(route('admin.orders.update', $order), ['status' => 'preparing'])->assertSessionHasErrors('status');
        $this->patch($reviewUrl, ['decision' => 'confirm'])->assertSessionHasErrors(['receipt_checked', 'bank_transaction_reference']);
        $this->patch($reviewUrl, $confirm)->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame($admin->id, $payment->fresh()->reviewed_by);
        $this->assertNotNull($payment->fresh()->reviewed_at);
        $this->assertSame('FIXTURE-000123ABC', $payment->fresh()->bank_transaction_reference);
        $this->patch($reviewUrl, $confirm)->assertSessionHasErrors('payment');
        $this->patch(route('admin.orders.update', $order), ['status' => 'preparing'])->assertSessionHasNoErrors();
        $this->assertSame('preparing', $order->fresh()->status);
        $this->actingAs($customer)->get($orderUrl)->assertOk()->assertSee('Print receipt');
        Mail::assertQueued(RestaurantMessage::class, fn ($mail) => $mail->title === 'Bank transfer confirmed');
    }

    public function test_rejection_duplicate_statement_references_and_historical_bank_details(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        [$meal] = $this->meals();
        $first = $this->placeBankOrder($customer, $meal);
        $bank = array_replace(Setting::current()['bank_transfer'], ['account_number' => '0101234567']);
        $this->patch('/admin/settings/bank-transfer', $bank)->assertForbidden();
        $this->actingAs($admin)->patch('/admin/settings/bank-transfer', $bank)->assertSessionHasNoErrors();
        $second = $this->placeBankOrder($customer, $meal);
        $third = $this->placeBankOrder($customer, $meal);
        $this->assertSame(3, Order::distinct()->count('number'));
        $this->assertSame('0001234567', $first->payment->bank_details['account_number']);
        $this->assertSame('0101234567', $second->payment->bank_details['account_number']);

        $confirm = ['decision' => 'confirm', 'receipt_checked' => 1, 'bank_transaction_reference' => 'FIXTURE-X1'];
        $this->actingAs($admin)->patch(route('admin.orders.transfer.review', $first), $confirm)->assertSessionHasErrors('payment');
        $this->actingAs($customer);
        foreach ([$first, $second, $third] as $order) {
            $this->post(route('orders.transfer.notify', $order))->assertRedirect();
        }
        $this->actingAs($admin)->patch(route('admin.orders.transfer.review', $first), $confirm)->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.transfer.review', $second), array_replace($confirm, ['bank_transaction_reference' => 'fixture-x1']))
            ->assertSessionHasErrors('bank_transaction_reference');
        $this->assertSame('pending', $second->payment()->sole()->status);
        $this->patch(route('admin.orders.transfer.review', $third), [
            'decision' => 'reject', 'rejection_reason' => 'The amount on the bank statement did not match.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $third->payment()->sole()->status);
        $this->assertSame('pending_payment', $third->fresh()->status);
        $this->assertNull($third->fresh()->paid_at);
        $this->actingAs($customer)->get(route('orders.show', $third))->assertOk()
            ->assertSee('Your order is unpaid')->assertSee('did not match')->assertDontSee('Print receipt');
        Mail::assertQueued(RestaurantMessage::class, fn ($mail) => $mail->title === 'Bank transfer rejected');

        $this->actingAs($admin)->patch('/admin/settings/bank-transfer', array_replace($bank, ['enabled' => '0']))->assertSessionHasNoErrors();
        $this->actingAs($customer)->withSession(['cart' => [$meal->id => 1]])->get('/checkout')->assertOk();
        $this->post('/checkout', $this->checkoutPayload())->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('orders', 3);
    }

    private function meals(): array
    {
        $category = Category::create(['name' => 'Fixture mains', 'slug' => 'fixture-mains']);

        return [
            $category->meals()->create(['name' => 'Rice', 'slug' => 'fixture-rice', 'description' => 'Fresh rice', 'price' => 400000, 'available' => true]),
            $category->meals()->create(['name' => 'Side', 'slug' => 'fixture-side', 'description' => 'Fresh side', 'price' => 150000, 'available' => true]),
        ];
    }

    private function checkoutPayload(): array
    {
        return ['checkout_reference' => session('checkout_reference'), 'name' => 'Order Customer',
            'phone' => '+234 900 123 4567', 'fulfilment' => 'pickup', 'payment_method' => 'bank_transfer'];
    }

    private function placeBankOrder(User $customer, Meal $meal): Order
    {
        $this->actingAs($customer)->withSession(['cart' => [$meal->id => 1]])->get('/checkout')->assertOk();
        $this->post('/checkout', $this->checkoutPayload())->assertRedirect();

        return Order::latest('id')->firstOrFail();
    }

    private function setting(array $data): void
    {
        $settings = Setting::findOrFail(1);
        $settings->update(['content' => array_replace($settings->values(), $data)]);
    }
}
