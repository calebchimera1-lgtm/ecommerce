<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Order;
use App\Models\VendorOrder;
use Tests\Support\Factory;
use Tests\TestCase;

final class VendorOrderTest extends TestCase
{
    private function makeOrder(int $userId): int
    {
        return Order::create([
            'order_number' => 'TEST-' . bin2hex(random_bytes(6)),
            'user_id' => $userId,
            'status' => 'pending',
            'subtotal' => '100.00',
            'total' => '100.00',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_unpaid_balance_sums_only_unpaid_rows(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $customer = Factory::customer();

        VendorOrder::create([
            'order_id' => $this->makeOrder((int) $customer['id']),
            'vendor_id' => $vendor['id'],
            'status' => 'delivered',
            'subtotal' => '100.00',
            'commission_amount' => '15.00',
            'payout_amount' => '85.00',
            'payout_status' => 'unpaid',
        ]);
        VendorOrder::create([
            'order_id' => $this->makeOrder((int) $customer['id']),
            'vendor_id' => $vendor['id'],
            'status' => 'delivered',
            'subtotal' => '50.00',
            'commission_amount' => '7.50',
            'payout_amount' => '42.50',
            'payout_status' => 'paid',
        ]);

        $this->assertSame(85.0, VendorOrder::unpaidBalanceForVendor((int) $vendor['id']));
        $this->assertSame(42.5, VendorOrder::paidToDateForVendor((int) $vendor['id']));
    }

    public function test_mark_paid_sets_status_reference_and_paid_by(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $customer = Factory::customer();
        $admin = Factory::customer();

        $vendorOrderId = VendorOrder::create([
            'order_id' => $this->makeOrder((int) $customer['id']),
            'vendor_id' => $vendor['id'],
            'status' => 'delivered',
            'subtotal' => '100.00',
            'commission_amount' => '15.00',
            'payout_amount' => '85.00',
            'payout_status' => 'unpaid',
        ]);

        VendorOrder::markPaid($vendorOrderId, (int) $admin['id'], 'Bank transfer #123');

        $fresh = VendorOrder::find($vendorOrderId);
        $this->assertSame('paid', $fresh['payout_status']);
        $this->assertSame((int) $admin['id'], (int) $fresh['paid_by']);
        $this->assertSame('Bank transfer #123', $fresh['payout_reference']);
        $this->assertNotNull($fresh['paid_at']);
    }

    public function test_update_status_writes_a_history_row(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $customer = Factory::customer();

        $vendorOrderId = VendorOrder::create([
            'order_id' => $this->makeOrder((int) $customer['id']),
            'vendor_id' => $vendor['id'],
            'status' => 'pending',
            'subtotal' => '100.00',
            'commission_amount' => '15.00',
            'payout_amount' => '85.00',
            'payout_status' => 'unpaid',
        ]);

        VendorOrder::updateStatus($vendorOrderId, 'shipped', 'Package handed to courier', (int) $vendor['user_id']);

        $fresh = VendorOrder::find($vendorOrderId);
        $this->assertSame('shipped', $fresh['status']);

        $history = \App\Models\VendorOrderStatusHistory::forVendorOrder($vendorOrderId);
        $this->assertCount(1, $history);
        $this->assertSame('shipped', $history[0]['status']);
        $this->assertSame('Package handed to courier', $history[0]['note']);
    }

    public function test_find_for_vendor_scopes_ownership(): void
    {
        ['vendor' => $vendorA] = Factory::vendor('approved');
        ['vendor' => $vendorB] = Factory::vendor('approved');
        $customer = Factory::customer();

        $vendorOrderId = VendorOrder::create([
            'order_id' => $this->makeOrder((int) $customer['id']),
            'vendor_id' => $vendorA['id'],
            'status' => 'pending',
            'subtotal' => '100.00',
            'commission_amount' => '15.00',
            'payout_amount' => '85.00',
            'payout_status' => 'unpaid',
        ]);

        $this->assertNotNull(VendorOrder::findForVendor($vendorOrderId, (int) $vendorA['id']));
        $this->assertNull(
            VendorOrder::findForVendor($vendorOrderId, (int) $vendorB['id']),
            'A vendor must never be able to load another vendor\'s sub-order by id.'
        );
    }

    public function test_has_delivered_order_for_user_requires_delivered_status(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $customer = Factory::customer();

        $vendorOrderId = VendorOrder::create([
            'order_id' => $this->makeOrder((int) $customer['id']),
            'vendor_id' => $vendor['id'],
            'status' => 'shipped',
            'subtotal' => '100.00',
            'commission_amount' => '15.00',
            'payout_amount' => '85.00',
            'payout_status' => 'unpaid',
        ]);

        $this->assertFalse(VendorOrder::hasDeliveredOrderForUser((int) $vendor['id'], (int) $customer['id']));

        VendorOrder::update($vendorOrderId, ['status' => 'delivered']);

        $this->assertTrue(VendorOrder::hasDeliveredOrderForUser((int) $vendor['id'], (int) $customer['id']));
    }

    public function test_has_delivered_order_for_user_does_not_leak_across_customers(): void
    {
        ['vendor' => $vendor] = Factory::vendor('approved');
        $buyer = Factory::customer();
        $otherCustomer = Factory::customer();

        VendorOrder::create([
            'order_id' => $this->makeOrder((int) $buyer['id']),
            'vendor_id' => $vendor['id'],
            'status' => 'delivered',
            'subtotal' => '100.00',
            'commission_amount' => '15.00',
            'payout_amount' => '85.00',
            'payout_status' => 'unpaid',
        ]);

        $this->assertTrue(VendorOrder::hasDeliveredOrderForUser((int) $vendor['id'], (int) $buyer['id']));
        $this->assertFalse(VendorOrder::hasDeliveredOrderForUser((int) $vendor['id'], (int) $otherCustomer['id']));
    }
}
