<?php

declare(strict_types=1);

// CLI Background Publishing Worker
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access denied. This script can only be run from the command line.\n");
}

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Logger;
use App\Services\PublishingWorker;

$options = getopt('', ['once', 'daemon', 'sleep::', 'max::']);

$isOnce = isset($options['once']);
$sleepSeconds = isset($options['sleep']) ? (int) $options['sleep'] : 5;
$maxIterations = isset($options['max']) ? (int) $options['max'] : 0;

echo "====================================================\n";
echo " YTClip Short Video Publishing Worker\n";
echo " Mode: " . ($isOnce ? "Single-Run (--once)" : "Continuous Loop (--daemon)") . "\n";
echo " Time: " . date('Y-m-d H:i:s T') . "\n";
echo "====================================================\n";

$worker = new PublishingWorker();

if ($isOnce) {
    $processed = $worker->runOnce();
    echo $processed ? "[SUCCESS] Ready jobs were processed.\n" : "[IDLE] No jobs pending or ready at this time.\n";
} else {
    echo "Running worker loop. Press Ctrl+C to terminate...\n";
    $worker->loop($sleepSeconds, $maxIterations);
}
