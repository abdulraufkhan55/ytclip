<?php
use App\Core\Security;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="text-white fw-bold m-0">Setup, API Configuration & Deployment Guide</h4>
        <p class="text-secondary small m-0">Step-by-step documentation for XAMPP Windows, Linux VPS, and official Social Media Developer APIs.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Navigation column -->
    <div class="col-lg-3">
        <div class="card-custom position-sticky" style="top: 85px;">
            <div class="card-body-custom p-3">
                <nav class="nav flex-column gap-1 small">
                    <a class="nav-link text-white py-1" href="#guide-quickstart"><i class="bi bi-lightning-charge me-2 text-warning"></i>Quickstart & Local Setup</a>
                    <a class="nav-link text-white py-1" href="#guide-youtube"><i class="bi bi-youtube me-2 text-danger"></i>YouTube Data API v3</a>
                    <a class="nav-link text-white py-1" href="#guide-tiktok"><i class="bi bi-tiktok me-2 text-info"></i>TikTok Content Posting API</a>
                    <a class="nav-link text-white py-1" href="#guide-instagram"><i class="bi bi-instagram me-2 text-pink" style="color: #f472b6;"></i>Instagram Reels Graph API</a>
                    <a class="nav-link text-white py-1" href="#guide-worker"><i class="bi bi-gear-wide-connected me-2 text-primary"></i>Background Worker Setup</a>
                    <a class="nav-link text-white py-1" href="#guide-vps"><i class="bi bi-hdd-network me-2 text-success"></i>Linux VPS Deployment</a>
                    <a class="nav-link text-white py-1" href="#guide-testing"><i class="bi bi-check2-all me-2 text-info"></i>Testing Checklist</a>
                </nav>
            </div>
        </div>
    </div>

    <!-- Content column -->
    <div class="col-lg-9">
        <!-- 1. Quickstart -->
        <div class="card-custom mb-4" id="guide-quickstart">
            <div class="card-header-custom">
                <h5 class="m-0 text-white fw-bold"><i class="bi bi-lightning-charge me-2 text-warning"></i>1. Local Development on Windows with XAMPP</h5>
            </div>
            <div class="card-body-custom">
                <p class="text-secondary">YTClip runs natively in XAMPP on Windows without requiring heavy container engines.</p>
                <ol class="text-secondary small ps-3 mb-3">
                    <li class="mb-2"><strong>Clone or place in htdocs:</strong> Place this project at <code>C:\xampp\htdocs\ytclip</code>.</li>
                    <li class="mb-2"><strong>Start Services:</strong> Open XAMPP Control Panel and start <strong>Apache</strong> and <strong>MySQL</strong>.</li>
                    <li class="mb-2"><strong>Database:</strong> Run MySQL database migration via schema file at <code>database/schema.sql</code> or the installer. Database name: <code>ytclip_db</code>.</li>
                    <li class="mb-2"><strong>Access Web Application:</strong> Open browser to <a href="http://localhost/ytclip/" target="_blank" class="text-primary"><code>http://localhost/ytclip/</code></a>.</li>
                    <li class="mb-2"><strong>Run Background Worker:</strong> Run via PowerShell:
                        <pre class="bg-dark text-info p-2 rounded mt-1"><code>C:\xampp\php\php.exe C:\xampp\htdocs\ytclip\worker.php --daemon</code></pre>
                    </li>
                </ol>
            </div>
        </div>

        <!-- 2. YouTube Data API -->
        <div class="card-custom mb-4" id="guide-youtube">
            <div class="card-header-custom">
                <h5 class="m-0 text-white fw-bold"><i class="bi bi-youtube me-2 text-danger"></i>2. YouTube Shorts (Google Cloud Console) Setup</h5>
            </div>
            <div class="card-body-custom text-secondary small">
                <ol class="ps-3 mb-3">
                    <li class="mb-2">Go to <a href="https://console.cloud.google.com/" target="_blank" class="text-primary">Google Cloud Console</a> and create a new project.</li>
                    <li class="mb-2">In <strong>APIs & Services &gt; Library</strong>, search for and enable <strong>YouTube Data API v3</strong>.</li>
                    <li class="mb-2">Go to <strong>OAuth consent screen</strong>:
                        <ul>
                            <li>Select <strong>External</strong> user type.</li>
                            <li>Add test users (your Google account email) while in testing mode.</li>
                            <li>Add Scopes: <code>https://www.googleapis.com/auth/youtube.upload</code>, <code>https://www.googleapis.com/auth/youtube.readonly</code>, <code>https://www.googleapis.com/auth/userinfo.profile</code>.</li>
                        </ul>
                    </li>
                    <li class="mb-2">In <strong>Credentials</strong>, click <strong>Create Credentials &gt; OAuth client ID</strong>:
                        <ul>
                            <li>Application type: <strong>Web application</strong>.</li>
                            <li>Authorized redirect URIs: <code><?= Security::e($app['url']) ?>/oauth/youtube/callback</code></li>
                        </ul>
                    </li>
                    <li class="mb-2">Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> into <a href="<?= $app['url'] ?>/settings" class="text-primary">Settings</a> or your <code>.env</code> file.</li>
                </ol>
            </div>
        </div>

        <!-- 3. TikTok Content Posting API -->
        <div class="card-custom mb-4" id="guide-tiktok">
            <div class="card-header-custom">
                <h5 class="m-0 text-white fw-bold"><i class="bi bi-tiktok me-2 text-info"></i>3. TikTok Content Posting API Setup</h5>
            </div>
            <div class="card-body-custom text-secondary small">
                <ol class="ps-3 mb-3">
                    <li class="mb-2">Register at <a href="https://developers.tiktok.com/" target="_blank" class="text-primary">TikTok for Developers</a>.</li>
                    <li class="mb-2">Create a new App and request <strong>Content Posting API</strong> permission.</li>
                    <li class="mb-2">Add Redirect URI: <code><?= Security::e($app['url']) ?>/oauth/tiktok/callback</code>.</li>
                    <li class="mb-2">Scopes needed: <code>user.info.basic</code>, <code>video.publish</code>, <code>video.upload</code>.</li>
                    <li class="mb-2">Copy the <strong>Client Key</strong> and <strong>Client Secret</strong> into Settings.</li>
                    <li class="mb-0"><strong>Note on App Review:</strong> In TikTok developer sandbox mode, you can connect creator accounts listed in your app's testing list. For production public publishing, submit for TikTok App Review.</li>
                </ol>
            </div>
        </div>

        <!-- 4. Instagram Reels Graph API -->
        <div class="card-custom mb-4" id="guide-instagram">
            <div class="card-header-custom">
                <h5 class="m-0 text-white fw-bold"><i class="bi bi-instagram me-2 text-pink" style="color: #f472b6;"></i>4. Instagram Reels (Meta for Developers) Setup</h5>
            </div>
            <div class="card-body-custom text-secondary small">
                <ol class="ps-3 mb-3">
                    <li class="mb-2">Visit <a href="https://developers.facebook.com/" target="_blank" class="text-primary">Meta for Developers</a> and create an app with type <strong>Business</strong>.</li>
                    <li class="mb-2">Add the <strong>Instagram Graph API</strong> and <strong>Facebook Login for Business</strong> products.</li>
                    <li class="mb-2">In Facebook Login settings, add Valid OAuth Redirect URI: <code><?= Security::e($app['url']) ?>/oauth/instagram/callback</code>.</li>
                    <li class="mb-2">Ensure your Instagram account is an <strong>Instagram Professional Account (Business or Creator)</strong> and connected to a Facebook Page. Personal accounts do not support API publishing.</li>
                    <li class="mb-2">Permissions requested: <code>instagram_basic</code>, <code>instagram_content_publish</code>, <code>pages_show_list</code>, <code>pages_read_engagement</code>.</li>
                    <li class="mb-0"><strong>Important Media URL Requirement:</strong> Meta's media-container API requires the video to be hosted at a publicly reachable HTTPS URL so Meta servers can download and transcode it. For local testing, use a tunnel like ngrok (e.g. <code>ngrok http 80</code>) and set <code>APP_URL</code> to the https tunnel address.</li>
                </ol>
            </div>
        </div>

        <!-- 5. Worker Setup -->
        <div class="card-custom mb-4" id="guide-worker">
            <div class="card-header-custom">
                <h5 class="m-0 text-white fw-bold"><i class="bi bi-gear-wide-connected me-2 text-primary"></i>5. Automated Background Worker Scheduling</h5>
            </div>
            <div class="card-body-custom text-secondary small">
                <p>The application worker handles video transcoding checks, API requests, retries with exponential backoff, and scheduled publishing without an administrator needing to keep the browser open.</p>

                <h6 class="text-white fw-bold mt-3">Option A: Windows Task Scheduler (Recommended for Windows)</h6>
                <ol class="ps-3 mb-3">
                    <li>Open <strong>Task Scheduler</strong> in Windows.</li>
                    <li>Click <strong>Create Task</strong> &gt; Name: <code>YTClipPublisherWorker</code>.</li>
                    <li>Trigger: <strong>On a schedule</strong> &gt; Repeat task every <strong>1 minute</strong> indefinitely.</li>
                    <li>Action: <strong>Start a program</strong>:
                        <ul>
                            <li>Program: <code>C:\xampp\php\php.exe</code></li>
                            <li>Arguments: <code>C:\xampp\htdocs\ytclip\worker.php --once</code></li>
                            <li>Start in: <code>C:\xampp\htdocs\ytclip</code></li>
                        </ul>
                    </li>
                </ol>

                <h6 class="text-white fw-bold mt-3">Option B: Linux Cron Job</h6>
                <p>Add the following entry to your crontab (<code>crontab -e</code>):</p>
                <pre class="bg-dark text-info p-2 rounded"><code>* * * * * /usr/bin/php /var/www/ytclip/worker.php --once >> /var/www/ytclip/storage/logs/cron.log 2>&1</code></pre>
            </div>
        </div>

        <!-- 6. Linux VPS Deployment -->
        <div class="card-custom mb-4" id="guide-vps">
            <div class="card-header-custom">
                <h5 class="m-0 text-white fw-bold"><i class="bi bi-hdd-network me-2 text-success"></i>6. Production Linux VPS Deployment Guide</h5>
            </div>
            <div class="card-body-custom text-secondary small">
                <p>For deploying to Ubuntu 22.04 / 24.04 LTS with Nginx, PHP 8.2-FPM, and MySQL:</p>
                <pre class="bg-dark text-light p-3 rounded" style="max-height: 250px; overflow-y: auto;"><code># 1. Install prerequisites
