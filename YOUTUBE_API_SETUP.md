# YouTube Data API Setup Guide

## How It Works

This app uses a **Hybrid Detection** approach:

| What | Method | Cost |
|------|--------|------|
| Detecting if channel is LIVE | Scraping | FREE (unlimited) |
| Getting accurate start time | YouTube API | ~100 units (only when first going live) |

### Flow:
1. **Scraping** → Checks if channel is live/offline (primary detection)
2. **API** → Gets accurate `actualStartTime` ONLY when channel first goes live
3. **Subsequent checks** → Uses scraping, keeps the start time from first detection

This means:
- ✅ API quota usage is minimal (~100 units per NEW stream)
- ✅ Accurate duration from `actualStartTime`
- ✅ Scraping still works if API quota runs out

## Setup Instructions

### 1. Get a YouTube Data API v3 Key (Optional but Recommended)

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project
3. Enable **YouTube Data API v3**
4. Create API Key

### 2. Set Up the API Key

```bash
php artisan youtube:setup-api YOUR_API_KEY --test
```

This will:
- Test the API key
- Save to `.env`
- Enable hybrid detection

### 3. Or Manually Add to .env

```env
YOUTUBE_API_KEY=your_api_key_here
YOUTUBE_DETECTION_PROVIDER=hybrid
```

Then:
```bash
php artisan config:clear
```

## Provider Options

| Provider | Description |
|----------|-------------|
| `hybrid` (default) | Scraping + API for start time |
| `youtube_direct` | Only scraping |
| `youtube_api` | Only API (higher quota usage) |

## API Quota Usage

| Scenario | Quota Used |
|----------|-----------|
| Scraping only | 0 |
| Hybrid (per new stream) | ~100 units |
| Full API (per check) | ~100 units |

**With hybrid approach**: 10,000 quota ÷ 100 = ~100 new streams per day

## No API Key?

The app works WITHOUT API key using scraping only:
- Duration will be estimated from first detection time
- Less accurate but still functional

## Troubleshooting

### "API key not configured"
- Hybrid mode works with scraping only, just less accurate duration

### "quotaExceeded"
- Switch to `youtube_direct` or wait for quota reset
- Quota resets daily at midnight PST
