<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
requireAuth();

// Polling-based log reader — returns JSON instantly, no long-running connection.
// Client calls repeatedly with ?offset=N to get new lines since last read.

$id   = (int)($_GET['id'] ?? 0);
$type = preg_replace('/[^a-z]/', '', $_GET['type'] ?? 'server');
$offset = max(0, (int)($_GET['offset'] ?? 0));

if ($type === 'update') {
    $logFile = DATA_DIR . '/logs/update.log';
} elseif ($id > 0 && in_array($type, ['server', 'install'])) {
    $logFile = DATA_DIR . '/logs/' . $type . '-' . $id . '.log';
} else {
    jsonError('Invalid parameters', 400);
}

$lines     = [];
$newOffset = $offset;

clearstatcache(true, $logFile);
if (file_exists($logFile)) {
    $size = filesize($logFile);
    if ($size > $offset) {
        $fh = fopen($logFile, 'rb');
        fseek($fh, $offset);
        $data = fread($fh, $size - $offset);
        fclose($fh);
        $newOffset = $size;
        foreach (explode("\n", $data) as $line) {
            if ($line !== '') $lines[] = $line;
        }
    }
} elseif ($offset === 0) {
    $lines[] = 'Waiting for process to start...';
}

jsonResponse(['lines' => $lines, 'offset' => $newOffset]);
