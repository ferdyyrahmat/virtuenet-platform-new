<?php

namespace App\Services;

use App\Enums\ServiceRequestType;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

class ServiceRequestDetailWriter
{
    public function sync(ServiceRequest $request): void
    {
        $details = $request->details ?? [];
        [$table, $values] = match ($request->type) {
            ServiceRequestType::AiToken => ['ai_access_request_details', [
                'purpose' => data_get($details, 'purpose'),
                'models' => $this->json(data_get($details, 'models', [])),
                'max_budget' => data_get($details, 'max_budget'),
                'budget_duration' => data_get($details, 'budget_duration'),
                'rpm_limit' => data_get($details, 'rpm_limit'),
                'tpm_limit' => data_get($details, 'tpm_limit'),
            ]],
            ServiceRequestType::CustomSystem => ['system_development_request_details', [
                'problem' => data_get($details, 'problem'),
                'target_users' => data_get($details, 'target_users'),
                'capabilities' => data_get($details, 'capabilities'),
                'needs_ai_analyzer' => (bool) data_get($details, 'needs_ai_analyzer'),
                'ai_models' => $this->json(data_get($details, 'ai_models', [])),
                'ai_max_budget' => data_get($details, 'ai_max_budget'),
            ]],
            ServiceRequestType::Integration => ['integration_request_details', [
                'source_system' => data_get($details, 'source_system'),
                'target_system' => data_get($details, 'target_system'),
                'scope' => data_get($details, 'scope'),
                'access_status' => data_get($details, 'access_status'),
            ]],
            ServiceRequestType::SaasSubscription => ['subscription_purchase_request_details', [
                'vendor' => data_get($details, 'vendor'),
                'product' => data_get($details, 'product'),
                'plan' => data_get($details, 'plan'),
                'category' => data_get($details, 'category'),
                'seats' => data_get($details, 'seats'),
                'billing_cycle' => data_get($details, 'billing_cycle'),
                'vendor_url' => data_get($details, 'vendor_url'),
                'business_reason' => data_get($details, 'business_reason'),
            ]],
            ServiceRequestType::Support => [null, []],
        };

        if ($table) {
            $exists = DB::table($table)->where('service_request_id', $request->id)->exists();
            DB::table($table)->updateOrInsert(
                ['service_request_id' => $request->id],
                [...$values, 'updated_at' => now(), ...($exists ? [] : ['created_at' => now()])]
            );
        }
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
