# VirtueNet Platform

Internal service-request and delivery platform for `platform.virtuenet.space`. The dashboard is the operational source of truth for requests, approvals, delivery, AI usage, integration health, and GitHub–Lark task synchronization.

## Runtime

- PHP 8.3 and Laravel 13
- PostgreSQL 16
- Redis cache and queue
- Laravel Octane on FrankenPHP for HTTP
- Laravel Horizon for queue supervision
- Laravel Pulse for application monitoring
- Spatie Permission for role/capability authorization
- Spatie Activitylog for audit history
- Laravel Fortify for authentication and two-factor authentication
- Bootstrap 5 / Silva theme, Vite, and SCSS

Production does not require a dedicated Nginx container. FrankenPHP serves Laravel directly on port `8000`; Coolify terminates HTTPS and proxies the configured domain to the `app` service on that port.

No custom queue monitor, route-name RBAC, server monitor, websocket, Pusher, backup engine, database-clear screen, or external notification blast transport is used. In-app notifications remain local. External products are connected through encrypted gateway credentials instead of having their features cloned into this application.

## Service catalog and workflow

All service types use one transaction-safe workflow, timeline, notification path, and delivery record.

| Service | Required business data | Approval stages | Delivery outcome |
|---|---|---|---|
| AI Token | purpose, gateway model IDs, budget period, budget, optional RPM/TPM | Technical & budget review | LiteLLM virtual key, budget, model access, and live usage |
| Custom System | problem, target users, capabilities, optional AI Analyzer | Scope review → Budget approval | system reference, access URL, dates, and optional AI key |
| System Integration | source, target, data/workflow scope, credential readiness | Technical review | integration reference, access URL, and delivery state |
| SaaS Subscription | product, plan, seats, billing cycle, business reason | Business review → Procurement approval | subscription reference, access URL, and validity dates |

### Request states

```text
Submitted
  → Under Review (when another approval stage remains)
  → Revision Requested → resubmitted as a new approval round
  → Rejected
  → Approved → In Progress ↔ Waiting External → Completed
  → Cancelled by requester before a terminal outcome
```

Each decision is protected by a database transaction and row lock. Approval actions only affect the active stage. A revision preserves previous rounds and creates a complete new approval chain. Timeline entries and in-app notifications are written in the same transaction.

AI provisioning is queued only after final approval commits. A gateway outage does not roll back the business approval: Horizon retries the job and operators can retry it from the request screen.

## External connections

Credentials are encrypted using Laravel's encrypted casts and are never rendered back in configuration forms.

### LiteLLM

Configure an endpoint and master key under **Administration → Gateway connections**.

VirtueNet calls the gateway directly for:

- `POST /key/generate` — approved virtual-key provisioning
- `GET /user/info` — user/key context
- `GET /user/daily/activity` — spend, request, and token totals
- `GET /spend/logs` — live gateway logs
- `GET /health/readiness` — connection verification

The token itself is encrypted locally and visible only to its owner. LiteLLM remains responsible for models, limits, budgets, spend calculation, and logs. Replacing the gateway only requires a new adapter at the connection boundary, not a rewrite of the request domain.

### GitHub and Lark

The GitHub connection stores one `owner/repository`, optional access token, and webhook secret. The webhook endpoint is:

```text
POST /webhooks/github
```

Requests must include a valid `X-Hub-Signature-256`. `X-GitHub-Delivery` is stored as an idempotency key, so duplicate webhook deliveries do not queue duplicate syncs. The scheduled sync runs every five minutes as a second recovery path.

Repository issues are synchronized locally; pull requests returned by GitHub's issues endpoint are excluded. New issues are converted into Lark tasks. Sync errors remain visible per task for operator recovery.

Lark supports SSO, tenant access-token caching, IM text messages, Task v2 creation, and an Approval v4 connector for flows that intentionally use a Lark approval definition. The current service-request approval chain remains local and transactional.

## Roles and capabilities

