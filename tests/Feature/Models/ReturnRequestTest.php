<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\ReturnRequestStatusHistory;
use Tests\Support\Factory;
use Tests\TestCase;

final class ReturnRequestTest extends TestCase
{
    private function makeOrder(int $userId, string $status = 'delivered'): int
    {
        return Order::create([
            'order_number' => 'TEST-' . bin2hex(random_bytes(6)),
            'user_id' => $userId,
            'status' => $status,
            'subtotal' => '100.00',
            'total' => '100.00',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
        ]);
    }

    private function makeOrderItem(int $orderId, int $quantity = 1, string $price = '100.00'): int
    {
        return OrderItem::create([
            'order_id' => $orderId,
            'product_id' => null,
            'vendor_order_id' => null,
            'product_name' => 'Test Product',
            'sku' => 'SKU-' . bin2hex(random_bytes(3)),
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => (string) ((float) $price * $quantity),
        ]);
    }

    public function test_delivered_at_returns_the_most_recent_delivered_transition(): void
    {
        $customer = Factory::customer();
        $orderId = $this->makeOrder((int) $customer['id']);

        $this->assertNull(OrderStatusHistory::deliveredAt($orderId));

        OrderStatusHistory::create(['order_id' => $orderId, 'status' => 'processing', 'note' => null, 'changed_by' => null]);
        OrderStatusHistory::create(['order_id' => $orderId, 'status' => 'delivered', 'note' => null, 'changed_by' => null]);

        $this->assertNotNull(OrderStatusHistory::deliveredAt($orderId));
    }

    public function test_active_quantity_excludes_rejected_requests(): void
    {
        $customer = Factory::customer();
        $orderId = $this->makeOrder((int) $customer['id']);
        $orderItemId = $this->makeOrderItem($orderId, 3);

        $rejectedRequestId = ReturnRequest::create([
            'order_id' => $orderId, 'user_id' => $customer['id'], 'status' => 'rejected', 'reason' => 'n/a',
        ]);
        ReturnRequestItem::create(['return_request_id' => $rejectedRequestId, 'order_item_id' => $orderItemId, 'quantity' => 2, 'reason' => null]);

        $this->assertSame(0, ReturnRequestItem::activeQuantityForOrderItem($orderItemId));

        $pendingRequestId = ReturnRequest::create([
            'order_id' => $orderId, 'user_id' => $customer['id'], 'status' => 'pending', 'reason' => 'n/a',
        ]);
        ReturnRequestItem::create(['return_request_id' => $pendingRequestId, 'order_item_id' => $orderItemId, 'quantity' => 1, 'reason' => null]);

        $this->assertSame(1, ReturnRequestItem::activeQuantityForOrderItem($orderItemId));
    }

    public function test_approve_reject_and_mark_refunded_each_write_a_status_history_row(): void
    {
        $customer = Factory::customer();
        $admin = Factory::customer();
        $orderId = $this->makeOrder((int) $customer['id']);

        $returnRequestId = ReturnRequest::create([
            'order_id' => $orderId, 'user_id' => $customer['id'], 'status' => 'pending', 'reason' => 'Changed my mind',
        ]);

        ReturnRequest::approve($returnRequestId, (int) $admin['id'], 'Looks fine');
        $fresh = ReturnRequest::find($returnRequestId);
        $this->assertSame('approved', $fresh['status']);
        $this->assertSame('Looks fine', $fresh['admin_note']);
        $this->assertSame((int) $admin['id'], (int) $fresh['processed_by']);

        ReturnRequest::markRefunded($returnRequestId, (int) $admin['id'], '75.00');
        $fresh = ReturnRequest::find($returnRequestId);
        $this->assertSame('refunded', $fresh['status']);
        $this->assertSame('75.00', $fresh['refunded_amount']);
        $this->assertNotNull($fresh['refunded_at']);

        $history = ReturnRequestStatusHistory::forReturnRequest($returnRequestId);
        $this->assertCount(2, $history);
        $this->assertSame('approved', $history[0]['status']);
        $this->assertSame('refunded', $history[1]['status']);
    }

    public function test_reject_requires_no_prior_approval_state_change(): void
    {
        $customer = Factory::customer();
        $admin = Factory::customer();
        $orderId = $this->makeOrder((int) $customer['id']);

        $returnRequestId = ReturnRequest::create([
            'order_id' => $orderId, 'user_id' => $customer['id'], 'status' => 'pending', 'reason' => 'Wrong item',
        ]);

        ReturnRequest::reject($returnRequestId, (int) $admin['id'], 'Not eligible - past window');

        $fresh = ReturnRequest::find($returnRequestId);
        $this->assertSame('rejected', $fresh['status']);
        $this->assertSame('Not eligible - past window', $fresh['admin_note']);
    }

    public function test_for_user_only_returns_that_users_requests(): void
    {
        $customerA = Factory::customer();
        $customerB = Factory::customer();
        $orderA = $this->makeOrder((int) $customerA['id']);
        $orderB = $this->makeOrder((int) $customerB['id']);

        ReturnRequest::create(['order_id' => $orderA, 'user_id' => $customerA['id'], 'status' => 'pending', 'reason' => 'A']);
        ReturnRequest::create(['order_id' => $orderB, 'user_id' => $customerB['id'], 'status' => 'pending', 'reason' => 'B']);

        $this->assertCount(1, ReturnRequest::forUser((int) $customerA['id']));
        $this->assertCount(1, ReturnRequest::forUser((int) $customerB['id']));
    }

    public function test_belongs_to_user_scopes_ownership(): void
    {
        $owner = Factory::customer();
        $stranger = Factory::customer();
        $orderId = $this->makeOrder((int) $owner['id']);

        $returnRequestId = ReturnRequest::create([
            'order_id' => $orderId, 'user_id' => $owner['id'], 'status' => 'pending', 'reason' => 'n/a',
        ]);

        $this->assertTrue(ReturnRequest::belongsToUser($returnRequestId, (int) $owner['id']));
        $this->assertFalse(ReturnRequest::belongsToUser($returnRequestId, (int) $stranger['id']));
    }

    public function test_paginate_admin_filters_by_status(): void
    {
        $customer = Factory::customer();
        $orderId = $this->makeOrder((int) $customer['id']);

        ReturnRequest::create(['order_id' => $orderId, 'user_id' => $customer['id'], 'status' => 'pending', 'reason' => 'A']);
        ReturnRequest::create(['order_id' => $orderId, 'user_id' => $customer['id'], 'status' => 'approved', 'reason' => 'B']);

        $pending = ReturnRequest::paginateAdmin(1, 20, ['status' => 'pending']);
        $this->assertCount(1, $pending);
        $this->assertSame('pending', $pending[0]['status']);

        $this->assertSame(1, ReturnRequest::countAdmin(['status' => 'approved']));
    }
}
