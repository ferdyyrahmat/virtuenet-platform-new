<?php

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case RevisionRequested = 'revision_requested';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case WaitingExternal = 'waiting_external';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'info',
            self::UnderReview, self::WaitingExternal => 'warning',
            self::RevisionRequested => 'secondary',
            self::Approved, self::Completed => 'success',
            self::InProgress => 'primary',
            self::Rejected, self::Cancelled => 'danger',
        };
    }
}