Authorization uses Spatie capabilities, not route names. The Developer role is protected and has the global emergency bypass.

Platform capabilities:

- `view service requests`
- `review service requests`
- `manage service requests`
- `view ai usage`
- `manage integrations`
- `view delivery tasks`
- `sync delivery tasks`

## Local installation without containers

```powershell
composer install
npm.cmd install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
npm.cmd run build
```

Default development accounts created by `RoleAndUserSeeder`:

| Role | Email | Password |
|---|---|---|
| Developer | `developer@example.com` | `password` |
| Admin | `admin@example.com` | `password` |
| User | `user@example.com` | `password` |

## Docker Desktop rehearsal

Docker Desktop intentionally uses the same immutable production image and process topology as dev/prod: FrankenPHP/Octane, PostgreSQL, Redis, Horizon, scheduler, and Pulse. It does not use Nginx or PHP-FPM.

Prepare the local-only environment and start the stack:

```powershell
Copy-Item .env.docker.example .env
docker compose config
docker compose up -d --build
docker compose ps
```

The application is available at `http://localhost:8080`. Validate the deployment contract:

```powershell
curl.exe --fail http://localhost:8080/health/live
curl.exe --fail http://localhost:8080/health/readiness
docker compose exec app php artisan horizon:status
docker compose exec app php artisan about --only=drivers
```

Expected running services are `app`, `db`, `redis`, `horizon`, `scheduler`, and `pulse-check`. Only the `app` service runs migrations at startup. PostgreSQL and Redis use persistent named volumes.

The same rehearsal is enforced by GitHub Actions after the Laravel test suite: Compose configuration is validated, the production image is built, the stack boots, health/readiness are queried, Horizon is checked, and every long-running service must still be running.

Stop and remove the local rehearsal stack with:

```powershell
docker compose down -v --remove-orphans
```

## Dev / production deployment on Coolify

Use `docker-compose.prod.yml`. Secrets are runtime environment variables managed by Coolify and are not committed. At minimum configure a stable `APP_KEY`, HTTPS `APP_URL`, and strong PostgreSQL credentials. Keep `APP_DEBUG=false` outside local development.

The production topology is:

```text
Internet
  → Coolify proxy / HTTPS
    → app:8000 (Laravel 13 + Octane + FrankenPHP)
      → PostgreSQL
      → Redis

Background services using the same Laravel image:
  horizon
  scheduler
  pulse-check
```

There is deliberately no application-owned Nginx proxy in this path. In Coolify, assign the application domain to service `app` port `8000`.

Before switching traffic:

1. Confirm the Docker rehearsal CI job is green for the exact commit being deployed.
2. Confirm `APP_ENV`, `APP_DEBUG`, `APP_KEY`, `APP_URL`, database, Redis, Lark, GitHub, and LiteLLM runtime values are correct for the target environment.
3. Confirm `/health/live` and `/health/readiness` return success through the deployed HTTPS domain.
4. Confirm `php artisan horizon:status`, the scheduler container, and the `pulse-check` container are healthy/running.
5. Confirm `/horizon` and `/pulse` remain limited to authorized administrators.
6. Run `php artisan platform:cutover-check` and archive the output.
7. Run `php artisan platform:ai-gateway-smoke --model=<approved-model> --json` where LiteLLM is configured; require `passed=true` and never expose the generated key.
8. Submit one non-production request and verify submit → approval → delivery → notification.
9. Confirm Horizon has no failed jobs and signed GitHub/Lark integrations behave as expected.
10. Benchmark Octane/FrankenPHP against the agreed PHP-FPM baseline before closing platform migration issue #42.

## Verification

```powershell
php artisan test
npm.cmd run build
php vendor\bin\pint --test
php artisan route:list
```

The tests cover native AJAX login, impersonation return, Spatie route authorization and UI language, request approval/revision rounds, queued AI provisioning, direct LiteLLM rendering, and signed/idempotent GitHub webhooks.
