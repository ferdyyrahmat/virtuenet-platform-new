<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\SubscriptionReminder;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSubscriptionRenewals implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function handle(): void
    {
        Subscription::query()
            ->with(['paymentInstrument', 'renewalDecisions'])
            ->whereIn('status', ['trial', 'active', 'renewal_review', 'scheduled_to_cancel', 'payment_failed'])
            ->whereNotNull('next_renewal_date')
            ->orderBy('id')
            ->chunkById(100, fn ($subscriptions) => $subscriptions->each(fn (Subscription $subscription) => $this->process($subscription)));
    }

    private function process(Subscription $subscription): void
    {
        $today = now('Asia/Jakarta')->startOfDay();
        $renewal = $subscription->next_renewal_date->copy()->startOfDay();
        $instrument = $subscription->paymentInstrument;
        if ($instrument?->expiry_year && $instrument?->expiry_month) {
            $expires = now('Asia/Jakarta')->setDate($instrument->expiry_year, $instrument->expiry_month, 1)->endOfMonth();
            if ($expires->lt($renewal) && $subscription->status !== 'payment_failed') {
                $this->moveToReview($subscription, 'payment_failed', 'Payment instrument expires before the next renewal.');
                $this->deliver($subscription, 'payment_failed', $renewal);

                return;
            }
        }

        $hasDecision = $subscription->renewalDecisions->contains(fn ($decision): bool => $decision->status === 'approved' && $decision->renewal_date?->isSameDay($renewal));
        if ($subscription->auto_renew && $subscription->cancellation_deadline?->startOfDay()->lte($today) && ! $hasDecision) {
            $this->moveToReview($subscription, 'renewal_review', 'Auto-renew governance deadline passed without an approved decision.');
            $this->deliver($subscription, 'governance_overdue', $renewal, true);

            return;
        }

        $checkpoint = $renewal->lt($today)
            ? 'overdue'
            : collect($subscription->reminder_days ?: [30, 14, 7, 3, 1, 0])
                ->map(fn ($days): array => ['days' => (int) $days, 'due' => $renewal->copy()->subDays((int) $days)])
                ->filter(fn (array $item): bool => $item['due']->lte($today))
                ->sortByDesc(fn (array $item) => $item['due']->timestamp)
                ->map(fn (array $item): string => 'd-'.$item['days'])
                ->first();
        if ($checkpoint) {
            $this->deliver($subscription, $checkpoint, $renewal, $checkpoint === 'overdue');
        }
    }

    private function moveToReview(Subscription $subscription, string $status, string $reason): void
    {
        if ($subscription->status === $status) {
            return;
        }
        $from = $subscription->status;
        $subscription->update(['status' => $status]);
        $subscription->lifecycleEvents()->create([
            'from_status' => $from,
            'to_status' => $status,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }

    private function deliver(Subscription $subscription, string $checkpoint, mixed $renewal, bool $escalated = false): void
    {
        if (SubscriptionReminder::where('subscription_id', $subscription->id)->where('checkpoint', $checkpoint)->whereDate('renewal_date', $renewal)->exists()) {
            return;
        }
        try {
            $reminder = SubscriptionReminder::create([
                'subscription_id' => $subscription->id,
                'checkpoint' => $checkpoint,
                'renewal_date' => $renewal->toDateString(),
                'due_at' => now(),
                'delivery_state' => 'pending',
                'channel' => 'in_app',
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        $recipientIds = collect([$subscription->owner_id, $subscription->renewal_owner_id])
            ->merge(User::permission(['manage subscriptions', 'review subscription finances'])->pluck('id'))
            ->filter()->unique();
        foreach ($recipientIds as $userId) {
            $recipient = User::find($userId);
            if (! $recipient) {
                continue;
            }
            $url = $recipient->can('view subscriptions')
                ? route('admin.subscriptions.index', ['tab' => 'renewals'])
                : route('v1.subscriptions.index');
            SystemNotification::send(
                $recipient,
                $escalated ? 'Subscription renewal escalation' : 'Subscription renewal reminder',
                $subscription->vendor.' '.$subscription->product.' renews on '.$renewal->format('d M Y').' ('.$checkpoint.').',
                $escalated ? 'warning' : 'info',
                $escalated ? 'mdi-calendar-alert' : 'mdi-calendar-clock',
                $url
            );
        }
        $reminder->update([
            'delivery_state' => 'delivered',
            'delivered_at' => now(),
            'escalated_at' => $escalated ? now() : null,
        ]);
    }
}
