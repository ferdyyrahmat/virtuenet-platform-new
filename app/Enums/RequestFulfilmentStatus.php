<?php

namespace App\Enums;

enum RequestFulfilmentStatus: string
{
    case NotStarted = 'not_started';
    case Queued = 'queued';
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Failed = 'failed';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Completed = 'completed';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }

    public function color(): string
    {
        return match ($this) {
            self::NotStarted => 'secondary',
            self::Queued => 'info',
            self::Provisioning => 'warning',
            self::Active, self::Completed => 'success',
            self::Suspended => 'dark',
            self::Failed, self::Revoked => 'danger',
        };
    }
}
