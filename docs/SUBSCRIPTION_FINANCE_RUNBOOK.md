# Subscription and Finance Runbook

## Operating boundary

Virtuenet is the governance and reporting control plane. Vendor billing remains authoritative for successful charges and renewals, while LiteLLM remains authoritative for AI usage. The platform imports references and usage data; it does not clone vendor or LiteLLM functions.

Notifications are in-app only, following the platform notification decision. Lark remains the approval/integration connector and no outbound reminder message is sent from this module.

## Roles and permissions

- `view subscriptions`: registry and renewal calendar.
- `manage subscriptions`: registry, lifecycle, evidence, renewal proposals, and masked instruments.
- `review subscription finances`: checker approval for commercial versions, renewal decisions, and budgets.
- `view finance`: spend dashboard, ledger, reconciliation, and budgets.
- `manage finance`: immutable entries, statement imports, reconciliation, and budget proposals.
- `export finance reports`: audited CSV exports.

Maker and checker must be different users even when one user has both permissions.

## Purchase to active subscription

1. Employee submits a SaaS service request with vendor, product, category, plan, seats, cycle, budget, and justification.
2. The normal request approval chain completes.
3. Operator opens the approved request and selects **Register approved subscription**.
4. Operator completes ownership, masked-account source, dates, payment instrument, FX basis, and evidence.
5. The registry remains `draft` and commercial version v1 remains `pending_review`.
6. A different checker approves v1. The subscription becomes `active` and an immutable `projected` ledger entry is created.

## Renewal control

`ProcessSubscriptionRenewals` runs every day at 08:00 Asia/Jakarta through the normal scheduler/Horizon setup.

- Default checkpoints: D-30, D-14, D-7, D-3, D-1, renewal day, overdue.
- On restart, only the latest applicable missed checkpoint is sent.
- A database unique key prevents duplicate delivery.
- Auto-renew without an approved decision by the cancellation deadline becomes `renewal_review` and raises one escalation.
- An instrument expiring before renewal changes the subscription to `payment_failed` and alerts accountable users.
- A renewal never claims the vendor charge succeeded. The checker only approves the business decision.
- Approved renewals create a post-renewal evidence work item. Uploading an invoice or receipt acknowledges it.

Check scheduler registration with:

```text
php artisan schedule:list
```

Production must run Laravel's scheduler and Horizon continuously.

## Ledger rules

- Amounts use decimal-safe arithmetic and persist original amount/currency, IDR normalization, FX rate, source, effective time, and method.
- `projected`, `accrued`, `invoiced`, `paid`, `refunded`, and `void` are never combined into an unlabeled total.
- Ledger rows cannot be edited or deleted. Create a correction linked through `correction_of_id`.
- Credits and refunds are negative. Normal charges are positive.
- AI entries are idempotently imported from LiteLLM spend logs using a source fingerprint.
- Finance exports are logged through Activitylog.

## Statement CSV

Accepted headers:

```text
date,description,amount,currency,reference
```

`reference` is optional. Files are stored privately and deduplicated using SHA-256 per payment instrument. Exact amount/currency matches within three days are linked automatically. Finance reviews all remaining lines as partially matched, duplicate, missing invoice, unexpected, amount variance, currency variance, refunded, or disputed.

## Incident checks

- **No reminder:** verify `schedule:list`, Horizon, queue health, and the subscription renewal/cancellation dates.
- **Duplicate statement rejected:** expected behavior; compare the file SHA-256 and use the existing import.
- **Unmatched charge:** verify instrument, amount, currency, and transaction date, then add a reviewed reconciliation note.
- **Wrong financial amount:** create a correction entry; never modify the original.
- **Missing invoice after renewal:** open Renewals and upload invoice/receipt evidence.
- **LiteLLM import unavailable:** verify the gateway connection; retrying is safe because source keys are idempotent.

## Cutover and rollback

Before production cutover, export the existing subscription/card register, map owners/departments/cost centers, import masked instruments only, and reconcile opening balances. Never import PAN, CVV, passwords, API keys, or recovery codes.

Rollback the migration only before production financial entries exist. After live use, preserve the ledger and use a forward migration because financial history is intentionally immutable.
