<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::PAID => 'primary',
            self::CANCELLED => 'dark',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED]);
    }

    public function canApprove(): bool
    {
        return $this === self::PENDING;
    }

    public function canPay(): bool
    {
        return $this === self::APPROVED;
    }
}
