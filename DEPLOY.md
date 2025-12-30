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

# Edit .env with your settings
nano .env
```

### 3. Deploy
```bash
docker compose up -d --build
```

### 4. Access
- **App**: http://your-server-ip
- **Login**: admin@example.com / password

---

## Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `APP_KEY` | Laravel encryption key | `base64:xxxx...` |
| `APP_URL` | Your domain | `https://attendance.example.com` |
| `DB_DATABASE` | Database name | `simple_attendance` |
| `DB_USERNAME` | Database user | `attendance_user` |
| `DB_PASSWORD` | Database password | `secure_password` |
| `DB_ROOT_PASSWORD` | MySQL root password | `root_password` |
| `BARCODE_SECRET_KEY` | Barcode signing key | `random_32_char_string` |

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
```

### Rebuild after code changes
```bash
git pull
docker compose up -d --build
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
      - "traefik.http.routers.attendance.tls.certresolver=letsencrypt"
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
```
