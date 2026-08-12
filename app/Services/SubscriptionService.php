<?php

namespace App\Services;

use App\Models\FinancialEntry;
use App\Models\Subscription;
use App\Models\SubscriptionEvidence;
use App\Models\SubscriptionReminder;
use App\Models\SubscriptionRenewalDecision;
use App\Models\SubscriptionVersion;
use App\Models\SystemNotification;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function create(array $data, User $actor, ?UploadedFile $evidence = null): Subscription
    {
        return DB::transaction(function () use ($data, $actor, $evidence): Subscription {
            $fingerprint = hash('sha256', Str::lower(trim($data['account_identifier'])));
            $identityFingerprint = hash('sha256', Str::lower(trim($data['vendor'])).'|'.Str::lower(trim($data['product'])).'|'.$fingerprint.'|'.$data['next_renewal_date']);
            $duplicate = Subscription::withTrashed()
                ->where('vendor', $data['vendor'])
                ->where('product', $data['product'])
                ->where('account_fingerprint', $fingerprint)
                ->where(function ($query) use ($data): void {
                    isset($data['next_renewal_date'])
                        ? $query->whereDate('next_renewal_date', $data['next_renewal_date'])
                        : $query->whereNull('next_renewal_date');
                })->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['account_identifier' => 'A subscription for this vendor, product, account, and renewal period already exists.']);
            }

            $subscription = Subscription::create([
                ...collect($data)->except(['beneficiary_ids', 'quantity', 'unit_price', 'discount', 'tax', 'fee', 'currency', 'fx_rate', 'fx_source', 'fx_effective_at', 'effective_from', 'version_reason', 'version_evidence_reference'])->all(),
                'account_fingerprint' => $fingerprint,
                'identity_fingerprint' => $identityFingerprint,
                'status' => 'draft',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
                'reminder_days' => $data['reminder_days'] ?? [30, 14, 7, 3, 1, 0],
            ]);
            $subscription->beneficiaries()->sync($data['beneficiary_ids'] ?? []);
            $version = $this->createVersion($subscription, $data, $actor);
            $this->event($subscription, null, 'draft', $actor, 'Subscription registered; commercial terms await checker approval.');
            if ($evidence) {
                $this->storeEvidence($subscription, $evidence, $actor, 'commercial');
            }

            return $subscription->setRelation('currentVersion', $version);
        });
    }

    public function approveVersion(SubscriptionVersion $version, User $checker): Subscription
    {
        return DB::transaction(function () use ($version, $checker): Subscription {
            $version = SubscriptionVersion::query()->lockForUpdate()->findOrFail($version->id);
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($version->subscription_id);
            $this->approveVersionLocked($subscription, $version, $checker);

            return $subscription->refresh();
        });
    }

    public function transition(Subscription $subscription, string $next, User $actor, string $reason, ?string $evidenceReference = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $next, $actor, $reason, $evidenceReference): Subscription {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $allowed = [
                'draft' => ['trial', 'active', 'cancelled'],
                'trial' => ['active', 'cancelled', 'expired'],
                'active' => ['renewal_review', 'scheduled_to_cancel', 'suspended', 'payment_failed'],
                'renewal_review' => ['active', 'scheduled_to_cancel', 'suspended'],
                'scheduled_to_cancel' => ['active', 'cancelled'],
                'payment_failed' => ['active', 'suspended', 'cancelled'],
                'suspended' => ['active', 'cancelled', 'expired'],
            ];
            if (! in_array($next, $allowed[$subscription->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'That lifecycle transition is not allowed.']);
            }
            if ($next === 'active' && ! $subscription->current_version_id) {
                throw ValidationException::withMessages(['status' => 'Commercial terms must be approved before activation.']);
            }
            $from = $subscription->status;
            $subscription->update(['status' => $next, 'updated_by' => $actor->id]);
            $this->event($subscription, $from, $next, $actor, $reason, $evidenceReference);

            return $subscription;
        });
    }

    public function proposeRenewal(Subscription $subscription, array $data, User $maker, ?UploadedFile $evidence = null): SubscriptionRenewalDecision
    {
        return DB::transaction(function () use ($subscription, $data, $maker, $evidence): SubscriptionRenewalDecision {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            if (! $subscription->current_version_id) {
                throw ValidationException::withMessages(['subscription' => 'Approve the initial commercial version first.']);
            }
            if ($subscription->renewalDecisions()->where('status', 'pending_review')->exists()) {
                throw ValidationException::withMessages(['subscription' => 'A renewal decision is already waiting for checker review.']);
            }

            $proposedVersion = $data['decision'] === 'renew_changed'
                ? $this->createVersion($subscription, $data, $maker)
                : null;
            $decision = $subscription->renewalDecisions()->create([
                'current_version_id' => $subscription->current_version_id,
                'proposed_version_id' => $proposedVersion?->id,
                'renewal_date' => $subscription->next_renewal_date,
                ...collect($data)->only(['decision', 'next_renewal_date', 'final_service_date', 'decision_due_at', 'reason', 'evidence_reference'])->all(),
                'made_by' => $maker->id,
            ]);
            if ($subscription->status === 'active') {
                $subscription->update(['status' => 'renewal_review', 'updated_by' => $maker->id]);
                $this->event($subscription, 'active', 'renewal_review', $maker, 'Renewal decision submitted for checker review.');
            }
            if ($evidence) {
                $this->storeEvidence($subscription, $evidence, $maker, 'renewal', $decision);
            }

            return $decision;
        });
    }

    public function reviewRenewal(SubscriptionRenewalDecision $decision, User $checker, bool $approve, ?string $note = null): SubscriptionRenewalDecision
    {
        return DB::transaction(function () use ($decision, $checker, $approve, $note): SubscriptionRenewalDecision {
            $decision = SubscriptionRenewalDecision::query()->lockForUpdate()->findOrFail($decision->id);
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($decision->subscription_id);
            if ($decision->status !== 'pending_review') {
                throw ValidationException::withMessages(['decision' => 'This decision has already been reviewed.']);
            }
            if ($decision->made_by === $checker->id) {
                throw ValidationException::withMessages(['decision' => 'Maker and checker must be different users.']);
            }

            $decision->update([
                'status' => $approve ? 'approved' : 'rejected',
                'checked_by' => $checker->id,
                'checked_at' => now(),
                'reason' => $note ? $decision->reason."\nChecker: ".$note : $decision->reason,
            ]);
            if (! $approve) {
                $decision->proposedVersion?->update(['status' => 'rejected', 'checked_by' => $checker->id, 'checked_at' => now()]);
                SystemNotification::send($decision->made_by, 'Renewal decision rejected', $subscription->vendor.' '.$subscription->product.' needs revision.', 'warning', 'mdi-calendar-alert', route('admin.subscriptions.index', ['tab' => 'renewals']));

                return $decision;
            }

            if ($decision->proposedVersion) {
                $this->approveVersionLocked($subscription, $decision->proposedVersion, $checker, false);
            }
            $target = match ($decision->decision) {
                'renew_unchanged', 'renew_changed' => 'active',
                'cancel', 'replace' => 'scheduled_to_cancel',
                default => 'renewal_review',
            };
            $from = $subscription->status;
            $subscription->update([
                'status' => $target,
                'next_renewal_date' => $decision->next_renewal_date ?: $subscription->next_renewal_date,
                'contract_end_date' => $decision->final_service_date ?: $subscription->contract_end_date,
                'updated_by' => $checker->id,
            ]);
            $this->event($subscription, $from, $target, $checker, 'Approved renewal decision: '.str($decision->decision)->replace('_', ' ')->title().'.', $decision->evidence_reference);
            SystemNotification::send($subscription->owner_id, 'Renewal decision approved', $subscription->vendor.' '.$subscription->product.' is now '.$target.'.', 'success', 'mdi-calendar-check', route('v1.subscriptions.index'));
            if (in_array($decision->decision, ['renew_unchanged', 'renew_changed'], true)) {
                SubscriptionReminder::firstOrCreate([
                    'subscription_id' => $subscription->id,
                    'checkpoint' => 'post_renewal_evidence',
                    'renewal_date' => $decision->renewal_date,
                ], [
                    'due_at' => $decision->renewal_date,
                    'delivery_state' => 'pending',
                    'channel' => 'in_app',
                ]);
                SystemNotification::send($subscription->renewal_owner_id, 'Post-renewal confirmation required', 'Confirm the vendor renewal and attach the invoice or receipt for '.$subscription->vendor.' '.$subscription->product.'.', 'warning', 'mdi-receipt-text-clock-outline', route('admin.subscriptions.index', ['tab' => 'renewals']));
            }

            return $decision;
        });
    }

    public function storeEvidence(Subscription $subscription, UploadedFile $file, User $actor, string $type, ?SubscriptionRenewalDecision $decision = null): SubscriptionEvidence
    {
        $hash = hash_file('sha256', $file->getRealPath());
        if ($subscription->evidences()->where('sha256', $hash)->exists()) {
            throw ValidationException::withMessages(['evidence' => 'This evidence file is already attached.']);
        }
        $path = $file->storeAs('subscriptions/'.$subscription->id, Str::uuid().'.'.$file->getClientOriginalExtension(), 'local');
        if (! $path || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['evidence' => 'The evidence file could not be stored.']);
        }

        $evidence = $subscription->evidences()->create([
            'renewal_decision_id' => $decision?->id,
            'type' => $type,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'sha256' => $hash,
            'uploaded_by' => $actor->id,
        ]);
        if (in_array($type, ['invoice', 'receipt'], true)) {
            $subscription->reminders()->where('checkpoint', 'post_renewal_evidence')->whereNull('acknowledged_at')->update([
                'delivery_state' => 'acknowledged',
                'acknowledged_at' => now(),
                'acknowledged_by' => $actor->id,
            ]);
        }

        return $evidence;
    }

    private function createVersion(Subscription $subscription, array $data, User $actor): SubscriptionVersion
    {
        $quantity = BigDecimal::of((string) $data['quantity']);
        $unitPrice = BigDecimal::of((string) $data['unit_price']);
        $subtotal = $quantity->multipliedBy($unitPrice);
        $total = $subtotal
            ->minus((string) ($data['discount'] ?? 0))
            ->plus((string) ($data['tax'] ?? 0))
            ->plus((string) ($data['fee'] ?? 0));
        if ($total->isLessThan(0)) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot make the commercial total negative.']);
        }
        $normalized = $total->multipliedBy((string) $data['fx_rate'])->toScale(2, RoundingMode::HalfUp);

        return $subscription->versions()->create([
            'version' => ((int) $subscription->versions()->max('version')) + 1,
            'quantity' => (string) $quantity,
            'unit_price' => (string) $unitPrice,
            'subtotal' => (string) $subtotal,
            'discount' => (string) ($data['discount'] ?? 0),
            'tax' => (string) ($data['tax'] ?? 0),
            'fee' => (string) ($data['fee'] ?? 0),
            'currency' => strtoupper($data['currency']),
            'normalized_idr' => (string) $normalized,
            'fx_rate' => (string) $data['fx_rate'],
            'fx_source' => $data['fx_source'],
            'fx_effective_at' => $data['fx_effective_at'],
            'billing_cycle' => $data['billing_cycle'],
            'billing_interval_months' => $data['billing_interval_months'] ?? null,
            'effective_from' => $data['effective_from'],
            'reason' => $data['version_reason'] ?? $data['reason'],
            'evidence_reference' => $data['version_evidence_reference'] ?? $data['evidence_reference'] ?? null,
            'proposed_by' => $actor->id,
        ]);
    }

    private function approveVersionLocked(Subscription $subscription, SubscriptionVersion $version, User $checker, bool $activate = true): void
    {
        if ($version->status !== 'pending_review') {
            throw ValidationException::withMessages(['version' => 'This commercial version is not pending review.']);
        }
        if ($version->proposed_by === $checker->id) {
            throw ValidationException::withMessages(['version' => 'Maker and checker must be different users.']);
        }
        $version->update(['status' => 'approved', 'checked_by' => $checker->id, 'checked_at' => now()]);
        $from = $subscription->status;
        $subscription->update([
            'current_version_id' => $version->id,
            'billing_cycle' => $version->billing_cycle,
            'billing_interval_months' => $version->billing_interval_months,
            'status' => $activate && $subscription->status === 'draft' ? 'active' : $subscription->status,
            'updated_by' => $checker->id,
        ]);
        if ($subscription->status !== $from) {
            $this->event($subscription, $from, $subscription->status, $checker, 'Initial commercial terms approved by checker.');
        }
        FinancialEntry::firstOrCreate(['source_key' => hash('sha256', 'subscription-version:'.$version->id)], [
            'reference' => 'FIN-'.now()->format('Ym').'-'.Str::upper(Str::random(8)),
            'kind' => 'subscription',
            'status' => 'projected',
            'subscription_id' => $subscription->id,
            'service_request_id' => $subscription->service_request_id,
            'payment_instrument_id' => $subscription->payment_instrument_id,
            'department_id' => $subscription->department_id,
            'owner_id' => $subscription->owner_id,
            'cost_center' => $subscription->cost_center,
            'vendor' => $subscription->vendor,
            'accounting_period' => $version->effective_from->copy()->startOfMonth(),
            'occurred_on' => $version->effective_from,
            'original_amount' => (string) BigDecimal::of($version->subtotal)->minus($version->discount)->plus($version->tax)->plus($version->fee),
            'currency' => $version->currency,
            'normalized_idr' => $version->normalized_idr,
            'fx_rate' => $version->fx_rate,
            'fx_source' => $version->fx_source,
            'fx_effective_at' => $version->fx_effective_at,
            'normalization_method' => 'approved subscription commercial version',
            'evidence_reference' => $version->evidence_reference,
            'created_by' => $checker->id,
        ]);
        SystemNotification::send($subscription->owner_id, 'Subscription terms approved', $subscription->vendor.' '.$subscription->product.' is ready for lifecycle management.', 'success', 'mdi-credit-card-check-outline', route('v1.subscriptions.index'));
    }

    private function event(Subscription $subscription, ?string $from, string $to, ?User $actor, string $reason, ?string $evidenceReference = null): void
    {
        $subscription->lifecycleEvents()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'reason' => $reason,
            'evidence_reference' => $evidenceReference,
            'occurred_at' => now(),
        ]);
    }
}
