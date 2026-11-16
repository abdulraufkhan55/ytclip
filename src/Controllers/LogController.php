<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\AuditLog;

class LogController
{
    public function index(Request $request): void
    {
        Auth::requireAdmin();

        $auditLogs = AuditLog::getRecent(100);
        $fileLogs = Logger::getRecentLogs(100);

        View::render('logs.index', [
            'title' => 'System & Audit Logs',
            'auditLogs' => $auditLogs,
            'fileLogs' => $fileLogs
        ]);
    }

    public function clearFileLogs(Request $request): void
    {
        Auth::requireAdmin();

        $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
        if (file_exists($logFile)) {
            file_put_contents($logFile, '');
        }

        Logger::audit('logs_cleared', 'log', 'app.log');
        Session::setFlash('success', 'Application log file cleared.');
        Response::redirect('/logs');
    }
}
