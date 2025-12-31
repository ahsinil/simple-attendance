# Deployment Guide

## Quick Deploy with Docker Compose

### 1. Clone on your VPS
```bash
git clone https://github.com/YOUR_USERNAME/simple-attendance.git
cd simple-attendance
```

### 2. Create environment file
```bash
cp env.production.example .env

# Generate APP_KEY
docker run --rm -v $(pwd):/app -w /app php:8.2-cli php artisan key:generate --show
# Copy the output and paste it as APP_KEY in .env

# Generate BARCODE_SECRET_KEY (random 32 characters)
openssl rand -base64 32 | tr -d '/+=' | head -c 32

# Edit .env with your settings
nano .env
```

**Required settings to change:**
- `APP_KEY` - Generated key from above
- `APP_URL` - Your domain (e.g., `https://attendance.yourdomain.com`)
- `DB_PASSWORD` - Secure database password
- `DB_ROOT_PASSWORD` - Secure root password
- `BARCODE_SECRET_KEY` - Random 32-character string

### 3. Deploy
```bash
docker compose up -d --build
```

### 4. Access
- **App**: http://your-server-ip
- **Login**: admin@example.com / password

> ⚠️ **Important**: Change the default admin password immediately after first login!

---

## Environment Variables

| Variable | Required | Description | Example |
|----------|----------|-------------|---------|
| `APP_KEY` | ✅ | Laravel encryption key | `base64:xxxx...` |
| `APP_URL` | ✅ | Your domain | `https://attendance.example.com` |
| `DB_DATABASE` | ✅ | Database name | `simple_attendance` |
| `DB_USERNAME` | ✅ | Database user | `attendance_user` |
| `DB_PASSWORD` | ✅ | Database password | `secure_password` |
| `DB_ROOT_PASSWORD` | ✅ | MySQL root password | `root_password` |
| `BARCODE_SECRET_KEY` | ✅ | Barcode signing key | `random_32_char_string` |
| `CORS_ALLOWED_ORIGINS` | ❌ | Allowed CORS origins | `https://app.example.com` |

---

## Commands

### View logs
```bash
docker compose logs -f app
```

### Run migrations manually
```bash
docker compose exec app php artisan migrate
```

### Clear cache
```bash
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

### Rebuild after code changes
```bash
git pull
docker compose up -d --build
```

### Access container shell
```bash
docker compose exec app sh
```

---

## With HTTPS (Traefik or Nginx Proxy)

If you're using Traefik or nginx-proxy, add labels to docker-compose.yml:

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

## Optional: Using Redis

For better performance, enable Redis for caching and sessions:

1. Uncomment Redis settings in your `.env`:
```bash
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
```

2. The Redis service is already included in docker-compose.yml.

---

## Health Check

The application includes a health endpoint at `/api/health` (if implemented) or you can check:

```bash
# Check if containers are running
docker compose ps

# Check app logs for errors
docker compose logs app --tail=50

# Test database connection
docker compose exec app php artisan migrate:status
```

---

## Troubleshooting

### Database connection error
```bash
docker compose exec app php artisan migrate:status
docker compose logs db
```

### Permission issues
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 775 storage bootstrap/cache
```

### Frontend not loading
```bash
# Check if assets were built correctly
docker compose exec app ls -la public/assets

# Rebuild the container
docker compose up -d --build --force-recreate app
```

### Queue jobs not processing
```bash
# Check queue worker status
docker compose exec app supervisorctl status

# Restart queue worker
docker compose exec app supervisorctl restart queue-worker
```
