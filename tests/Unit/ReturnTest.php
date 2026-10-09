<?php

use App\Enums\RefundMethod;
use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use App\Models\Refund;
use App\Models\ReturnItem;
use App\Models\SaleReturn;

uses(Tests\TestCase::class);

// --- SaleReturn Model Configuration Tests ---

test('sale return uses uuid as route key', function () {
    $return = new SaleReturn;

    expect($return->getRouteKeyName())->toBe('uuid');
});

test('sale return uses returns table', function () {
    $return = new SaleReturn;

    expect($return->getTable())->toBe('returns');
});

test('sale return casts status to ReturnStatus enum', function () {
    $return = new SaleReturn;
    $return->status = 'pending';

    expect($return->status)->toBeInstanceOf(ReturnStatus::class)
        ->and($return->status)->toBe(ReturnStatus::PENDING);
});

test('sale return casts reason to ReturnReason enum', function () {
    $return = new SaleReturn;
    $return->reason = 'defective';

    expect($return->reason)->toBeInstanceOf(ReturnReason::class)
        ->and($return->reason)->toBe(ReturnReason::DEFECTIVE);
});

test('sale return casts amounts to decimal', function () {
    $return = new SaleReturn;
    $return->total_amount = '150.50';
    $return->restocking_fee = '10.00';
    $return->refund_amount = '140.50';

    expect($return->total_amount)->toBe('150.50')
        ->and($return->restocking_fee)->toBe('10.00')
        ->and($return->refund_amount)->toBe('140.50');
});