sudo apt update && sudo apt install -y nginx mysql-server php8.2-fpm php8.2-mysql php8.2-curl php8.2-mbstring php8.2-xml php8.2-zip ffmpeg

# 2. Permissions
sudo chown -R www-data:www-data /var/www/ytclip/storage
sudo chmod -R 775 /var/www/ytclip/storage

# 3. Nginx Virtual Host (/etc/nginx/sites-available/ytclip)
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
}</code></pre>
            </div>
        </div>

        <!-- 7. Testing Checklist -->
        <div class="card-custom" id="guide-testing">
            <div class="card-header-custom">
                <h5 class="m-0 text-white fw-bold"><i class="bi bi-check2-all me-2 text-info"></i>7. Quality & Verification Checklist</h5>
            </div>
            <div class="card-body-custom">
                <ul class="list-unstyled mb-0 small">
                    <li class="d-flex align-items-center gap-2 mb-2 text-secondary">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Administrator authentication with bcrypt password hashing and rate limiting.</span>
                    </li>
                    <li class="d-flex align-items-center gap-2 mb-2 text-secondary">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>CSRF token validation enforced on all state-changing POST requests.</span>
                    </li>
                    <li class="d-flex align-items-center gap-2 mb-2 text-secondary">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Video duration validation (30–60s) with pure PHP atom parser & FFprobe support.</span>
                    </li>
                    <li class="d-flex align-items-center gap-2 mb-2 text-secondary">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>OAuth tokens encrypted at rest using authenticated AES-256-GCM.</span>
                    </li>
                    <li class="d-flex align-items-center gap-2 mb-2 text-secondary">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Publishing queue worker with atomic job locks and exponential backoff retry.</span>
                    </li>
                    <li class="d-flex align-items-center gap-2 mb-2 text-secondary">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Append-only publishing attempts table for full debugging and auditability.</span>
                    </li>
                    <li class="d-flex align-items-center gap-2 text-secondary">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Mock mode adapters enabled for offline pipeline verification without live API keys.</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
