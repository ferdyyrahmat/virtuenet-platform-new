# 🎨 Silva Kit

> A modern **Laravel 12** admin panel starter kit built with the [Silva Admin Template](https://zoyothemes.com/silva/html/) by Zoyothemes. Equipped with essential system modules, dynamic multi-role dashboards, user impersonation, ticketing, queue management, database tools, beautiful UI, and developer-friendly architecture — ready for rapid production development.

---

## 🚀 Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | Laravel 12, PHP 8.2+ |
| **Frontend** | Bootstrap 5.3, SCSS, Vite |
| **Database** | MySQL 8.0 / SQLite |
| **Object Storage** | MinIO (S3 Compatible Storage) |
| **DataTables** | Yajra DataTables (Server-side) |
| **UI Template** | Silva Admin by Zoyothemes |

---

## 📦 Installation

### Prerequisites

- PHP >= 8.2
- Composer
- Node.js >= 18 & npm
- MySQL 8.0

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
- 🗄️ **MySQL Database**: `localhost:3306` (`DB_DATABASE=silva_kit`)
- 🪣 **MinIO Console (Object Storage Dashboard)**: [http://localhost:9001](http://localhost:9001) (`user: minioadmin / pass: minioadmin`)
- 📦 **MinIO S3 API Endpoint**: `http://localhost:9000` (Default bucket: `silva-bucket`)

#### 2. Production Deployment Mode

```bash
# Start Production Containers (OPcache Optimized, Assets Prebuilt)
docker compose -f docker-compose.prod.yml up -d --build
```

---

### Local Bare-Metal Setup

```bash
# 1. Clone the repository
git clone https://github.com/ferdyyrahmat/silva-kit-new.git
cd silva-kit-new

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

---

### 🔑 Default Credentials

When running `php artisan db:seed` (or `RoleAndUserSeeder`), the system creates three default accounts:

| Role | Email | Password | Access Level |
|------|-------|----------|--------------|
| **Developer** | `developer@example.com` | `password` | Full system access, developer tools, database manager, queue monitor |
| **Admin** | `admin@example.com` | `password` | System administration, user management, tickets, audit logs, settings |
| **User** | `user@example.com` | `password` | Profile management, personal API tokens, support tickets |

---

## 🌟 Global Features

### 🔐 Authentication & OAuth 2FA System
- Standard Login, Register, Password Reset, and Logout.
- **Lock Screen**: Session-based lock screen requiring user password/PIN unlock.
- **Two-Factor Authentication (2FA)**: TOTP Authenticator apps (Google Authenticator, Authy) with SVG QR code setup and emergency recovery codes.
- **Socialite OAuth Login**: Seamless login with Google and GitHub accounts out-of-the-box.

### 🎭 User Impersonation System
- Administrators can impersonate any registered user to inspect the system from their perspective.
- **Sticky Impersonation Banner**: Top warning banner indicating active impersonation session with a quick "Stop Impersonating" button.

### 🔒 Role & Permission System (with Locked Roles)
- Dynamic route-based access control with custom `check_permission` middleware.
- **Role Locking Protection**: System roles (e.g. Developer) can be locked to prevent accidental deletion or unauthorized attribute modification.
- Complete CRUD interface for roles and permissions with group mapping.

### 📊 Multi-Role Responsive Dashboards
- Intelligent dashboard routing based on user role:
  - **Developer Dashboard**: High-level system statistics, server environment info, database status, and developer quick links.
  - **Admin Dashboard**: System metrics, user counts, ticket summaries, audit trail highlights, and health monitor cards.
  - **User Dashboard**: Personal profile overview, active support tickets status, and recent notifications.

### 🎟️ Support Ticket System & Developer Portal
- **User Support Ticketing**: Users can create, view, reply to, and track support tickets.
- **Admin & Developer Management**: Admins and developers can assign tickets to technical staff, update status (Open, In Progress, Resolved, Closed), and reply to user inquiries.

### ⚡ Queue Manager & Job Monitor
- Monitor pending and failed queue jobs via `/admin/queues`.
- View exception details, retry individual failed jobs, or purge queue lists safely.

### 📁 Directory & File Manager
- Web-based Cloud / Local Storage File Manager accessible at `/admin/directory`.
- Supports file uploads, folder creation, file deletion, zip archive downloading, and storage quota settings.

### 🛠️ Database Management Tools
- Dedicated developer database control view (`/admin/database`).
- View table statistics, row counts, and execute database clearing or role/user re-seeding commands directly from the panel.

### ⚙️ System Settings (Branding & WebSockets)
- **Branding Settings**: Customize Application Name, Brand Logos, Favicon, and Footer copyright text.
- **WebSocket Settings**: Configure Pusher / Laravel Reverb credentials with an interactive real-time connectivity tester.

### 👤 User Profile & API Token Manager
- Edit user details (Name, Email, Phone, Location, Avatar).
- Auto center-crop avatar upload (1:1 circular ratio).
- Change password with current password validation.
- Manage **Sanctum Personal Access Tokens** (Generate, view, and revoke API tokens).

### 👥 User Management (Admin)
- Server-side Yajra DataTables listing with search, sorting, and pagination.
- Full user CRUD operations with role and permission assignments.

### 🌙 Dark Mode & 🌐 Multi-Language (i18n)
- **Dark Mode**: Seamless toggle between Light and Dark themes with session persistence.
- **Multi-Language**: Click-to-toggle between **Bahasa Indonesia (ID)** and **English (EN)** in topbar. Translation files organized under `lang/id/` and `lang/en/`.

### 🔍 Global Search
- Real-time AJAX search bar in topbar matching routes, navigation menus, and system pages.

### 🔔 Notification Bell & Inbox System
- Real-time notification updates with unread count badge & AJAX polling.
- **Notification Blast**: Broadcast system notifications to all users or specific roles (`/admin/notifications`).
- Profile Inbox tab (`/v1/profile#tab-notifications`) and global helper function `send_notification($title, $message, $url)`.

### 📜 Audit Trail Logging System
- Automated logging for login/logout, user creation/updates, role modifications, impersonation events, and system settings changes.
- View logs via `/admin/audit-logs` DataTables view or trigger via global helper `audit_log($description)`.

### 🛠️ Maintenance Mode
- Toggle maintenance mode from settings with custom title and message.
- Automatic bypass for users with the **Admin** role.
- Dynamic persistence using `system_settings` table.

### 📊 System Health Monitor Widget
- Live server metrics card container on Admin Dashboard: Database latency, MinIO Object Storage status, RAM memory usage, and Disk space capacity.

### 💾 Automated Backup Engine
- Database & asset backup management with MinIO S3 object storage synchronization via `spatie/laravel-backup`.
- Manual backup trigger & zip archive download from Admin Panel (`/admin/backups`).

### 🔑 API Engine & Swagger OpenAPI Documentation
- Laravel Sanctum token-authenticated REST API endpoint structure.
- Interactive OpenAPI / Swagger REST API documentation generated at `/api/documentation`.

---

## 📁 Project Structure

```
silva-kit-new/
├── app/
│   ├── Helpers/
│   │   └── helpers.php           # Global helper functions (send_notification, audit_log)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/              # REST API & Swagger Controllers
│   │   │   ├── Auth/             # Login, Register, Lockscreen, 2FA, Socialite, Impersonation
│   │   │   ├── Dashboard/        # Role-based Dashboard controller
│   │   │   ├── System/           # Admin system modules
│   │   │   │   ├── AuditLog/     # Audit Trail controller
│   │   │   │   ├── Backup/       # Backup Manager controller
│   │   │   │   ├── Database/     # Database Management controller
│   │   │   │   ├── Directory/    # File & Directory Manager controller
│   │   │   │   ├── Feedback/     # Feedback controller
│   │   │   │   ├── Language/     # Language & Theme toggle
│   │   │   │   ├── Maintenance/  # Maintenance Mode controller
│   │   │   │   ├── Notification/ # Bell & Blast notification controllers
│   │   │   │   ├── Permission/   # Role & Permission controller (with locking)
│   │   │   │   ├── Profile/      # Profile & Sanctum token controller
│   │   │   │   ├── Queue/        # Queue Manager controller
│   │   │   │   ├── Search/       # Global search controller
│   │   │   │   ├── Setting/      # Branding & WebSocket settings controllers
│   │   │   │   ├── Ticket/       # Admin & Developer ticket controllers
│   │   │   │   └── User/         # Admin User CRUD controller
│   │   │   └── User/             # User ticket controller
│   │   └── Middleware/
│   │       ├── CheckLockscreen.php
│   │       ├── CheckMaintenanceMode.php
│   │       ├── CheckPermission.php
│   │       └── SetLocale.php
│   ├── Models/
│   │   ├── AuditLog.php
│   │   ├── Developer.php
│   │   ├── Feedback.php
│   │   ├── NotificationBlast.php
│   │   ├── Permission.php
│   │   ├── Role.php
│   │   ├── SystemNotification.php
│   │   ├── SystemSetting.php
│   │   ├── Ticket.php
│   │   ├── TicketReply.php
│   │   └── User.php
│   └── Services/
│       ├── SystemHealthService.php
│       └── TwoFactorService.php
├── database/
│   ├── migrations/
│   └── seeders/                  # DatabaseSeeder, RoleAndUserSeeder, UserSeeder, AdminSeeder
├── docker/
│   ├── nginx/                    # Development & Production Nginx configurations
│   ├── php/                      # Production OPcache configuration
│   ├── Dockerfile.dev
│   ├── Dockerfile.prod
│   ├── entrypoint.sh
│   └── entrypoint.prod.sh
├── lang/
│   ├── en/                       # English translations
│   └── id/                       # Indonesian translations
├── resources/
│   ├── scss/                     # Custom SCSS styles
│   └── views/
│       ├── admin/                # Admin module views (users, roles, tickets, queues, backups, directory, database)
│       ├── auth/                 # Auth & 2FA challenge views
│       ├── dashboard/            # Admin, Developer, and User dashboard views
│       ├── errors/               # Custom error pages (503 maintenance, 404, etc.)
│       └── layouts/              # Layouts & partials (sidebar, topbar, impersonation banner)
├── routes/
│   ├── web.php                   # Main routes & entry points
│   ├── api.php                   # Sanctum API routes
│   ├── auth.php                  # Auth, 2FA, & Socialite OAuth routes
│   └── partials/
│       ├── admin.php             # Admin & Developer protected routes
│       └── user.php              # Authenticated user routes
├── docker-compose.yml            # Development Docker setup (MySQL 8.0, MinIO, App, Nginx)
├── docker-compose.prod.yml       # Production Docker setup
└── .env.docker.example           # Docker environment template
```

---

## 🔄 Last Update

**Date:** 26 July 2026

### ✨ What's New
- ✅ **Database Management Tools** — Database table inspector, table cleaner/reset tools, and developer seeding triggers (`/admin/database`).
- ✅ **User Impersonation** — Admin feature to switch views into any user context with top notification warning banner and quick exit control.
- ✅ **Role Locking Protection** — Security enhancement to lock critical system roles (e.g. Developer, Admin) against unauthorized deletion.
- ✅ **Multi-Role Dashboards** — Tailored dashboards for Admin, Developer, and User roles featuring system diagnostics, environment stats, and quick links.
- ✅ **Support Ticket System** — Complete ticketing portal for users to submit issues and for admins/developers to reply and manage resolution workflow.
- ✅ **Queue Manager & Worker Monitor** — Failed jobs viewer, manual retry trigger, and queue purge utilities (`/admin/queues`).
- ✅ **Directory & File Storage Manager** — File manager tool supporting uploads, folder management, downloads, and storage usage stats (`/admin/directory`).
- ✅ **System Branding & WebSocket Settings** — Panel tools for dynamic app title/logo/favicon configuration and real-time WebSocket connection testing (`/admin/settings`).
- ✅ **Notification Blaster** — Send targeted broadcast notification messages to all users or specific role groups.
- ✅ **Docker Ready (Dev & Prod)** — Complete containerized stack using PHP 8.2-FPM, Nginx, MySQL 8.0, and MinIO S3 Object Storage.
- ✅ **Two-Factor Authentication (2FA) & Socialite** — TOTP authenticator setup with QR codes, recovery codes, and Google/GitHub OAuth integration.
- ✅ **Sanctum API Engine & Swagger Docs** — Personal Access Token manager with auto-generated OpenAPI REST docs at `/api/documentation`.

---

## 👨‍💻 Author

**Ferdy Rahmat**

- GitHub: [@ferdyyrahmat](https://github.com/ferdyyrahmat)

---

## 📄 License

This project is built on top of the [Laravel Framework](https://laravel.com/) which is open-sourced software licensed under the [MIT License](https://opensource.org/licenses/MIT).

The Silva Admin Template is a product of [Zoyothemes](https://zoyothemes.com/).
