# Deployment Guide

## Architecture

Single monolith container — Laravel (PHP-FPM) + Vue.js SPA + Nginx + Supervisor in one Docker image.

```
┌─────────────────────────────────────┐
│           attendance-app            │
│                                     │
│   Nginx :80  ──►  /api  ──►  PHP   │
│              ──►  /*    ──►  Vue    │
└─────────────────────────────────────┘
         Your External Database
```

---

## Quick Deploy

### 1. Clone on your server

```bash
git clone https://github.com/YOUR_USERNAME/simple-attendance.git
cd simple-attendance
```

### 2. Create your environment file

```bash
cp env.production.example .env
nano .env   # fill in the required values below
```

**Required values to set in `.env`:**

| Variable | Description | Example |
|---|---|---|
| `APP_KEY` | Laravel encryption key | `base64:xxxx...` |
| `APP_URL` | Your domain | `https://attendance.example.com` |
| `DB_HOST` | Database host IP | `host.docker.internal` (for local host DB) |
| `DB_DATABASE` | Database name | `simple_attendance` |
| `DB_USERNAME` | Database user | `attendance_user` |
| `DB_PASSWORD` | Database password | `a_secure_password` |
| `BARCODE_SECRET_KEY` | Random 32-char string | see below |

**Generate `APP_KEY`:**
```bash
docker run --rm php:8.2-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

**Generate `BARCODE_SECRET_KEY`:**
```bash
openssl rand -base64 32 | tr -d '/+=' | head -c 32
```

### 3. Deploy

```bash
docker compose up -d --build
```

### 4. Access

- **App**: `http://your-server-ip`
- **Login**: `admin@example.com` / `password`

> ⚠️ **Change the default admin password immediately after first login!**

---

## Updating the App

```bash
git pull
docker compose up -d --build
```

Docker layer caching makes this fast:
- Only changed layers are rebuilt (e.g., PHP-only changes skip `npm install`)
- Migrations run automatically on startup
- Downtime is ~5–15 seconds during container swap

---

## File Structure

```
simple-attendance/
├── docker/
│   ├── app.Dockerfile    ← Multi-stage build (Vue → Laravel+Nginx)
│   ├── entrypoint.sh     ← Runs migrations, seeds, cache on startup
│   ├── nginx.conf        ← Nginx config inside the container
│   └── supervisord.conf  ← Manages php-fpm, nginx, queue, scheduler
├── frontend/             ← Vue.js source (built into public/ during Docker build)
├── docker-compose.yml    ← Orchestrates the app service
├── .env                  ← Your secrets (never commit this)
└── env.production.example ← Template for .env
```

---

## Common Commands

```bash
# View live logs
docker compose logs -f app

# Run a migration manually
docker compose exec app php artisan migrate

# Clear and rebuild cache
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache

# Open a shell inside the container
docker compose exec app sh

# Check all containers are healthy
docker compose ps

# Stop everything
docker compose down

# Stop and wipe the database (⚠️ destructive)
docker compose down -v
```

---

## Optional: Enable Redis

For better performance on high-traffic deployments, enable Redis for caching and sessions:

1. In your `.env`, set:
```bash
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
```

2. Start with the redis profile:
```bash
docker compose --profile redis up -d --build
```

---

## HTTPS with a Reverse Proxy

If you're behind Traefik or Nginx Proxy Manager, add labels to `docker-compose.yml`:

```yaml
services:
  app:
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.attendance.rule=Host(`attendance.yourdomain.com`)"
      - "traefik.http.routers.attendance.entrypoints=websecure"
      - "traefik.http.routers.attendance.tls.certresolver=letsencrypt"
```

---

## Troubleshooting

### Database won't connect
Make sure your host database allows connections from Docker.
If using a local database on your machine, set `DB_HOST=host.docker.internal` in your `.env`.
```bash
docker compose exec app php artisan migrate:status
```

### Permission errors
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 775 storage bootstrap/cache
```

### Frontend not loading (blank page)
```bash
# Check if Vue assets were built into the image
docker compose exec app ls -la public/assets

# Rebuild from scratch (no cache)
docker compose up -d --build --force-recreate
```

### Queue jobs not processing
```bash
docker compose exec app supervisorctl status
docker compose exec app supervisorctl restart queue-worker
```
