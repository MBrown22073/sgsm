<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();

$db     = new GSM_DB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── Setup ───────────────────────────────────────────────────────────────────
if ($action === 'setup' && $method === 'POST') {
    if ($db->getSetting('setup_complete')) jsonError('Already configured', 400);
    $b = getBody();
    $username = trim($b['username'] ?? '');
    $password = $b['password'] ?? '';
    $appName  = trim($b['app_name'] ?? 'Game Server Manager');
    if (strlen($username) < 3) jsonError('Username must be at least 3 characters');
    if (strlen($password) < 8) jsonError('Password must be at least 8 characters');
    $db->setSetting('app_name',            $appName);
    $db->setSetting('admin_username',      $username);
    $db->setSetting('admin_password_hash', password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
    $db->setSetting('setup_complete',      'true');
    session_regenerate_id(true);
    $_SESSION['gsm_user'] = $username;
    jsonResponse(['ok' => true]);
}

// ── Login ───────────────────────────────────────────────────────────────────
if ($action === 'login' && $method === 'POST') {
    $b        = getBody();
    $username = trim($b['username'] ?? '');
    $password = $b['password'] ?? '';
    $stored   = $db->getSetting('admin_username');
    $hash     = $db->getSetting('admin_password_hash');
    if ($username !== $stored || !password_verify($password, $hash)) {
        jsonError('Invalid username or password', 401);
    }
    session_regenerate_id(true);
    $_SESSION['gsm_user'] = $username;
    jsonResponse(['ok' => true, 'username' => $username]);
}

// ── Logout ──────────────────────────────────────────────────────────────────
if ($action === 'logout') {
    session_destroy();
    header('Location: ../');
    exit;
}

// ── Change password ─────────────────────────────────────────────────────────
if ($action === 'change-password' && $method === 'POST') {
    requireAuth();
    $b       = getBody();
    $current = $b['current_password'] ?? '';
    $newpw   = $b['new_password'] ?? '';
    $hash    = $db->getSetting('admin_password_hash');
    if (!password_verify($current, $hash)) jsonError('Current password is incorrect', 401);
    if (strlen($newpw) < 8)               jsonError('New password must be at least 8 characters');
    $db->setSetting('admin_password_hash', password_hash($newpw, PASSWORD_BCRYPT, ['cost' => 12]));
    jsonResponse(['ok' => true]);
}

jsonError('Not found', 404);
