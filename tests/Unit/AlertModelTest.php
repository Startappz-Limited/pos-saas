<?php

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;

uses(Tests\TestCase::class);

// --- Model Configuration Tests ---

test('alert uses uuid as route key', function () {
    $alert = new Alert;

    expect($alert->getRouteKeyName())->toBe('uuid');
});

test('alert casts type to AlertType enum', function () {
    $alert = new Alert;
    $alert->type = 'order_note';

    expect($alert->type)->toBeInstanceOf(AlertType::class)
        ->and($alert->type)->toBe(AlertType::ORDER_NOTE);
});

test('alert casts severity to AlertSeverity enum', function () {
    $alert = new Alert;
    $alert->severity = 'low';

    expect($alert->severity)->toBeInstanceOf(AlertSeverity::class)
        ->and($alert->severity)->toBe(AlertSeverity::LOW);
});

test('alert casts category to AlertCategory enum', function () {
    $alert = new Alert;
    $alert->category = 'orders';

    expect($alert->category)->toBeInstanceOf(AlertCategory::class)
        ->and($alert->category)->toBe(AlertCategory::ORDERS);
});

test('alert casts is_read to boolean', function () {
    $alert = new Alert;
    $alert->is_read = 1;

    expect($alert->is_read)->toBeBool()->toBeTrue();
});

test('alert casts is_resolved to boolean', function () {
    $alert = new Alert;
    $alert->is_resolved = 0;

    expect($alert->is_resolved)->toBeBool()->toBeFalse();
});

test('alert casts data to array', function () {
    $alert = new Alert;
    $alert->data = ['order_number' => 'WC-100'];

    expect($alert->data)->toBeArray()
        ->and($alert->data['order_number'])->toBe('WC-100');
});

test('alert casts actions to array', function () {
    $alert = new Alert;
    $alert->actions = ['dismiss', 'snooze'];

    expect($alert->actions)->toBeArray()
        ->and($alert->actions)->toContain('dismiss');
});

test('alert casts scheduled_at to datetime', function () {
    $alert = new Alert;
    $alert->scheduled_at = '2025-01-15 10:00:00';

    expect($alert->scheduled_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

// --- Helper Method Tests ---

test('isReminder returns true for ORDER_REMINDER type', function () {
    $alert = new Alert;
    $alert->type = AlertType::ORDER_REMINDER;

    expect($alert->isReminder())->toBeTrue();
});

test('isReminder returns false for ORDER_NOTE type', function () {
    $alert = new Alert;
    $alert->type = AlertType::ORDER_NOTE;

    expect($alert->isReminder())->toBeFalse();
});

test('isNote returns true for ORDER_NOTE type', function () {
    $alert = new Alert;
    $alert->type = AlertType::ORDER_NOTE;

    expect($alert->isNote())->toBeTrue();
});

test('isNote returns false for ORDER_REMINDER type', function () {
    $alert = new Alert;
    $alert->type = AlertType::ORDER_REMINDER;

    expect($alert->isNote())->toBeFalse();
});

test('isOverdue returns true when scheduled_at is past and not resolved', function () {
    $alert = new Alert;
    $alert->scheduled_at = now()->subDay();
    $alert->is_resolved = false;

    expect($alert->isOverdue())->toBeTrue();
});

test('isOverdue returns false when scheduled_at is future', function () {
    $alert = new Alert;
    $alert->scheduled_at = now()->addDay();
    $alert->is_resolved = false;

    expect($alert->isOverdue())->toBeFalse();
});

test('isOverdue returns false when resolved even if past', function () {
    $alert = new Alert;
    $alert->scheduled_at = now()->subDay();
    $alert->is_resolved = true;

    expect($alert->isOverdue())->toBeFalse();
});

test('isOverdue returns false when no scheduled_at', function () {
    $alert = new Alert;
    $alert->scheduled_at = null;
    $alert->is_resolved = false;

    expect($alert->isOverdue())->toBeFalse();
});

// --- Fillable Tests ---

test('alert has expected fillable fields', function () {
    $alert = new Alert;
    $fillable = $alert->getFillable();

    expect($fillable)->toContain('uuid')
        ->and($fillable)->toContain('shop_id')
        ->and($fillable)->toContain('title')
        ->and($fillable)->toContain('message')
        ->and($fillable)->toContain('type')
        ->and($fillable)->toContain('severity')
        ->and($fillable)->toContain('category')
        ->and($fillable)->toContain('alertable_type')
        ->and($fillable)->toContain('alertable_id')
        ->and($fillable)->toContain('is_read')
        ->and($fillable)->toContain('is_resolved')
        ->and($fillable)->toContain('scheduled_at')
        ->and($fillable)->toContain('data')
        ->and($fillable)->toContain('created_by');
});

// --- Enum Tests ---

test('AlertType has order-related cases', function () {
    expect(AlertType::ORDER_NOTE->value)->toBe('order_note')
        ->and(AlertType::ORDER_REMINDER->value)->toBe('order_reminder')
        ->and(AlertType::ORDER_STATUS_CHANGED->value)->toBe('order_status_changed');
});

test('AlertType order cases have labels', function () {
    expect(AlertType::ORDER_NOTE->label())->toBe('Order Note')
        ->and(AlertType::ORDER_REMINDER->label())->toBe('Order Reminder')
        ->and(AlertType::ORDER_STATUS_CHANGED->label())->toBe('Order Status Changed');
});

test('AlertCategory ORDERS contains order-related types', function () {
    $types = AlertCategory::ORDERS->types();

    expect($types)->toContain(AlertType::ORDER_NOTE)
        ->and($types)->toContain(AlertType::ORDER_REMINDER)
        ->and($types)->toContain(AlertType::ORDER_STATUS_CHANGED);
});

// --- EcommerceOrder Relationship Tests ---

test('ecommerce order has orderNotes relationship', function () {
    $order = new \App\Models\EcommerceOrder;

    expect(method_exists($order, 'orderNotes'))->toBeTrue()
        ->and($order->orderNotes())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
});

test('ecommerce order has reminders relationship', function () {
    $order = new \App\Models\EcommerceOrder;

    expect(method_exists($order, 'reminders'))->toBeTrue()
        ->and($order->reminders())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
});

test('ecommerce order has alerts relationship', function () {
    $order = new \App\Models\EcommerceOrder;

    expect(method_exists($order, 'alerts'))->toBeTrue()
        ->and($order->alerts())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
});
