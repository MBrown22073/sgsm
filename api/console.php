<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
requireAuth();

$id   = (int)($_GET['id'] ?? 0);
$type = preg_replace('/[^a-z]/', '', $_GET['type'] ?? 'server'); // 'server' or 'install' or 'update'

if ($type === 'update') {
    $logFile = DATA_DIR . '/logs/update.log';
} elseif ($id > 0 && in_array($type, ['server', 'install'])) {
    $logFile = DATA_DIR . '/logs/' . $type . '-' . $id . '.log';
} else {
    http_response_code(400);
    exit;
}

set_time_limit(0);
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

if (ob_get_level()) ob_end_clean();

function sse(string $line): void {
    echo 'data: ' . json_encode($line) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}

if (!file_exists($logFile)) {
    sse('Waiting for process to start...');
}

$offset  = 0;
$maxSecs = 300; // 5-minute stream limit; client reconnects if needed
$start   = time();

// Ensure PHP detects client disconnection promptly
ignore_user_abort(false);

while (time() - $start < $maxSecs) {
    if (connection_aborted()) break;

    clearstatcache(true, $logFile);
    if (file_exists($logFile)) {
        $size = filesize($logFile);
        if ($size > $offset) {
            $fh   = fopen($logFile, 'rb');
            fseek($fh, $offset);
            $data = fread($fh, $size - $offset);
            fclose($fh);
            $offset = $size;
            foreach (explode("\n", $data) as $line) {
                if ($line !== '') sse($line);
            }
        }
    }
    usleep(300000); // poll every 300ms
}

sse('--- Stream ended (reconnecting) ---');
