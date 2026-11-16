# Multi-Platform Short Video Publishing and Automation System (YTClip)

A complete, production-oriented PHP & MySQL web application built for authenticated administrators and operators to upload short videos (30–60s) once and publish them to multiple connected social media accounts:

- **YouTube Shorts** (Google Cloud Console / YouTube Data API v3)
- **TikTok** (TikTok for Developers / Content Posting API)
- **Instagram Reels** (Meta for Developers / Instagram Graph API v19.0)

Built with pure official platform APIs and zero unauthorized scraping. Features centralized video management, drag-and-drop upload with client-side preview, AES-256-GCM token encryption, background publishing queue with exponential backoff, scheduling with timezone support, matrix publishing tracker, and append-only audit logs.

---

## Architecture Overview

```
ytclip/
├── composer.json               # PSR-4 Autoloading definition
├── composer.phar               # Bundled Composer executable
├── .env.example                # Environment variables template
├── .env                        # Local environment configuration
├── .htaccess                   # Root Apache rewrite rules & directory protection
├── index.php                   # Root entry forwarder
├── install.php                 # Web & CLI installation wizard
├── worker.php                  # CLI background publishing worker
│
├── config/
│   ├── app.php                 # App config & environment variables loader
│   ├── database.php            # PDO MySQL connection settings
│   └── platforms.php           # OAuth endpoints, scopes & API endpoints
│
├── database/
│   ├── schema.sql              # MySQL/MariaDB database schema (8 tables)
│   └── seed.sql                # Default platform settings
│
├── src/
│   ├── Core/
│   │   ├── Database.php        # Singleton PDO manager with query helpers
│   │   ├── Env.php             # .env file parser & environment provider
│   │   ├── Security.php        # AES-256-GCM encryption, CSRF protection, sanitization
│   │   ├── Session.php         # Secure cookie handling, session regeneration, flash msgs
│   │   ├── Auth.php            # RBAC authentication (Admin/Operator), login rate limiting
│   │   ├── Logger.php          # Structured application & database audit logging
│   │   ├── Request.php         # HTTP request wrapper & JSON body parser
│   │   ├── Response.php        # JSON, redirect, and file streaming helper
│   │   ├── Router.php          # Request router with dynamic regex parameters & CSRF check
│   │   └── View.php            # Template renderer with layout nesting
│   │
│   ├── Models/
│   │   ├── User.php            # User authentication, roles & lifecycle
│   │   ├── SocialAccount.php   # Account credentials with encrypted tokens
│   │   ├── Video.php           # Video asset metadata and live statistics
│   │   ├── PublishingJob.php   # Queue jobs, atomic locks, duplicate safeguards
│   │   ├── PublishingAttempt.php # Append-only publishing attempts & sanitized responses
│   │   ├── AuditLog.php        # Administrative audit trail records
│   │   └── Setting.php         # Dynamic application settings with runtime cache
│   │
│   ├── Services/
│   │   ├── VideoProcessor.php  # Upload validation, FFprobe & pure PHP MP4 atom parser
│   │   ├── QueueManager.php    # Job dispatch, retry logic, duplicate checks, daily limits
│   │   ├── SchedulerService.php# Timezone conversions (UTC storage), FullCalendar events
│   │   └── PublishingWorker.php# Queue worker loop, exponential backoff, stale job recovery
│   │
│   ├── Adapters/
│   │   ├── PlatformAdapterInterface.php # Contract for platform publishing adapters
│   │   ├── AbstractPlatformAdapter.php  # Resilient cURL client & error sanitization
│   │   ├── YouTubePublisher.php         # YouTube Data API v3 resumable upload adapter
│   │   ├── TikTokPublisher.php          # TikTok Content Posting API Direct Post adapter
│   │   ├── InstagramPublisher.php       # Instagram Graph API Reels media-container adapter
│   │   ├── MockPublisher.php            # Development simulator adapter for test accounts
│   │   └── AdapterFactory.php           # Adapter resolver (Mock vs Live APIs)
│   │
│   └── Controllers/
│       ├── AuthController.php      # Login, logout, initial admin setup
│       ├── DashboardController.php # Live dashboard figures & 7-day Chart.js metrics
│       ├── VideoController.php     # Video library, drag & drop upload, details preview
│       ├── AccountController.php   # OAuth connections, test verification, mock accounts
│       ├── OAuthController.php     # Official OAuth 2.0 redirect & callback flows
│       ├── QueueController.php     # Publishing queue, retries, cancellations, manual worker
│       ├── TrackerController.php   # Multi-platform status matrix tracker
│       ├── CalendarController.php  # FullCalendar scheduling & bulk dispatcher
│       ├── AttemptController.php   # Append-only attempt history & payload inspector
│       ├── ReportController.php    # Performance analytics & CSV report export
│       ├── UserController.php      # User management, roles, and status controls
│       ├── SettingController.php   # Platform credentials, limits, mock mode switch
│       ├── LogController.php       # System app.log & audit log viewer
│       ├── GuideController.php     # In-app API documentation & setup guides
│       └── ApiController.php       # Video byte-range streaming & live AJAX polling
│
├── views/                          # Blade-like modular PHP views
│   ├── layouts/                    # Main (sidebar/navbar) and Auth layouts
│   ├── auth/                       # Login view
│   ├── dashboard/                  # Dashboard view & 404
│   ├── videos/                     # Library, Upload & Detail views
│   ├── accounts/                   # Social Accounts view
│   ├── queue/                      # Queue view
│   ├── tracker/                    # Matrix tracker view
│   ├── calendar/                   # Interactive calendar view
│   ├── attempts/                   # Attempt history view
│   ├── reports/                    # Analytics & CSV export view
│   ├── users/                      # User management view
│   ├── settings/                   # Settings view
│   ├── logs/                       # System & audit logs view
│   └── setup/                      # Install & developer guide views
│
├── public/                         # Web root (accessible by web server)
│   ├── index.php                   # Public entry point
│   ├── .htaccess                   # Rewrite rules
│   └── assets/                     # Custom CSS, JS, and uploader scripts
│
├── storage/                        # Protected storage (.htaccess protected)
│   ├── uploads/                    # Uploaded video files
│   ├── thumbnails/                 # Generated video posters
│   ├── keys/                       # Secret AES encryption keys
│   └── logs/                       # app.log
│
└── tests/
    └── test_system.php             # Comprehensive automated verification suite
```

