# GoPasig Deployment Checklist

| Area | Value / Verification | Status |
| --- | --- | --- |
| Environment | Production / Staging / Local UAT | Pending |
| PHP Version | Run `php -v` | Pending |
| Laravel Version | Run `php artisan --version` | Pending |
| Node Version | Run `node -v` | Pending |
| Database | MySQL/MariaDB database name, user, host | Pending |
| Queue Configuration | `QUEUE_CONNECTION` and worker/scheduler setup | Pending |
| Cache Configuration | `CACHE_STORE`, config cache, route cache | Pending |
| Session Configuration | `SESSION_DRIVER`, lifetime, domain | Pending |
| Required API Keys | Google Maps and any notification credentials | Pending |
| Environment File | `.env` present and production values verified | Pending |
| Migrations | `php artisan migrate --force` | Pending |
| Seeders | Required production seeders executed only if intended | Pending |
| Storage Link | `php artisan storage:link` | Pending |
| Build Assets | `npm run build` completed | Pending |
| Permissions | `storage/` and `bootstrap/cache/` writable | Pending |
| Scheduler | Laravel scheduler configured | Pending |
| Queue Worker | Queue worker configured/restarted | Pending |
| Smoke Test | Login, dashboard, dispatch, alerts, driver, commuter | Pending |
| Backup Plan | Database and code rollback documented | Pending |
