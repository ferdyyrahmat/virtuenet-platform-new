<?php

namespace App\Jobs;

use App\Models\LarkApprovalContract;
use App\Models\ServiceRequest;
use App\Services\LarkService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;

class CreateLarkApproval implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [30, 60, 180, 300];

    public function __construct(public ServiceRequest $request) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('lark-approval-'.$this->request->id))->expireAfter(180)];
    }

    public function handle(LarkService $lark): void
    {
        $request = $this->request->fresh(['requester', 'department']);
        if ($request->approval_source !== 'lark' || filled($request->lark_instance_code)) {
            return;
        }
        if (! config('services.lark.approval_outbound_enabled')) {
            throw new RuntimeException('Outbound Lark approval creation is disabled.');
        }
        if (! $lark->approvalContractIsSafe() || blank($request->requester->lark_open_id)) {
            throw new RuntimeException('Lark approval is enabled but its connection or requester Open ID is missing.');
        }

        $contract = LarkApprovalContract::query()
            ->where('approval_code', config('services.lark.approval_code'))->where('active', true)->first();
        $fieldMap = config('services.lark.approval_field_map', []);
        if (! $contract || $fieldMap === []) {
            throw new RuntimeException('The active Lark Modified form contract and field map are required for outbound creation.');
        }

        $values = [
            'request_code' => $request->code,
            'title' => $request->title,
            'request_type' => $request->type->value,
            'department' => $request->department?->name ?? '-',
            'requester' => $request->requester->name,
            'description' => $request->description,
            'business_justification' => data_get($request->details, 'business_justification', '-'),
            'requires_ai' => $request->type->value === 'ai_token' || data_get($request->details, 'needs_ai_analyzer') ? 'yes' : 'no',
            'submission_date' => $request->submitted_at?->toDateString() ?? now()->toDateString(),
        ];
        $controls = collect($contract->controls)->keyBy(fn (array $control): string => (string) ($control['custom_id'] ?? $control['id'] ?? ''));
        $form = collect($fieldMap)->map(function ($controlId, $semantic) use ($controls, $values): array {
            $control = $controls->get((string) $controlId);
            if (! $control || ! array_key_exists($semantic, $values)) {
                throw new RuntimeException('Lark field map does not match the active contract: '.$semantic.'.');
            }

            return ['id' => $control['id'], 'type' => $control['type'], 'value' => $values[$semantic]];
        })->values()->all();

        $response = $lark->createApprovalInstance(array_filter([
            'approval_code' => config('services.lark.approval_code'),
            'open_id' => $request->requester->lark_open_id,
            'form' => json_encode($form, JSON_THROW_ON_ERROR),
            'node_approver_open_id_list' => $lark->approvalNodeApprovers(),
        ], fn ($value): bool => $value !== []));

        $instanceCode = data_get($response, 'instance_code');
        if (blank($instanceCode)) {
            throw new RuntimeException('Lark did not return an approval instance code.');
        }

        $request->update([
            'lark_instance_code' => $instanceCode,
            'lark_status' => 'PENDING',
            'approval_sync_status' => 'synced',
            'approval_synced_at' => now(),
        ]);
        $request->updates()->create(['type' => 'approval_sync', 'message' => 'Lark approval instance created.']);
    }

    public function failed(?\Throwable $exception): void
    {
        $this->request->update(['approval_sync_status' => 'failed']);
        $this->request->updates()->create([
            'type' => 'integration_error',
            'message' => 'Lark approval creation needs operator attention.',
            'metadata' => ['error' => $exception?->getMessage()],
        ]);
    }
}