---

## 15 Required Application Pages

1. **Login** (`/login`) — Secure authentication with login rate-limiting and CSRF validation.
2. **Dashboard** (`/dashboard`) — 9 live metric cards, platform summaries, 7-day publishing charts, upcoming schedules, and recent upload activity.
3. **Video Library** (`/videos`) — Searchable and filterable video assets, status badges, and asset management.
4. **Upload Video** (`/upload`) — Drag-and-drop file upload with HTML5 client-side video preview, duration validation (30–60s), and multi-platform distribution checkboxes.
5. **Video Details and Preview** (`/videos/{id}`) — Embedded HTML5 player with byte-range streaming, metadata editor, and publishing target manager.
6. **Social Account Connections** (`/accounts`) — Separate connection workflows for YouTube, TikTok, and Instagram, token verification, and test account simulator.
7. **Publishing Queue** (`/queue`) — Live status of queued, validating, uploading, and processing jobs with retry, cancel, and manual worker trigger.
8. **Publishing Tracker** (`/tracker`) — Matrix view with one row per video and individual status columns for YouTube, TikTok, and Instagram, plus direct links to published posts.
9. **Scheduling Calendar** (`/calendar`) — Visual monthly/weekly calendar of posts, timezone indicators, and bulk video scheduling modal.
10. **Publishing Attempt History** (`/attempts`) — Immutable append-only attempt logs with sanitized API response payload modals.
11. **Reports and CSV Export** (`/reports`) — Channel efficiency metrics, platform publishing success rates, and downloadable CSV reports (`/reports/export`).
12. **User Management** (`/users`) — Admin and Operator role management, status toggling, and user creation.
13. **Application Settings** (`/settings`) — Configurable duration limits (30–60s), max upload sizes, FFprobe binary paths, daily posting limits, mock mode switch, and platform OAuth credentials.
14. **System Logs** (`/logs`) — Structured execution logs (`app.log`) and immutable administrative audit trail.
15. **Setup and Installation Guide** (`/setup-guide` and `install.php`) — Complete instructions for developer app credentials, XAMPP local setup, and Linux VPS deployment.

---

## Local Installation Guide (XAMPP on Windows)

### Prerequisites
- XAMPP with PHP 8.1+ and MySQL/MariaDB (Ensure Apache & MySQL are running in XAMPP Control Panel).
- Extensions enabled: `curl`, `openssl`, `pdo_mysql`, `mbstring`, `fileinfo`.

### Step 1: Place Project in `htdocs`
Clone or place this repository in:
```
C:\xampp\htdocs\ytclip
```

### Step 2: Database Migration & Admin Setup
Run the standalone installer via CLI:
```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\ytclip\install.php "Super Admin" "admin@ytclip.local" "Admin@12345"
```
Or open your browser to:
```
http://localhost/ytclip/install.php
```

