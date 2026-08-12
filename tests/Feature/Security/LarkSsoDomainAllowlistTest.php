<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Auth\LarkSsoController;
use Tests\TestCase;

class LarkSsoDomainAllowlistTest extends TestCase
{
    public function test_allows_all_domains_when_allowlist_is_blank(): void
    {
        config(['services.lark.allowed_domains' => null]);

        $this->assertTrue(app(LarkSsoController::class)->isEmailDomainAllowed('anyone@example.com'));
    }

    public function test_rejects_domain_not_in_allowlist(): void
    {
        config(['services.lark.allowed_domains' => 'virtuenet.id,example.com']);

        $this->assertFalse(app(LarkSsoController::class)->isEmailDomainAllowed('user@evil.dev'));
    }

    public function test_accepts_domain_in_allowlist_case_insensitive(): void
    {
        config(['services.lark.allowed_domains' => 'VIRTUENET.ID, Example.COM']);

        $this->assertTrue(app(LarkSsoController::class)->isEmailDomainAllowed('User@virtuenet.id'));
        $this->assertTrue(app(LarkSsoController::class)->isEmailDomainAllowed('user@example.com'));
    }

    public function test_rejects_email_without_domain_when_allowlist_is_set(): void
    {
        config(['services.lark.allowed_domains' => 'virtuenet.id']);

        $this->assertFalse(app(LarkSsoController::class)->isEmailDomainAllowed('not-an-email'));
    }
}