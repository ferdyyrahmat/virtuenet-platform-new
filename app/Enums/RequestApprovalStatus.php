<?php

namespace App\Enums;

enum RequestApprovalStatus: string
{
    case Submitted = 'submitted';
    case InApproval = 'in_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case RevisionRequired = 'revision_required';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::InApproval => 'warning',
            self::Approved => 'success',
            self::RevisionRequired => 'secondary',
            self::Rejected, self::Cancelled => 'danger',
        };
    }
}
