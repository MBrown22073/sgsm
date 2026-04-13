<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
requireAuth();

$db     = new GSM_DB();
$method = $_SERVER['REQUEST_METHOD'];
$path   = trim($_GET['path'] ?? '');

if (!$path) jsonError('path is required', 400);

// ── Security: restrict all access to the configured servers directory ─────────
$serversBase = rtrim($db->getSetting('servers_path') ?: '/opt/servers', '/\\');

// Normalize the requested path manually to defeat traversal (/../ etc.)
$norm = [];
foreach (explode('/', str_replace('\\', '/', $path)) as $seg) {
    if ($seg === '' || $seg === '.') continue;
    if ($seg === '..') { if ($norm) array_pop($norm); continue; }
    $norm[] = $seg;
}
$cleanPath = '/' . implode('/', $norm);

// Normalise the base the same way
$baseNorm = [];
foreach (explode('/', str_replace('\\', '/', $serversBase)) as $seg) {
    if ($seg === '' || $seg === '.') continue;
    if ($seg === '..') { if ($baseNorm) array_pop($baseNorm); continue; }
    $baseNorm[] = $seg;
}
$cleanBase = '/' . implode('/', $baseNorm);

// The requested path must sit inside the servers base directory
if (!str_starts_with($cleanPath . '/', $cleanBase . '/')) {
    jsonError('Access denied: path is outside the servers directory', 403);
}

// ── GET: read file ────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (!file_exists($cleanPath)) jsonError('File not found', 404);
    if (!is_file($cleanPath))     jsonError('Not a file', 400);
    jsonResponse(['content' => file_get_contents($cleanPath)]);
}

// ── PUT: write file ───────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $b = getBody();
    if (!array_key_exists('content', $b)) jsonError('content is required', 400);
    $dir = dirname($cleanPath);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        jsonError('Could not create directory', 500);
    }
    file_put_contents($cleanPath, $b['content']);
    jsonResponse(['ok' => true]);
}

jsonError('Method not allowed', 405);
