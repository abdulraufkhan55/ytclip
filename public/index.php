<?php

declare(strict_types=1);

// Application Entry Point
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\AccountController;
use App\Controllers\ApiController;
use App\Controllers\AttemptController;
use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\DashboardController;
use App\Controllers\GuideController;
use App\Controllers\LogController;
use App\Controllers\OAuthController;
use App\Controllers\QueueController;
use App\Controllers\ReportController;
use App\Controllers\SettingController;
use App\Controllers\TrackerController;
use App\Controllers\UserController;
use App\Controllers\VideoController;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;

// Initialize Session
Session::start();

// Set default timezone from config
$appConfig = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'UTC');

$request = new Request();
$router = new Router();

// Routes Definition

// Root redirect
$router->get('/', function(Request $req) {
    if (Auth::check()) {
        Response::redirect('/dashboard');
    } else {
        Response::redirect('/login');
    }
});

// Authentication & Setup Wizard
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/setup', [AuthController::class, 'showSetup']);
$router->post('/setup', [AuthController::class, 'processSetup']);

// Dashboard
$router->get('/dashboard', [DashboardController::class, 'index']);
$router->post('/dashboard/trigger-worker', [DashboardController::class, 'triggerWorker']);
$router->post('/dashboard/retry-failed', [DashboardController::class, 'retryAllFailed']);

// Video Library & Uploads
$router->get('/videos', [VideoController::class, 'index']);
$router->post('/videos/sync-folder', [VideoController::class, 'syncManual']);
$router->get('/upload', [VideoController::class, 'showUpload']);
$router->post('/upload', [VideoController::class, 'upload']);
$router->get('/videos/{id}', [VideoController::class, 'show']);
$router->post('/videos/{id}/update', [VideoController::class, 'update']);
$router->post('/videos/{id}/dispatch', [VideoController::class, 'dispatchPublish']);
$router->post('/videos/{id}/archive', [VideoController::class, 'archive']);
$router->post('/videos/{id}/delete', [VideoController::class, 'delete']);

// Social Accounts & OAuth
$router->get('/accounts', [AccountController::class, 'index']);
$router->post('/accounts/{id}/disconnect', [AccountController::class, 'disconnect']);
$router->post('/accounts/{id}/test', [AccountController::class, 'testConnection']);
$router->post('/accounts/mock', [AccountController::class, 'connectMock']);
$router->get('/oauth/{platform}', [OAuthController::class, 'redirect']);
$router->get('/oauth/{platform}/callback', [OAuthController::class, 'callback']);

// Publishing Queue & Worker
$router->get('/queue', [QueueController::class, 'index']);
$router->post('/queue/retry/{id}', [QueueController::class, 'retry']);
$router->post('/queue/cancel/{id}', [QueueController::class, 'cancel']);
$router->post('/queue/worker-now', [QueueController::class, 'runWorkerNow']);

// Publishing Tracker
$router->get('/tracker', [TrackerController::class, 'index']);
$router->post('/tracker/retry-video/{video_id}', [TrackerController::class, 'retryVideoJobs']);

// Scheduling Calendar
$router->get('/calendar', [CalendarController::class, 'index']);
$router->get('/calendar/events', [CalendarController::class, 'events']);
$router->post('/calendar/bulk', [CalendarController::class, 'bulkSchedule']);

// Attempt History
$router->get('/attempts', [AttemptController::class, 'index']);

// Reports & CSV
$router->get('/reports', [ReportController::class, 'index']);
$router->get('/reports/export', [ReportController::class, 'exportCsv']);

// User Management
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'create']);
$router->post('/users/{id}/status', [UserController::class, 'updateStatus']);
$router->post('/users/{id}/delete', [UserController::class, 'delete']);

// Settings
$router->get('/settings', [SettingController::class, 'index']);
$router->post('/settings', [SettingController::class, 'update']);

// Logs
$router->get('/logs', [LogController::class, 'index']);
$router->post('/logs/clear-file', [LogController::class, 'clearFileLogs']);

// Setup Guide
$router->get('/setup-guide', [GuideController::class, 'index']);

// Public Media & AJAX APIs
$router->get('/api/videos/{id}/stream', [ApiController::class, 'streamVideo']);
$router->get('/api/videos/{id}/thumbnail', [ApiController::class, 'streamThumbnail']);
$router->get('/api/jobs/{id}/status', [ApiController::class, 'jobStatus']);
$router->get('/api/worker/run', [ApiController::class, 'runWorkerAjax']);

// Dispatch
$router->dispatch($request);