### Step 3: Access Application
Open your web browser:
```
http://localhost/ytclip/
```
Log in using:
- **Email:** `admin@ytclip.local`
- **Password:** `Admin@12345`

### Step 4: Run Background Publishing Worker
To process jobs in the queue:
```powershell
# Single iteration (ideal for Windows Task Scheduler or Cron):
C:\xampp\php\php.exe C:\xampp\htdocs\ytclip\worker.php --once

# Continuous daemon loop:
C:\xampp\php\php.exe C:\xampp\htdocs\ytclip\worker.php --daemon
```

---

## Windows Task Scheduler Configuration
1. Open **Task Scheduler** in Windows.
2. Click **Create Task** &gt; Name: `YTClipWorker`.
3. Trigger: **On a schedule** &gt; Repeat task every **1 minute** indefinitely.
4. Action: **Start a program**:
   - **Program:** `C:\xampp\php\php.exe`
   - **Arguments:** `C:\xampp\htdocs\ytclip\worker.php --once`
   - **Start in:** `C:\xampp\htdocs\ytclip`

---

## Linux VPS Deployment Guide (Ubuntu / Debian)

### 1. Install System Dependencies
```bash
sudo apt update && sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip ffmpeg
```

### 2. File Permissions
```bash
sudo chown -R www-data:www-data /var/www/ytclip/storage
sudo chmod -R 775 /var/www/ytclip/storage
```

### 3. Nginx Virtual Host Configuration (`/etc/nginx/sites-available/ytclip`)
```nginx
server {
    listen 80;
    server_name shorts.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name shorts.yourdomain.com;
    root /var/www/ytclip/public;
    index index.php;

    ssl_certificate /etc/letsencrypt/live/shorts.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/shorts.yourdomain.com/privkey.pem;

    client_max_body_size 250M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 4. Background Worker Cron Job
Add to crontab (`crontab -e`):
```bash
* * * * * /usr/bin/php /var/www/ytclip/worker.php --once >> /var/www/ytclip/storage/logs/cron.log 2>&1
```

---

## Developer Application Setup Instructions

### 1. YouTube Data API v3 (Google Cloud Console)
1. Go to [Google Cloud Console](https://console.cloud.google.com/) and create a project.
2. In **APIs & Services &gt; Library**, enable **YouTube Data API v3**.
3. In **OAuth consent screen**, set user type to **External** and add test users.
4. Add scopes:
   - `https://www.googleapis.com/auth/youtube.upload`
   - `https://www.googleapis.com/auth/youtube.readonly`
   - `https://www.googleapis.com/auth/userinfo.profile`
5. In **Credentials**, create an **OAuth 2.0 Client ID** (Web application).
   - Authorized Redirect URI: `http://localhost/ytclip/oauth/youtube/callback` (or your production URL).
6. Enter the Client ID and Client Secret in `Settings` or `.env`.

### 2. TikTok Content Posting API (TikTok for Developers)
1. Register at [TikTok for Developers](https://developers.tiktok.com/).
2. Create an App and add the **Content Posting API** product.
3. Configure Redirect URI: `http://localhost/ytclip/oauth/tiktok/callback`.
4. Permissions requested: `user.info.basic`, `video.publish`, `video.upload`.
5. Enter the Client Key and Client Secret in `Settings` or `.env`.
6. For development testing, add your creator TikTok account to the app's test users list.

### 3. Instagram Reels (Meta for Developers / Instagram Graph API)
1. Go to [Meta for Developers](https://developers.facebook.com/) and create an app with type **Business**.
2. Add the **Instagram Graph API** and **Facebook Login for Business** products.
3. In Facebook Login Settings, set Valid OAuth Redirect URI: `http://localhost/ytclip/oauth/instagram/callback`.
4. Permissions requested: `instagram_basic`, `instagram_content_publish`, `pages_show_list`, `pages_read_engagement`.
5. Connect your Instagram Professional (Business/Creator) account to a Facebook Page.
6. Enter App ID and App Secret in `Settings` or `.env`.
7. **Notice:** Meta's Reels API requires the video to be accessible over a public HTTPS URL (use an ngrok tunnel for local testing, e.g. `ngrok http 80`).

---

## Automated Verification Suite

Run the full end-to-end automated test suite:
```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\ytclip\tests\test_system.php
```
Tests 25 critical features:
- Database connectivity & schema migration
- AES-256-GCM encryption & decryption at rest
- CSRF token generation & validation
- Authentication & rate limiting
- Social account token lifecycle
- Video asset creation & duration rules
- Queue dispatch & duplicate-publication safeguard
- Background worker execution & status progression
- Append-only publishing attempt logging
- Real database metric calculations
- Timezone conversions & calendar feeds
- Audit trail logging
