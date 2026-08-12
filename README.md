# VirtueNet Platform

Internal service-request and delivery platform for `platform.virtuenet.space`. The dashboard is the operational source of truth for requests, approvals, delivery, AI usage, integration health, and GitHub–Lark task synchronization.

## Runtime

- PHP 8.3 and Laravel 13
- MySQL 8
- Redis cache and queue
- Laravel Horizon for queue supervision
- Laravel Pulse for application monitoring
- Spatie Permission for role/capability authorization
- Spatie Activitylog for audit history
- Laravel Fortify for authentication and two-factor authentication
- Bootstrap 5 / Silva theme, Vite, and SCSS

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

Lark supports:

- SSO through the configured OAuth app
- tenant access-token caching
- text messages through the IM API
- task creation through Task v2
- an Approval v4 connector method for flows that intentionally use a Lark approval definition

The current service-request approval chain is local and transactional; it does not create a Lark approval instance automatically.

In-app business notifications do not send external Lark messages automatically. This prevents approval or delivery alerts from unexpectedly leaving the platform.

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

The role editor groups these by user-facing application area. Route paths and HTTP methods are not presented as business permissions.

## Local installation

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

Email login submits AJAX and always returns:

```json
{
  "success": true,
  "message": "Authenticated successfully.",
  "redirect": "/v1/dashboard"
}
```

Failed authentication uses the same contract with `success: false`.

## Redis, Horizon, scheduler, and Pulse

For Windows/Laragon development, run Redis on the project-specific forwarded port and run Linux-only workers in Docker:

```powershell
docker compose up -d redis
docker compose -f docker-compose.host-workers.yml up -d --build
php artisan horizon:status
```

The host application uses:

```dotenv
QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6380
FORWARD_REDIS_PORT=6380
```

`docker-compose.host-workers.yml` connects Horizon, the scheduler, and `pulse:check` to the host MySQL and forwarded Redis. It exists because native Windows PHP does not provide the `pcntl` functions required by Horizon.

Stop only the worker runtime with:

```powershell
docker compose -f docker-compose.host-workers.yml down
```

## Production deployment

Set real application, database, Redis, Lark, and encryption values in `.env`; never commit them. Then deploy the production stack:

```powershell
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan about --only=drivers
docker compose -f docker-compose.prod.yml exec app php artisan horizon:status
```

The production stack contains:

- `app` — PHP-FPM; the only service allowed to run migrations/setup
- `web` — Nginx
- `db` — MySQL with a persistent volume
- `redis` — private network only, with a persistent volume
- `horizon` — Redis queue supervisor
- `scheduler` — `schedule:work`
- `pulse-check` — server metric collector

Before traffic is switched:

1. Confirm `APP_ENV=production`, `APP_DEBUG=false`, a stable `APP_KEY`, HTTPS `APP_URL`, and production database credentials.
2. Confirm queue and cache drivers are Redis.
3. Run migrations and verify the seven platform capabilities exist on Developer/Admin roles.
4. Test Lark, GitHub, and LiteLLM from **Gateway connections**.
5. Confirm `/horizon` and `/pulse` are accessible only to authorized administrators.
6. Submit one non-production request and verify submit → approval → delivery → notification.
7. Confirm Horizon has no failed jobs and GitHub webhook deliveries are signed.

## Verification

```powershell
php artisan test
npm.cmd run build
php vendor\bin\pint --test
php artisan route:list
```

The tests cover native AJAX login, impersonation return, Spatie route authorization and UI language, request approval/revision rounds, queued AI provisioning, direct LiteLLM rendering, and signed/idempotent GitHub webhooks.
