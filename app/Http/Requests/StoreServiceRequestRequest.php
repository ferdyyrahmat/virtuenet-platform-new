<?php

namespace App\Http\Requests;

use App\Enums\ServiceRequestType;
use App\Models\RequestTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'schema_version' => (int) $this->input('schema_version', 1),
            'idempotency_key' => $this->header('Idempotency-Key') ?: $this->input('idempotency_key'),
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
            'schema_version' => ['required', 'integer', Rule::in([$type?->schemaVersion() ?? 1])],
            'template_id' => ['nullable', Rule::exists('request_templates', 'id')->where('active', true)],
            'department_id' => ['nullable', Rule::exists('department_user', 'department_id')->where('user_id', $this->user()?->id)],
            'parent_id' => ['nullable', Rule::exists('service_requests', 'id')->where('requester_id', $this->user()?->id)],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'requested_due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'estimated_budget' => ['nullable', 'required_if:type,saas_subscription', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['required', 'alpha', 'size:3'],
            'details' => ['required', 'array'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,doc,docx,xls,xlsx,csv,txt'],
            ...$this->detailRules($type),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $definitionExists = DB::table('request_type_definitions')
                ->where('type', $this->string('type'))
                ->where('version', $this->integer('schema_version'))
                ->where('active', true)
                ->exists();
            if (! $definitionExists) {
                $validator->errors()->add('schema_version', 'This request type or schema version is not currently accepted.');
            }
            if (! $this->filled('template_id')) {
                return;
            }
            $matches = RequestTemplate::query()
                ->whereKey($this->integer('template_id'))
                ->where('request_type', $this->string('type'))
                ->where('version', $this->integer('schema_version'))
                ->exists();
            if (! $matches) {
                $validator->errors()->add('template_id', 'The template does not match this request type and schema version.');
            }
        }];
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
                'details.vendor' => ['required', 'string', 'max:160'],
                'details.product' => ['required', 'string', 'max:160'],
                'details.plan' => ['required', 'string', 'max:160'],
                'details.category' => ['required', 'string', 'max:100'],
                'details.seats' => ['required', 'integer', 'min:1', 'max:100000'],
                'details.billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'annual', 'multi_year', 'usage_based', 'custom'])],
                'details.vendor_url' => ['nullable', 'url:http,https', 'max:2048'],
                'details.business_reason' => ['required', 'string', 'max:3000'],
            ],
            ServiceRequestType::Support => [],
            default => [],
        };
    }
}
