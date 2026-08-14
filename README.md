# 🎨 Silva Kit — VirtueNet Platform

> A modern **Laravel 13** admin panel built on the [Silva Admin Template](https://zoyothemes.com/silva/html/) by Zoyothemes. This is the customized **`virtuenet`** branch: Spatie RBAC (route-based permissions), Lark SSO, support ticketing, audit trail, notification blast, and more.

---

## 🚀 Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 13, PHP 8.3+ |
| **Frontend** | Bootstrap 5.3, SCSS, Vite |
| **Database** | SQLite (default) / MySQL 8.0 (Docker) |
| **Object Storage** | MinIO (S3 Compatible Storage, Docker dev) |
| **DataTables** | Yajra DataTables (Server-side) |
| **Auth / RBAC** | Laravel Fortify + Spatie Permission |
| **Jobs / Monitor** | Laravel Horizon, Laravel Pulse |
| **Audit** | spatie/laravel-activitylog |
| **UI Template** | Silva Admin by Zoyothemes |

---

## 📦 Installation

### Prerequisites

- PHP >= 8.3
- Composer
- Node.js >= 18 & npm
- SQLite (default) or MySQL 8.0

---

### 🐳 Docker Quickstart (Recommended)

#### 1. Development Mode (with Live Reload, MySQL & MinIO)

```bash
# Copy Docker environment template
cp .env.docker.example .env

# Start containers (App, Nginx, MySQL, MinIO, Auto-Bucket Init)
docker compose up -d --build

# Run database migrations & seeders inside container
docker compose exec app php artisan migrate --seed
```

- 🌐 **Web Application**: [http://localhost:8080](http://localhost:8080)
- 🗄️ **MySQL Database**: `localhost:3306`
- 🪣 **MinIO Console (Object Storage Dashboard)**: [http://localhost:9001](http://localhost:9001) (`user: minioadmin / pass: minioadmin`)
- 📦 **MinIO S3 API Endpoint**: `http://localhost:9000`

#### 2. Production Deployment Mode

```bash
# Start Production Containers (OPcache Optimized, Assets Prebuilt)
docker compose -f docker-compose.prod.yml up -d --build
```

---

### Local Bare-Metal Setup

```bash
# 1. Clone the repository
git clone https://github.com/ferdyyrahmat/virtuenet-platform-new.git
cd virtuenet-platform-new

# 2. Install PHP dependencies
composer install

# 3. Install Node.js dependencies
npm install

# 4. Copy environment file
cp .env.example .env

# 5. Generate application key
php artisan key:generate

# 6. Run database migrations & seed default data
php artisan migrate --seed

# 7. Build frontend assets & start server
npm run build
php artisan serve
```

> Default setup uses SQLite (`database/database.sqlite`). For MySQL, set the credentials in `.env` before migrating.

---

### 🔑 Default Credentials

When running `php artisan db:seed` (`RoleAndUserSeeder`), the system creates three default accounts:

| Role | Email | Password | Access Level |
|------|-------|----------|--------------|
| **Developer** | `developer@example.com` | `password` | Full access (all permissions), Horizon/Pulse, infrastructure |
| **Admin** | `admin@example.com` | `password` | System administration, user & permission management, tickets, audit logs, notifications |
| **User** | `user@example.com` | `password` | Profile management, personal API tokens, support tickets |

---

## 🌟 Global Features

### 🔐 Authentication
- Login & logout via Laravel Fortify.
- Registration with automatic email verification (`email_verified_at` set on create — mirrors Lark SSO behavior; no verification e-mail is sent).
- Password reset & confirm-password flows (Fortify).
- **Lock Screen**: session-based lock requiring the user's password to unlock (`/lockscreen`).
- **Lark SSO**: single sign-on via Lark accounts with a domain allowlist (`LARK_ALLOWED_DOMAINS`), configured in `config/services.php`.

### 🎭 User Impersonation
- Admins can impersonate any registered user to inspect the system from their perspective (`/impersonation/start/{user}`), with a sticky warning banner and quick exit control.

### 🔒 Role & Permission System (Spatie, route-based)
- Roles and permissions managed with `spatie/laravel-permission`.
- **Permission names == admin route names** (`admin.users.index`, `admin.tickets.reply`, …). Routes use the `permission:` middleware alias.
- **Role locking**: critical system roles (e.g. Developer, Admin) can be locked against deletion or attribute changes.
- Full CRUD interface for roles & permissions at `/admin/permissions`.

### 📊 Multi-Role Dashboards
- **Developer Dashboard**: ticket stats for tickets assigned to the developer, recent assigned tickets.
- **Admin Dashboard**: user & ticket counts, recent audit log entries, recent tickets.
- **User Dashboard**: own ticket stats, recent tickets, unread notification count.
- `/v1/dashboard` redirects based on role (`isDeveloper`, `isAdmin`, otherwise user).

### 🎟️ Support Ticket System
- **User portal** (`/v1/tickets`): create, view by ticket code, and reply.
- **Admin panel** (`/admin/tickets`): DataTables listing, show, reply, assign to a developer, destroy.
- **Developer management** (`/admin/tickets/developers`): assignable developer records.
- Statuses: `open`, `in_progress`, `waiting_user`, `resolved`, `closed`.

### 🔔 Notification Bell & Blast
- Notification bell with unread count, mark-as-read, delete, and clear-all (AJAX, routes under `/notifications-bell`).
- **Notification Blast**: broadcast to all users or a role group (`/admin/notifications`).
- Global helper `send_notification($title, $message, $url)` (`app/Helpers/helpers.php`).

### 📜 Audit Trail (spatie/laravel-activitylog)
- Automated logging of logins (password & Lark), user/role changes, impersonation events, and more.
- View at `/admin/audit-logs` (DataTables).
- Global helper `audit_log($description)`.

### 📁 Directory & File Manager
- Web-based file manager at `/admin/directory` supporting upload, folder creation, download, and delete via the configured filesystem (S3/MinIO or local).

### 💬 Feedback
- Feedback submissions reviewed at `/admin/feedbacks` with status updates and deletion.

### 👥 User Management (Admin)
- Server-side Yajra DataTables listing at `/admin/users` with full CRUD and role/permission assignment.

### 👤 User Profile & API Tokens
- Edit user details (name, email, phone, location, avatar at `/v1/profile`).
- Change password with current password validation.
- Manage **Sanctum Personal Access Tokens** (create & revoke).

### 🚦 Queue & Server Monitoring
- **Horizon** dashboard at `/horizon` (admin only, gate `viewHorizon`).
- **Pulse** dashboard at `/pulse` (admin only, gate `viewPulse`).

### 🔍 Global Search, Dark Mode & i18n
- Global AJAX search in the topbar (`/global-search`).
- Dark/light theme toggle with session persistence (`/theme/toggle`).
- Multi-language switching between **Bahasa Indonesia (ID)** and **English (EN)**; files under `lang/id/` and `lang/en/`.

### 🔑 API & Swagger
- Sanctum-authenticated API endpoint `/api/user`.
- Swagger/OpenAPI docs at `/api/documentation` (`darkaonline/l5-swagger`).

---

## 📁 Project Structure

```
virtuenet-platform-new/
├── app/
│   ├── Actions/Fortify/           # Custom Fortify actions (CreateNewUser, UpdateUser*…)
│   ├── Helpers/
│   │   └── helpers.php            # Global helpers (send_notification, audit_log)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/               # SwaggerDocsController (api.user)
│   │   │   ├── Auth/              # Fortify, Lockscreen, Lark SSO, Impersonation
│   │   │   ├── Dashboard/         # Role-based DashboardController
│   │   │   ├── System/            # Admin system modules
│   │   │   │   ├── AuditLog/      # Audit trail controller
│   │   │   │   ├── Directory/     # File & Directory manager
│   │   │   │   ├── Feedback/      # Feedback controller
│   │   │   │   ├── Language/      # Language & theme toggle
│   │   │   │   ├── Notification/  # Bell & Blast controllers
│   │   │   │   ├── Permission/    # Role & Permission controller (with locking)
│   │   │   │   ├── Profile/       # Profile & Sanctum token controllers
│   │   │   │   ├── Search/        # Global search controller
│   │   │   │   ├── Ticket/        # Admin & developer ticket controllers
│   │   │   │   └── User/          # Admin user CRUD controller
│   │   │   └── User/              # User ticket controller
│   │   │   └── RoutingController   # Root route
│   │   └── Middleware/
│   │       ├── CheckLockscreen.php
│   │       └── SetLocale.php
│   ├── Models/
│   │   ├── Developer.php
│   │   ├── Feedback.php
│   │   ├── NotificationBlast.php
│   │   ├── Permission.php
│   │   ├── Role.php
│   │   ├── SystemNotification.php
│   │   ├── Ticket.php
│   │   ├── TicketReply.php
│   │   └── User.php
│   ├── Providers/
│   │   ├── AppServiceProvider.php # Gates (developer bypass, viewPulse), login audit listener
│   │   ├── FortifyServiceProvider.php
│   │   ├── HorizonServiceProvider.php # Gate viewHorizon
│   │   └── RouteServiceProvider.php
│   └── Services/
│       ├── LarkService.php
│       └── TicketNotificationService.php
├── database/
│   ├── migrations/
│   └── seeders/                  # DatabaseSeeder, RoleAndUserSeeder
├── docker/
│   ├── nginx/                    # Development & Production Nginx configurations
│   ├── php/                      # Production OPcache configuration
│   ├── Dockerfile.dev
│   ├── Dockerfile.prod
│   ├── entrypoint.sh
│   └── entrypoint.prod.sh
├── lang/
│   ├── en/messages.php
│   └── id/messages.php
├── resources/
│   ├── scss/                     # Custom SCSS styles
│   └── views/
│       ├── admin/                # audit-logs, directory, feedbacks, notification, permissions, profile, tickets, users
│       ├── auth/                 # login, register, lockscreen, recoverpw, two-factor-challenge, …
│       ├── dashboard/            # Admin, Developer, and User dashboard views
│       ├── errors/               # Custom error pages (401–503, minimal)
│       └── layouts/              # vertical, auth, error + partials (sidebar, topbar, impersonation banner)
├── routes/
│   ├── web.php                   # Root & web entry points (loads auth/admin/user partials)
│   ├── api.php                   # Sanctum API route (api.user)
│   ├── auth.php                  # Register, password reset, lockscreen, Lark SSO
│   └── partials/
│       ├── admin.php             # Admin routes (permission middleware)
│       └── user.php              # Authenticated user routes (profile, tickets)
├── docker-compose.yml            # Development Docker setup (MySQL 8.0, MinIO, App, Nginx)
├── docker-compose.prod.yml       # Production Docker setup
└── .env.docker.example           # Docker environment template
```

---

## 🔄 Where is everything routed?

Full route inventory (non-vendor) is generated with `php artisan route:list`. Routes always take precedence over this README — for an authoritative setup, see [`docs/SSOT.md`](docs/SSOT.md).

---

## 👨‍💻 Author

**Ferdy Rahmat**

- GitHub: [@ferdyyrahmat](https://github.com/ferdyyrahmat)

---

## 📄 License

This project is built on top of the [Laravel Framework](https://laravel.com/) which is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).

The Silva Admin Template is a product of [Zoyothemes](https://zoyothemes.com/).