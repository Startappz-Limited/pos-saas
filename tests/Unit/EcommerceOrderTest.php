<?php

use App\Enums\EcommerceOrderStatus;
use App\Models\EcommerceOrder;

uses(Tests\TestCase::class);

// --- Model Configuration Tests ---

test('ecommerce order uses uuid as route key', function () {
    $order = new EcommerceOrder;

    expect($order->getRouteKeyName())->toBe('uuid');
});

test('ecommerce order casts status to enum', function () {
    $order = new EcommerceOrder;
    $order->status = 'pending';

    expect($order->status)->toBeInstanceOf(EcommerceOrderStatus::class)
        ->and($order->status)->toBe(EcommerceOrderStatus::Pending);
});

test('ecommerce order casts billing_address to array', function () {
    $order = new EcommerceOrder;
    $order->billing_address = ['address_1' => '123 Main St', 'city' => 'Nairobi'];

    expect($order->billing_address)->toBeArray()
        ->and($order->billing_address['city'])->toBe('Nairobi');
});

test('ecommerce order casts shipping_address to array', function () {
    $order = new EcommerceOrder;
    $order->shipping_address = ['address_1' => '456 Oak Ave', 'city' => 'Mombasa'];

    expect($order->shipping_address)->toBeArray()
        ->and($order->shipping_address['city'])->toBe('Mombasa');
});

test('ecommerce order casts platform_data to array', function () {
    $order = new EcommerceOrder;
    $order->platform_data = ['key' => 'value'];

    expect($order->platform_data)->toBeArray()
        ->and($order->platform_data['key'])->toBe('value');
});

// --- Accessor Tests ---

test('is_converted returns true when sale_id is set', function () {
    $order = new EcommerceOrder;
    $order->sale_id = 1;

    expect($order->is_converted)->toBeTrue();
});

test('is_converted returns false when sale_id is null', function () {
    $order = new EcommerceOrder;
    $order->sale_id = null;

    expect($order->is_converted)->toBeFalse();
});

test('is_cod returns true for cod payment method', function () {
    $order = new EcommerceOrder;
    $order->payment_method = 'cod';

    expect($order->is_cod)->toBeTrue();
});

test('is_cod returns true for COD uppercase', function () {
    $order = new EcommerceOrder;
    $order->payment_method = 'COD';

    expect($order->is_cod)->toBeTrue();
});

test('is_cod returns false for non-cod payment method', function () {
    $order = new EcommerceOrder;
    $order->payment_method = 'card';

    expect($order->is_cod)->toBeFalse();
});

test('can_be_converted returns true for pending unconverted order', function () {
    $order = new EcommerceOrder;
    $order->status = EcommerceOrderStatus::Pending;
    $order->sale_id = null;

    expect($order->can_be_converted)->toBeTrue();
});

test('can_be_converted returns true for processing unconverted order', function () {
    $order = new EcommerceOrder;
    $order->status = EcommerceOrderStatus::Processing;
    $order->sale_id = null;

    expect($order->can_be_converted)->toBeTrue();
});

test('can_be_converted returns false for converted order', function () {
    $order = new EcommerceOrder;
    $order->status = EcommerceOrderStatus::Pending;
    $order->sale_id = 1;

    expect($order->can_be_converted)->toBeFalse();
});

test('can_be_converted returns false for cancelled order', function () {
    $order = new EcommerceOrder;
    $order->status = EcommerceOrderStatus::Cancelled;
    $order->sale_id = null;

    expect($order->can_be_converted)->toBeFalse();
});

test('can_be_converted returns false for refunded order', function () {
    $order = new EcommerceOrder;
    $order->status = EcommerceOrderStatus::Refunded;
    $order->sale_id = null;

    expect($order->can_be_converted)->toBeFalse();
});

test('can_be_converted returns false for failed order', function () {
    $order = new EcommerceOrder;
    $order->status = EcommerceOrderStatus::Failed;
    $order->sale_id = null;

    expect($order->can_be_converted)->toBeFalse();
});

// --- Enum Tests ---

test('ecommerce order status has correct labels', function () {
    expect(EcommerceOrderStatus::Pending->label())->toBe('Pending')
        ->and(EcommerceOrderStatus::Processing->label())->toBe('Processing')
        ->and(EcommerceOrderStatus::OnHold->label())->toBe('On Hold')
        ->and(EcommerceOrderStatus::Completed->label())->toBe('Completed')
        ->and(EcommerceOrderStatus::Cancelled->label())->toBe('Cancelled')
        ->and(EcommerceOrderStatus::Refunded->label())->toBe('Refunded')
        ->and(EcommerceOrderStatus::Failed->label())->toBe('Failed');
});

test('ecommerce order status has colors', function () {
    expect(EcommerceOrderStatus::Pending->color())->toBeString()
        ->and(EcommerceOrderStatus::Processing->color())->toBeString()
        ->and(EcommerceOrderStatus::Completed->color())->toBeString()
        ->and(EcommerceOrderStatus::Cancelled->color())->toBeString();
});

test('ecommerce order status canBeConverted works correctly', function () {
    expect(EcommerceOrderStatus::Pending->canBeConverted())->toBeTrue()
        ->and(EcommerceOrderStatus::Processing->canBeConverted())->toBeTrue()
        ->and(EcommerceOrderStatus::OnHold->canBeConverted())->toBeTrue()
        ->and(EcommerceOrderStatus::Completed->canBeConverted())->toBeTrue()
        ->and(EcommerceOrderStatus::Cancelled->canBeConverted())->toBeFalse()
        ->and(EcommerceOrderStatus::Refunded->canBeConverted())->toBeFalse()
        ->and(EcommerceOrderStatus::Failed->canBeConverted())->toBeFalse();
});

test('ecommerce order status canChangeStatus works correctly', function () {
    expect(EcommerceOrderStatus::Pending->canChangeStatus())->toBeTrue()
        ->and(EcommerceOrderStatus::Processing->canChangeStatus())->toBeTrue()
        ->and(EcommerceOrderStatus::OnHold->canChangeStatus())->toBeTrue()
        ->and(EcommerceOrderStatus::Completed->canChangeStatus())->toBeTrue()
        ->and(EcommerceOrderStatus::Cancelled->canChangeStatus())->toBeFalse()
        ->and(EcommerceOrderStatus::Refunded->canChangeStatus())->toBeFalse();
});

test('ecommerce order status options returns all cases', function () {
    $options = EcommerceOrderStatus::options();

    expect($options)->toBeArray()
        ->and($options)->toHaveCount(7);
});

// --- Fillable Tests ---

test('ecommerce order has expected fillable fields', function () {
    $order = new EcommerceOrder;
    $fillable = $order->getFillable();

    expect($fillable)->toContain('uuid')
        ->and($fillable)->toContain('shop_id')
        ->and($fillable)->toContain('platform')
        ->and($fillable)->toContain('platform_order_id')
        ->and($fillable)->toContain('order_number')
        ->and($fillable)->toContain('status')
        ->and($fillable)->toContain('total')
        ->and($fillable)->toContain('customer_name')
        ->and($fillable)->toContain('sale_id')
        ->and($fillable)->toContain('converted_at')
        ->and($fillable)->toContain('converted_by');
});
