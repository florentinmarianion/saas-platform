# SaaS Platform

Multi-tenant cloud workspace platform where users can belong to multiple
companies and access role-specific business applications.

**Stack:** PHP 8.5 / Laravel 13 / MariaDB (MySQL compatible) / Sanctum API

---

## Requirements

- PHP 8.5+
- Composer
- Node.js 18+
- MySQL 8+ or MariaDB 10.6+
- WAMP / XAMPP / Laravel Herd

---

## Installation

```bash
git clone https://github.com/your-repo/saas-platform
cd saas-platform

composer install

cp .env.example .env
php artisan key:generate
```

Configure `.env`:
```env
DB_DATABASE=saas_platform
DB_USERNAME=root
DB_PASSWORD=

ADMIN_EMAIL=admin@demo.com
ADMIN_PASSWORD=password
```

Create database:
```sql
CREATE DATABASE saas_platform
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_0900_ai_ci;
```

```bash
php artisan migrate
php artisan db:seed
php artisan serve
```

**Default credentials:**

| Email | Password | Role |
|-------|----------|------|
| admin@demo.com | password | Platform super-admin |
| member@demo.com | password | Company member |
| manager@demo.com | password | Company manager |

---

## Architecture

### Multi-Tenancy

- Users are **global** — one account, multiple companies
- Active company resolved from `X-Company-ID` header or Sanctum token ability
- `TenantContext` singleton holds active tenant per request
- `CompanyScope` global scope applied automatically on company-scoped models

### Permission System

Two layers — no Spatie, fully custom:

**1. Platform permissions** (`user_platform_permissions`)
- Pattern: `platform.{resource}.{action}`
- Examples: `platform.companies.create`, `platform.users.manage`
- `platform.*` wildcard = super-admin bypass on all Gate checks

**2. App permissions** (`user_app_permissions`)
- Pattern: `{resource}.{action}` scoped to user + company + app
- Examples: `invoices.approve`, `salary.view`, `convert`
- Immutable versioning — every change creates a new row (linked list)
- Rollback to any previous version supported

### Versioning (Immutable Linked List)

Entities with versioning: `users`, `companies`, `apps`, `roles`,
`company_users`, `user_app_permissions`, `user_platform_permissions`
