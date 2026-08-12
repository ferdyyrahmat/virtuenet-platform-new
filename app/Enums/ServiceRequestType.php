<?php

namespace App\Enums;

enum ServiceRequestType: string
{
    case AiToken = 'ai_token';
    case CustomSystem = 'custom_system';
    case Integration = 'integration';
    case SaasSubscription = 'saas_subscription';

    public function label(): string
    {
        return match ($this) {
            self::AiToken => 'AI Token',
            self::CustomSystem => 'Custom System',
            self::Integration => 'System Integration',
            self::SaasSubscription => 'SaaS Subscription',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::AiToken => 'mdi-key-variant',
            self::CustomSystem => 'mdi-application-braces-outline',
            self::Integration => 'mdi-connection',
            self::SaasSubscription => 'mdi-credit-card-outline',
        };
    }

    public function approvalStages(): array
    {
        return match ($this) {
            self::AiToken => ['Technical & budget review'],
            self::CustomSystem => ['Scope review', 'Budget approval'],
            self::Integration => ['Technical review'],
            self::SaasSubscription => ['Business review', 'Procurement approval'],
        };
    }
}
