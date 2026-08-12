# Shadow Deployment and Cutover Runbook

## Safety boundary

Deploy `codex/virtuenet-alignment` as a separate Coolify application and database first. Do not point `platform.virtuenet.space` at it until parity is signed off. The legacy database connection must be read-only, and shadow Lark outbound approval creation stays disabled.

## Shadow configuration

Required production values:

- `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, and an HTTPS shadow `APP_URL`.
- Dedicated PostgreSQL database and Redis. Keep `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, and `SESSION_DRIVER=database`.
- `LEGACY_DB_URL` uses a read-only PostgreSQL account restricted to `SELECT` on the legacy tables.
- Configure Lark credentials, but keep `LARK_APPROVAL_OUTBOUND_ENABLED=false` during shadow validation.
- Configure LiteLLM only through the encrypted connection screen; never place its master key in source control.

`docker-compose.prod.yml` already uses `init: true`, health-gated PostgreSQL, Octane/FrankenPHP, Horizon, scheduler, Pulse, and separate persistent volumes. Coolify should probe `/health/live`; release approval uses `/health/readiness`.

## Rehearsal

1. Create a target PostgreSQL backup and record its restore command.
2. Deploy the shadow branch and run `php artisan migrate --force`.
3. Run `php artisan platform:import-legacy` without `--commit`; archive the row-count output.
4. Resolve every unmapped requester, department, UUID conflict, and unsupported schema before continuing.
5. Run `php artisan platform:import-legacy --commit` twice. The second run must not increase record counts.
6. Run `php artisan platform:cutover-check`; every check must pass.
7. Validate `/health/live`, `/health/readiness`, `/horizon`, and `/pulse` using an authorized operator account.
8. Run `php artisan platform:ai-gateway-smoke --json` from the deployed application container and archive the JSON output with the release evidence. A passing result must report `configured`, `health`, `key_generated`, and `key_revoked` as `true`. The generated canary secret is never printed and is deleted before the command succeeds.

Imported legacy AI keys remain encrypted and are marked already delivered. They are never re-revealed after migration; users request rotation when a replacement is required.

## AI Gateway acceptance evidence (#46)

The AI Token regression gate has two layers:

1. CI must pass `AiGatewaySmokeCheckTest`, `AiCredentialLifecycleTest`, `LiteLlmGatewayTest`, and `PlatformWorkflowTest`, followed by the complete Laravel test suite.
2. The deployed shadow/production application must pass `php artisan platform:ai-gateway-smoke --json` using the encrypted LiteLLM connection stored by the platform.

The live smoke command creates a short-lived canary key with a minimal daily budget, verifies LiteLLM key generation, and always attempts deletion in a `finally` path. Treat a cleanup failure as a failed release gate and remove the canary manually before continuing. Never paste the LiteLLM master key or generated virtual key into tickets, CI logs, chat, or release notes.

## Parity evidence

Record old-versus-shadow counts for departments, users, requests by type/status, approval instances/stages, and AI credentials by status. Then smoke-test:

- requester submission, revision, cancellation, timeline, attachment, and in-app notification;
- Lark webhook signature, challenge, idempotent event replay, approval mapping, and stale-state indicator;
- object-level authorization using two users in different departments;
- AI provisioning, one-time delivery, usage failure state, rotate, and revoke;
- subscription maker-checker, renewal reminder idempotency, evidence, statement reconciliation, and CSV export;
- GitHub–Lark task mapping, Coolify inventory, health probes, and deep-linked dashboard exception totals.

## Cutover

1. Announce a short write freeze on the legacy platform.
2. Take the final legacy and target database backups.
3. Run the dry run, final committed import, idempotency rerun, full automated tests, `platform:cutover-check`, and `platform:ai-gateway-smoke --json`.
4. Switch the domain only after owner, IT/AI admin, finance, and requester acceptance is recorded.
5. Watch readiness, Horizon failures, Pulse, Lark sync drift, LiteLLM staleness, and reconciliation exceptions continuously during the observation window.

## Rollback

Switch routing back to the legacy application, restore its write access, and leave the shadow database intact for investigation. Do not reverse-import shadow writes automatically. Reconcile any records created during the cutover window explicitly by request code, Lark instance, and immutable ledger reference before attempting another cutover.