test('sale return casts dates to datetime', function () {
    $return = new SaleReturn;
    $return->approved_at = '2026-01-15 10:00:00';
    $return->received_at = '2026-01-16 10:00:00';
    $return->inspected_at = '2026-01-17 10:00:00';

    expect($return->approved_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($return->received_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($return->inspected_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('sale return has expected fillable fields', function () {
    $return = new SaleReturn;
    $fillable = $return->getFillable();

    expect($fillable)->toContain('uuid')
        ->and($fillable)->toContain('sale_id')
        ->and($fillable)->toContain('customer_id')
        ->and($fillable)->toContain('shop_id')
        ->and($fillable)->toContain('return_number')
        ->and($fillable)->toContain('status')
        ->and($fillable)->toContain('reason')
        ->and($fillable)->toContain('notes')
        ->and($fillable)->toContain('total_amount')
        ->and($fillable)->toContain('restocking_fee')
        ->and($fillable)->toContain('refund_amount')
        ->and($fillable)->toContain('requested_by')
        ->and($fillable)->toContain('approved_by')
        ->and($fillable)->toContain('rejection_reason')
        ->and($fillable)->toContain('inspected_by')
        ->and($fillable)->toContain('inspection_notes');
});

// --- SaleReturn Helper Method Tests ---

test('isPending returns true for pending status', function () {
    $return = new SaleReturn;
    $return->status = ReturnStatus::PENDING;

    expect($return->isPending())->toBeTrue();
});

test('isPending returns false for approved status', function () {
    $return = new SaleReturn;
    $return->status = ReturnStatus::APPROVED;

    expect($return->isPending())->toBeFalse();
});

test('isApproved returns true for approved status', function () {
    $return = new SaleReturn;
    $return->status = ReturnStatus::APPROVED;

    expect($return->isApproved())->toBeTrue();
});

test('isRejected returns true for rejected status', function () {
    $return = new SaleReturn;
    $return->status = ReturnStatus::REJECTED;

    expect($return->isRejected())->toBeTrue();
});

test('isReceived returns true for received status', function () {
    $return = new SaleReturn;
    $return->status = ReturnStatus::RECEIVED;

    expect($return->isReceived())->toBeTrue();
});

test('isInspected returns true for inspected status', function () {
    $return = new SaleReturn;
    $return->status = ReturnStatus::INSPECTED;

    expect($return->isInspected())->toBeTrue();
});

test('isCompleted returns true for completed status', function () {
    $return = new SaleReturn;
    $return->status = ReturnStatus::COMPLETED;

    expect($return->isCompleted())->toBeTrue();
});

test('canBeApproved returns true only for pending status', function () {
    $pendingReturn = new SaleReturn;
    $pendingReturn->status = ReturnStatus::PENDING;

    $approvedReturn = new SaleReturn;
    $approvedReturn->status = ReturnStatus::APPROVED;

    expect($pendingReturn->canBeApproved())->toBeTrue()
        ->and($approvedReturn->canBeApproved())->toBeFalse();
});

test('canBeRejected returns true only for pending status', function () {
    $pendingReturn = new SaleReturn;
    $pendingReturn->status = ReturnStatus::PENDING;

    $completedReturn = new SaleReturn;
    $completedReturn->status = ReturnStatus::COMPLETED;

    expect($pendingReturn->canBeRejected())->toBeTrue()
        ->and($completedReturn->canBeRejected())->toBeFalse();
});

test('canBeReceived returns true only for approved status', function () {
    $approvedReturn = new SaleReturn;
    $approvedReturn->status = ReturnStatus::APPROVED;

    $pendingReturn = new SaleReturn;
    $pendingReturn->status = ReturnStatus::PENDING;

    expect($approvedReturn->canBeReceived())->toBeTrue()
        ->and($pendingReturn->canBeReceived())->toBeFalse();
});

test('canBeInspected returns true only for received status', function () {
    $receivedReturn = new SaleReturn;
    $receivedReturn->status = ReturnStatus::RECEIVED;

    $approvedReturn = new SaleReturn;
    $approvedReturn->status = ReturnStatus::APPROVED;

    expect($receivedReturn->canBeInspected())->toBeTrue()
        ->and($approvedReturn->canBeInspected())->toBeFalse();
});

test('canBeRefunded returns true for received, inspected, and completed statuses', function () {
    $received = new SaleReturn;
    $received->status = ReturnStatus::RECEIVED;

    $inspected = new SaleReturn;
    $inspected->status = ReturnStatus::INSPECTED;

    $completed = new SaleReturn;
    $completed->status = ReturnStatus::COMPLETED;

    $pending = new SaleReturn;
    $pending->status = ReturnStatus::PENDING;

    $rejected = new SaleReturn;
    $rejected->status = ReturnStatus::REJECTED;

    expect($received->canBeRefunded())->toBeTrue()
        ->and($inspected->canBeRefunded())->toBeTrue()
        ->and($completed->canBeRefunded())->toBeTrue()
        ->and($pending->canBeRefunded())->toBeFalse()
        ->and($rejected->canBeRefunded())->toBeFalse();
});

// --- SaleReturn Relationship Existence Tests ---

test('sale return has sale relationship', function () {
    $return = new SaleReturn;

    expect(method_exists($return, 'sale'))->toBeTrue()
        ->and($return->sale())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('sale return has customer relationship', function () {
    $return = new SaleReturn;

    expect(method_exists($return, 'customer'))->toBeTrue()
        ->and($return->customer())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('sale return has shop relationship', function () {
    $return = new SaleReturn;

    expect(method_exists($return, 'shop'))->toBeTrue()
        ->and($return->shop())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('sale return has items relationship', function () {
    $return = new SaleReturn;

    expect(method_exists($return, 'items'))->toBeTrue()
        ->and($return->items())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('sale return has refund relationship', function () {
    $return = new SaleReturn;

    expect(method_exists($return, 'refund'))->toBeTrue()
        ->and($return->refund())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class);
});

test('sale return has requestedBy relationship', function () {
    $return = new SaleReturn;

    expect(method_exists($return, 'requestedBy'))->toBeTrue()
        ->and($return->requestedBy())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('sale return has approvedBy relationship', function () {
    $return = new SaleReturn;

    expect(method_exists($return, 'approvedBy'))->toBeTrue()
        ->and($return->approvedBy())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('sale return has inspectedBy relationship', function () {
    $return = new SaleReturn;

    expect(method_exists($return, 'inspectedBy'))->toBeTrue()
        ->and($return->inspectedBy())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

// --- ReturnStatus Enum Tests ---

test('ReturnStatus has all expected cases', function () {
    expect(ReturnStatus::cases())->toHaveCount(6)
        ->and(ReturnStatus::PENDING->value)->toBe('pending')
        ->and(ReturnStatus::APPROVED->value)->toBe('approved')
        ->and(ReturnStatus::REJECTED->value)->toBe('rejected')
        ->and(ReturnStatus::RECEIVED->value)->toBe('received')
        ->and(ReturnStatus::INSPECTED->value)->toBe('inspected')
        ->and(ReturnStatus::COMPLETED->value)->toBe('completed');
});

test('ReturnStatus labels return correct strings', function () {
    expect(ReturnStatus::PENDING->label())->toBe('Pending Approval')
        ->and(ReturnStatus::APPROVED->label())->toBe('Approved')
        ->and(ReturnStatus::REJECTED->label())->toBe('Rejected')
        ->and(ReturnStatus::RECEIVED->label())->toBe('Items Received')
        ->and(ReturnStatus::INSPECTED->label())->toBe('Inspected')
        ->and(ReturnStatus::COMPLETED->label())->toBe('Completed');
});

test('ReturnStatus colors return correct values', function () {
    expect(ReturnStatus::PENDING->color())->toBe('warning')
        ->and(ReturnStatus::APPROVED->color())->toBe('info')
        ->and(ReturnStatus::REJECTED->color())->toBe('danger')
        ->and(ReturnStatus::RECEIVED->color())->toBe('primary')
        ->and(ReturnStatus::INSPECTED->color())->toBe('secondary')
        ->and(ReturnStatus::COMPLETED->color())->toBe('success');
});

test('ReturnStatus canEdit returns true only for pending', function () {
    expect(ReturnStatus::PENDING->canEdit())->toBeTrue()
        ->and(ReturnStatus::APPROVED->canEdit())->toBeFalse()
        ->and(ReturnStatus::COMPLETED->canEdit())->toBeFalse();
});

test('ReturnStatus canRefund returns true for received, inspected, completed', function () {
    expect(ReturnStatus::RECEIVED->canRefund())->toBeTrue()
        ->and(ReturnStatus::INSPECTED->canRefund())->toBeTrue()
        ->and(ReturnStatus::COMPLETED->canRefund())->toBeTrue()
        ->and(ReturnStatus::PENDING->canRefund())->toBeFalse()
        ->and(ReturnStatus::APPROVED->canRefund())->toBeFalse()
        ->and(ReturnStatus::REJECTED->canRefund())->toBeFalse();
});

// --- ReturnReason Enum Tests ---

test('ReturnReason has all expected cases', function () {
    expect(ReturnReason::cases())->toHaveCount(10)
        ->and(ReturnReason::DEFECTIVE->value)->toBe('defective')
        ->and(ReturnReason::DAMAGED->value)->toBe('damaged')
        ->and(ReturnReason::WRONG_ITEM->value)->toBe('wrong_item')
        ->and(ReturnReason::CUSTOMER_CHANGED_MIND->value)->toBe('customer_changed_mind')
        ->and(ReturnReason::OTHER->value)->toBe('other');
});

test('ReturnReason labels return correct strings', function () {
    expect(ReturnReason::DEFECTIVE->label())->toBe('Defective Product')
        ->and(ReturnReason::DAMAGED->label())->toBe('Damaged During Shipping')
        ->and(ReturnReason::WRONG_ITEM->label())->toBe('Wrong Item Received')
        ->and(ReturnReason::CUSTOMER_CHANGED_MIND->label())->toBe('Customer Changed Mind')
        ->and(ReturnReason::OTHER->label())->toBe('Other');
});

test('ReturnReason requiresInspection returns true for defective, damaged, quality, expired', function () {
    expect(ReturnReason::DEFECTIVE->requiresInspection())->toBeTrue()
        ->and(ReturnReason::DAMAGED->requiresInspection())->toBeTrue()
        ->and(ReturnReason::QUALITY_ISSUE->requiresInspection())->toBeTrue()
        ->and(ReturnReason::EXPIRED->requiresInspection())->toBeTrue()
        ->and(ReturnReason::CUSTOMER_CHANGED_MIND->requiresInspection())->toBeFalse()
        ->and(ReturnReason::OTHER->requiresInspection())->toBeFalse();
});

test('ReturnReason isRestockable returns true for mind change, wrong item, duplicate, no longer needed', function () {
    expect(ReturnReason::CUSTOMER_CHANGED_MIND->isRestockable())->toBeTrue()
        ->and(ReturnReason::WRONG_ITEM->isRestockable())->toBeTrue()
        ->and(ReturnReason::DUPLICATE_ORDER->isRestockable())->toBeTrue()
        ->and(ReturnReason::NO_LONGER_NEEDED->isRestockable())->toBeTrue()
        ->and(ReturnReason::DEFECTIVE->isRestockable())->toBeFalse()
        ->and(ReturnReason::DAMAGED->isRestockable())->toBeFalse();
});

// --- RefundMethod Enum Tests ---

test('RefundMethod has all expected cases', function () {
    expect(RefundMethod::cases())->toHaveCount(5)
        ->and(RefundMethod::CASH->value)->toBe('cash')
        ->and(RefundMethod::CARD->value)->toBe('card')
        ->and(RefundMethod::BANK_TRANSFER->value)->toBe('bank_transfer')
        ->and(RefundMethod::STORE_CREDIT->value)->toBe('store_credit')
        ->and(RefundMethod::ORIGINAL_PAYMENT->value)->toBe('original_payment');
});

test('RefundMethod labels return correct strings', function () {
    expect(RefundMethod::CASH->label())->toBe('Cash Refund')
        ->and(RefundMethod::CARD->label())->toBe('Card Refund')
        ->and(RefundMethod::BANK_TRANSFER->label())->toBe('Bank Transfer')
        ->and(RefundMethod::STORE_CREDIT->label())->toBe('Store Credit')
        ->and(RefundMethod::ORIGINAL_PAYMENT->label())->toBe('Original Payment Method');
});

test('RefundMethod requiresApproval returns true for cash and bank transfer', function () {
    expect(RefundMethod::CASH->requiresApproval())->toBeTrue()
        ->and(RefundMethod::BANK_TRANSFER->requiresApproval())->toBeTrue()
        ->and(RefundMethod::CARD->requiresApproval())->toBeFalse()
        ->and(RefundMethod::STORE_CREDIT->requiresApproval())->toBeFalse()
        ->and(RefundMethod::ORIGINAL_PAYMENT->requiresApproval())->toBeFalse();
});

// --- Refund Model Tests ---

test('refund uses uuid as route key', function () {
    $refund = new Refund;

    expect($refund->getRouteKeyName())->toBe('uuid');
});

test('refund casts method to RefundMethod enum', function () {
    $refund = new Refund;
    $refund->method = 'cash';

    expect($refund->method)->toBeInstanceOf(RefundMethod::class)
        ->and($refund->method)->toBe(RefundMethod::CASH);
});

test('refund casts amount to decimal', function () {
    $refund = new Refund;
    $refund->amount = '250.75';

    expect($refund->amount)->toBe('250.75');
});

test('refund casts processed_at to datetime', function () {
    $refund = new Refund;
    $refund->processed_at = '2026-01-15 10:00:00';

    expect($refund->processed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('refund has expected fillable fields', function () {
    $refund = new Refund;
    $fillable = $refund->getFillable();

    expect($fillable)->toContain('uuid')
        ->and($fillable)->toContain('return_id')
        ->and($fillable)->toContain('customer_id')
        ->and($fillable)->toContain('shop_id')
        ->and($fillable)->toContain('refund_number')
        ->and($fillable)->toContain('method')
        ->and($fillable)->toContain('amount')
        ->and($fillable)->toContain('status')
        ->and($fillable)->toContain('notes')
        ->and($fillable)->toContain('transaction_id')
        ->and($fillable)->toContain('reference_number')
        ->and($fillable)->toContain('processed_by')
        ->and($fillable)->toContain('processed_at')
        ->and($fillable)->toContain('failure_reason');
});

// --- Refund Helper Method Tests ---

test('refund isPending returns true for pending status', function () {
    $refund = new Refund;
    $refund->status = 'pending';

    expect($refund->isPending())->toBeTrue();
});

test('refund isProcessing returns true for processing status', function () {
    $refund = new Refund;
    $refund->status = 'processing';

    expect($refund->isProcessing())->toBeTrue();
});

test('refund isCompleted returns true for completed status', function () {
    $refund = new Refund;
    $refund->status = 'completed';

    expect($refund->isCompleted())->toBeTrue();
});

test('refund isFailed returns true for failed status', function () {
    $refund = new Refund;
    $refund->status = 'failed';

    expect($refund->isFailed())->toBeTrue();
});

test('refund status helpers return false for wrong status', function () {
    $refund = new Refund;
    $refund->status = 'completed';

    expect($refund->isPending())->toBeFalse()
        ->and($refund->isProcessing())->toBeFalse()
        ->and($refund->isFailed())->toBeFalse();
});

// --- Refund Relationship Existence Tests ---

test('refund has saleReturn relationship', function () {
    $refund = new Refund;

    expect(method_exists($refund, 'saleReturn'))->toBeTrue()
        ->and($refund->saleReturn())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('refund has customer relationship', function () {
    $refund = new Refund;

    expect(method_exists($refund, 'customer'))->toBeTrue()
        ->and($refund->customer())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('refund has shop relationship', function () {
    $refund = new Refund;

    expect(method_exists($refund, 'shop'))->toBeTrue()
        ->and($refund->shop())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

test('refund has processedBy relationship', function () {
    $refund = new Refund;

    expect(method_exists($refund, 'processedBy'))->toBeTrue()
        ->and($refund->processedBy())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});

// --- ReturnItem Model Tests ---

test('return item has expected relationships', function () {
    $item = new ReturnItem;

    expect(method_exists($item, 'saleReturn'))->toBeTrue()
        ->and($item->saleReturn())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and(method_exists($item, 'saleItem'))->toBeTrue()
        ->and($item->saleItem())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class)
        ->and(method_exists($item, 'product'))->toBeTrue()
        ->and($item->product())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});
