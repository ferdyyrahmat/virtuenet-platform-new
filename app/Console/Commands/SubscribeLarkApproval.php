<?php

namespace App\Console\Commands;

use App\Services\LarkService;
use Illuminate\Console\Command;

class SubscribeLarkApproval extends Command
{
    protected $signature = 'platform:subscribe-lark-approval';

    protected $description = 'Subscribe the configured Lark Modified definition to approval events';

    public function handle(LarkService $lark): int
    {
        if (! $lark->approvalContractIsSafe()) {
            $this->error('The exact Lark Modified approval contract is not configured.');

            return self::FAILURE;
        }

        $lark->subscribeApproval();
        $this->info('Subscribed to Lark Modified approval events.');

        return self::SUCCESS;
    }
}
