# YouTube Live Monitor

Aplikasi web untuk memonitor channel YouTube dan mendeteksi apakah channel tersebut sedang melakukan live stream.

## Tech Stack

- **Backend**: Laravel 11
- **Frontend**: React 19 + Inertia.js
- **Database**: SQLite (development) / MySQL (production)
- **Styling**: Tailwind CSS v4

## Requirements

- PHP 8.2+
- Node.js 18+
- Composer
- SQLite atau MySQL

## Installation

### 1. Clone dan Install Dependencies

```bash
cd ~/youtube-live-monitor

# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 2. Environment Setup

File `.env` sudah dikonfigurasi dengan SQLite untuk development lokal.

**Untuk MySQL**, edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=youtube_live_monitor
DB_USERNAME=root
DB_PASSWORD=your_password
```

Lalu buat database:

```sql
CREATE DATABASE youtube_live_monitor;
```

### 3. Database Migration

```bash
php artisan migrate
```

### 4. (Optional) Seed Sample Data

```bash
php artisan db:seed
```

Ini akan membuat:
- 1 user test (`test@example.com` / `password`)
- 7 sample monitored channels
- 3 live streams (untuk 3 channel pertama)

### 5. Build Frontend

```bash
npm run build
```

### 6. Jalankan Server

```bash
php artisan serve
```

Buka http://localhost:8000

## Development

### Running Dev Server

```bash
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server
npm run dev
```

### Frontend Hot Reload

Dengan Vite dev server, perubahan React akan langsung terlihat tanpa refresh.

## Project Structure

```
youtube-live-monitor/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── DashboardController.php
│   │   └── Middleware/
│   │       └── HandleInertiaRequests.php
│   └── Models/
│       ├── User.php
│       ├── MonitoredChannel.php
│       └── LiveStream.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2024_01_01_000001_create_monitored_channels_table.php
│   │   └── 2024_01_01_000002_create_live_streams_table.php
│   └── seeders/
│       └── MonitoredChannelsSeeder.php
├── resources/
│   ├── css/
│   │   └── app.css
│   ├── js/
│   │   ├── app.jsx
│   │   ├── Layouts/
│   │   │   └── AppLayout.jsx
│   │   ├── components/
│   │   │   ├── LiveStreamCard.jsx
│   │   │   └── OfflineChannelCard.jsx
│   │   └── pages/
│   │       └── Dashboard.jsx
│   └── views/
│       └── app.blade.php
└── routes/
    ├── web.php
    └── api.php
```

## Database Schema

### monitored_channels
| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| user_id | bigint | Foreign key to users |
| youtube_channel_id | string | YouTube channel ID |
| channel_name | string | Channel display name |
| channel_url | string | Channel URL |
| channel_thumbnail | string | Channel thumbnail URL |
| is_active | boolean | Whether monitoring is active |
| created_at | timestamp | |
| updated_at | timestamp | |

**Constraints**: Unique (`user_id`, `youtube_channel_id`)

### live_streams
| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| monitored_channel_id | bigint | Foreign key to monitored_channels |
| youtube_video_id | string | YouTube video ID |
| title | string | Stream title |
| thumbnail | string | Stream thumbnail URL |
| started_at | timestamp | When stream started |
| ended_at | timestamp | When stream ended (nullable) |
| viewer_count | integer | Current viewer count |
| status | enum | 'live' or 'ended' |
| created_at | timestamp | |
| updated_at | timestamp | |

## Future Development (Phase 2+)

- [ ] YouTube Data API v3 integration
- [ ] Automatic live detection via Laravel Scheduler
- [ ] Queue for API polling
- [ ] Notification system (email, Discord, etc.)
- [ ] Live stream history
- [ ] Viewer statistics history
- [ ] Channel analytics

## License

MIT
