# Cheetah Uptime Monitoring Platform

> High-performance website uptime tracking, SSL certificate verification, and real-time incident alerting powered by Laravel 11, SQLite/MySQL, and modern vanilla JavaScript.

---

## Features

- **Real-Time Uptime Tracking:** Continuous HTTP probe checks with configurable intervals (from 30 seconds to minutes).
- **False-Positive Protection:** 2-second automatic retry guard to prevent false-alarm outage alerts during transient network glitches.
- **Latency & Degraded Detection:** Tracks response latency thresholds (`max_response_time_ms`) and alerts when response times exceed limits.
- **Keyword Verification:** Detects server crashes or maintenance screens that return HTTP 200 OK.
- **SSL Certificate Monitoring:** Automated TLS handshake checks providing 30-day and 7-day expiration warnings.
- **Multi-Tenant Security:** Secure token-based authentication via Laravel Sanctum; strict model scoping and CORS protection.
- **Automated Data Pruning:** Daily pruning of ping records older than 7 days via `uptime:clean` and Laravel's `Prunable` trait.
- **Frontend Dashboard:** Dark-theme dashboard with interactive latency charts (Chart.js), real-time client-side log filtering, log export (.txt), and pricing calculators.
- **Dockerized Stack:** Production-ready container orchestration with PHP 8.3 FPM, Nginx, MySQL, Queue Worker, and Scheduler.

---

## Architecture Overview

```
                      +-----------------------------+
                      |   Cheetah Frontend UI       |
                      | (HTML5 / Bootstrap 5 / JS)  |
                      +--------------+--------------+
                                     |
                                REST API
                                     v
                      +-----------------------------+
                      |      Nginx Web Server       |
                      +--------------+--------------+
                                     |
                                FastCGI (9000)
                                     v
                      +-----------------------------+
                      |   Laravel 11 (PHP 8.3 FPM)  |
                      |   - Sanctum Auth Middleware |
                      |   - MonitorController       |
                      +--------------+--------------+
                                     |
            +------------------------+------------------------+
            |                                                 |
            v                                                 v
+-----------------------+                         +-----------------------+
|  Queue Worker         |                         |  Database (SQLite/    |
|  - CheckSingleMonitor |                         |           MySQL)      |
|  - CheckSslCertificate|                         |  - users / monitors   |
+-----------+-----------+                         |  - pings / tokens     |
            |                                     +-----------------------+
            v
+-----------------------+
| External Integrations |
| - Target Websites     |
| - Discord Webhook     |
| - Email Notifications |
+-----------------------+
```

---

## Prerequisites

- **PHP:** `^8.3`
- **Composer:** `^2.x`
- **Node.js:** (optional, for asset tooling)
- **Docker & Docker Compose:** (optional, for containerized deployment)

---

## Quickstart (Local Development)

### 1. Clone the repository
```bash
git clone https://github.com/islamfazliyev/laravel-uptime-api.git
cd laravel-uptime-api
```

### 2. Configure Environment
```bash
cd uptime-tracker
cp .env.example .env
php artisan key:generate
```

### 3. Run Migrations & Seed
```bash
php artisan migrate
```

### 4. Start Services via Helper Script
From the project root:
```bash
./start.sh
```
This script starts:
- Laravel API server on `http://127.0.0.1:8000`
- Background Scheduler (`php artisan schedule:work`)
- Queue Worker (`php artisan queue:work`)

Open `uptime-frontend/index.html` or `uptime-frontend/control-center.html` in your browser.

---

## Docker Deployment

To launch the full containerized stack:

```bash
# 1. Copy Docker environment variables
cp .env.docker.example .env

# 2. Build and launch services in background
docker compose up -d --build

# 3. Run database migrations inside the app container
docker compose exec app php artisan migrate --force
```

Services exposed:
- **API & Web:** `http://localhost:8000`
- **MySQL Database:** `localhost:33066`

---

## Required Environment Variables

Key variables in `uptime-tracker/.env`:

| Variable | Description | Default |
|---|---|---|
| `APP_URL` | Base application URL | `http://localhost:8000` |
| `DB_CONNECTION` | Database driver (`sqlite` or `mysql`) | `sqlite` |
| `QUEUE_CONNECTION` | Queue driver (`database` or `sync`) | `database` |
| `CORS_ALLOWED_ORIGINS` | Comma-separated list of allowed CORS domains | Local dev URLs |
| `DISCORD_ALERT_WEBHOOK`| Discord webhook URL for instant incident alerts | `null` |
| `ALERT_EMAIL_ADDRESS`  | Destination email address for uptime notifications | `admin@example.com` |

---

## Artisan Commands

- **Run Due Uptime Checks:**
  ```bash
  php artisan uptime:check
  ```
  Evaluates all active, unpaused monitors whose check interval has elapsed and dispatches probing jobs.

- **Prune Historical Ping Records:**
  ```bash
  # Delete records older than 7 days (default)
  php artisan uptime:clean

  # Delete records older than 30 days
  php artisan uptime:clean --days=30
  ```

- **Model Prune (Laravel Native):**
  ```bash
  php artisan model:prune --model="App\Models\Ping"
  ```

---

## Scheduled Tasks Configuration

Cron jobs are defined in `uptime-tracker/routes/console.php`:

```php
// Dispatch checks for monitors due for probing
Schedule::command('uptime:check')->everyMinute();

// Prune historical pings daily
Schedule::command('uptime:clean')->daily();
```

In production, register the standard Laravel Cron entry on the host:
```crontab
* * * * * cd /path-to-project/uptime-tracker && php artisan schedule:run >> /dev/null 2>&1
```

---

## Automated Testing Suite

The application includes automated unit and feature tests covering authentication, multi-tenant isolation, state transitions (up, down, degraded), exception handling, and database optimization:

```bash
cd uptime-tracker
php artisan test
```

---

## Postman Collection

Import the included collection into Postman for quick testing and integration:
- File: [`Cheetah_Uptime_API.postman_collection.json`](./Cheetah_Uptime_API.postman_collection.json)
- Reference: [`API.md`](./API.md)
