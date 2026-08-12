<?php

namespace App\Console\Commands;

use App\Services\LarkApprovalIngestor;
use App\Services\LarkService;
use Illuminate\Console\Command;

class SyncLarkApprovals extends Command
{
    protected $signature = 'platform:sync-lark-approvals {--instance= : Synchronize one instance code} {--hours=1 : Lookback window, maximum 10 hours}';

    protected $description = 'Reconcile pending approval state from Lark';

    public function handle(LarkService $lark, LarkApprovalIngestor $ingestor): int
    {
        if (! $lark->configuredForApproval()) {
            $this->warn('Lark approval is disabled or incomplete.');

            return self::SUCCESS;
        }

        if ($instance = $this->option('instance')) {
            $ingestor->ingest((string) $instance);

            return self::SUCCESS;
        }

        $hours = max(1, min(10, (int) $this->option('hours')));
        $start = now()->subHours($hours)->getTimestampMs();
        $end = now()->getTimestampMs();
        $pageToken = null;
        do {
            $page = $lark->approvalInstanceCodes($start, $end, $pageToken);
            foreach ((array) data_get($page, 'instance_code_list', []) as $instanceCode) {
                $ingestor->ingest((string) $instanceCode);
            }
            $pageToken = data_get($page, 'has_more') ? data_get($page, 'page_token') : null;
        } while ($pageToken);

        return self::SUCCESS;
    }
}
