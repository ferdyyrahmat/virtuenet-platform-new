<?php

namespace App\Http\Requests;

use App\Enums\ServiceRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $details = $this->input('details', []);
        foreach (['models', 'ai_models'] as $key) {
            if (is_string($details[$key] ?? null)) {
                $details[$key] = collect(explode(',', $details[$key]))
                    ->map(fn (string $model): string => trim($model))
                    ->filter()
                    ->values()
                    ->all();
            }
        }
        $this->merge([
            'details' => $details,
            'currency' => strtoupper((string) $this->input('currency', 'USD')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $type = ServiceRequestType::tryFrom((string) $this->input('type'));

        return [
            'type' => ['required', Rule::enum(ServiceRequestType::class)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'requested_due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'estimated_budget' => ['nullable', 'required_if:type,saas_subscription', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['required', 'alpha', 'size:3'],
            'details' => ['required', 'array'],
            ...$this->detailRules($type),
        ];
    }

    private function detailRules(?ServiceRequestType $type): array
    {
        return match ($type) {
            ServiceRequestType::AiToken => [
                'details.purpose' => ['required', 'string', 'max:3000'],
                'details.models' => ['required', 'array', 'min:1', 'max:20'],
                'details.models.*' => ['string', 'max:120'],
                'details.max_budget' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
                'details.budget_duration' => ['required', Rule::in(['1d', '7d', '30d', 'monthly'])],
                'details.rpm_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
                'details.tpm_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            ],
            ServiceRequestType::CustomSystem => [
                'details.problem' => ['required', 'string', 'max:5000'],
                'details.target_users' => ['required', 'string', 'max:2000'],
                'details.capabilities' => ['required', 'string', 'max:5000'],
                'details.needs_ai_analyzer' => ['nullable', 'boolean'],
                'details.ai_models' => ['nullable', 'array', 'max:20'],
                'details.ai_models.*' => ['string', 'max:120'],
                'details.ai_max_budget' => ['nullable', 'required_if:details.needs_ai_analyzer,1', 'numeric', 'min:0.01'],
            ],
            ServiceRequestType::Integration => [
                'details.source_system' => ['required', 'string', 'max:160'],
                'details.target_system' => ['required', 'string', 'max:160'],
                'details.scope' => ['required', 'string', 'max:5000'],
                'details.access_status' => ['required', Rule::in(['available', 'partial', 'not_available', 'unknown'])],
            ],
            ServiceRequestType::SaasSubscription => [
                'details.product' => ['required', 'string', 'max:160'],
                'details.plan' => ['required', 'string', 'max:160'],
                'details.seats' => ['required', 'integer', 'min:1', 'max:100000'],
                'details.billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'yearly', 'one_time'])],
                'details.vendor_url' => ['nullable', 'url:http,https', 'max:2048'],
                'details.business_reason' => ['required', 'string', 'max:3000'],
            ],
            default => [],
        };
    }
}
