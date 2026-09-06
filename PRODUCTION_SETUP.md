# Production Deployment Guide

## System Requirements

1. **Queue Worker** - Process detection jobs
2. **Scheduler** - Dispatch jobs every minute

## Quick Start

### 1. Start Queue Worker

```bash
# Single worker (development/small scale)
php artisan queue:work --queue=youtube --sleep=3 --tries=3

# Multiple workers (production)
php artisan queue:work --queue=youtube --sleep=3 --tries=3 &
```

### 2. Start Scheduler

```bash
# For development - runs scheduler in foreground
php artisan schedule:work

# For production - use cron (see below)
```

## Production Setup

### Using Supervisor (Recommended)

Install supervisor:
```bash
sudo apt-get install supervisor
```

Create config file:
```bash
sudo nano /etc/supervisor/conf.d/youtube-monitor.conf
```

Add this content:
```ini
[program:youtube-monitor-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --queue=youtube --sleep=3 --tries=3 --max-time=3600
numprocs=2
autostart=true
autorestart=true
startretries=3
stopwaitsecs=30
user=www-data
```

Create scheduler cron job:
```bash
sudo crontab -e
```

Add:
```cron
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

Apply changes:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start youtube-monitor-worker:*
```

### Check Status

```bash
sudo supervisorctl status
```

### Logs

```bash
# Queue worker logs
tail -f /path/to/project/storage/logs/laravel.log

# Scheduler logs
tail -f /path/to/project/storage/logs/monitor-schedule.log
```

## Using Systemd (Alternative)

Create service file:
```bash
sudo nano /etc/systemd/system/youtube-monitor-worker.service
```

```ini
[Unit]
Description=YouTube Monitor Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/your/project
ExecStart=/usr/bin/php artisan queue:work --queue=youtube --sleep=3 --tries=3
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Create timer for scheduler:
```bash
sudo nano /etc/systemd/system/youtube-monitor-scheduler.timer
```

```ini
[Unit]
Description=YouTube Monitor Scheduler (every minute)
Requires=youtube-monitor-worker.service

[Timer]
OnBootSec=10
OnUnitActiveSec=60
Unit=youtube-monitor-scheduler.service

[Install]
WantedBy=timers.target
```

Create scheduler service:
```bash
sudo nano /etc/systemd/system/youtube-monitor-scheduler.service
```

```ini
[Unit]
Description=YouTube Monitor Scheduler

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/path/to/your/project
ExecStart=/usr/bin/php artisan schedule:run
```

Enable:
```bash
sudo systemctl daemon-reload
sudo systemctl enable youtube-monitor-worker.service
sudo systemctl enable youtube-monitor-scheduler.timer
sudo systemctl start youtube-monitor-scheduler.timer
```

## Verification

Check if everything is working:

```bash
# 1. Check scheduler is running
php artisan schedule:list

# 2. Check queue worker is processing
php artisan queue:monitor youtube

# 3. Manual test
php artisan monitor:check --sync

# 4. Check database for latest detection
php artisan tinker --execute="echo \App\Models\LiveStream::latest()->first()?->toJson();"
```

## Troubleshooting

### Jobs not being processed
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear stuck jobs
php artisan queue:flush
```

### Scheduler not running
```bash
# Check cron
crontab -l

# Test cron manually
cd /path/to/project && php artisan schedule:run
```

### Queue worker stopped
```bash
# Check logs
tail -f storage/logs/laravel.log

# Restart
sudo systemctl restart youtube-monitor-worker:*
```

## Memory Limit

For large number of channels, increase memory:
```bash
php -d memory_limit=512M artisan queue:work --queue=youtube
```

Or in supervisor:
```ini
command=php -d memory_limit=512M /path/to/project/artisan queue:work --queue=youtube
```
