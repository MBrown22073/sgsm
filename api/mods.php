<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
requireAuth();

$db       = new GSM_DB();
$method   = $_SERVER['REQUEST_METHOD'];
$serverId = isset($_GET['server_id']) ? (int)$_GET['server_id'] : 0;
$modDbId  = isset($_GET['id'])        ? (int)$_GET['id']        : 0;
$action   = $_GET['action'] ?? '';

if ($serverId && !$db->getServer($serverId)) jsonError('Server not found', 404);

// ── GET list ──────────────────────────────────────────────────────────────────
if ($method === 'GET' && $serverId && !$action) {
    jsonResponse($db->getMods($serverId));
}

// ── GET Steam Workshop lookup ─────────────────────────────────────────────────
// No API key needed — GetPublishedFileDetails is publicly accessible.
if ($method === 'GET' && $action === 'lookup') {
    $workshopId = trim($_GET['workshop_id'] ?? '');
    if (!$workshopId) jsonError('workshop_id is required');

    $postData = http_build_query(['itemcount' => 1, 'publishedfileids[0]' => $workshopId]);
    $raw      = false;
    $curlErr  = '';

    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $raw     = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);
    } else {
        // Fallback: file_get_contents with stream context
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => $postData,
            'timeout' => 10,
        ]]);
        $raw = @file_get_contents(
            'https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/',
            false, $ctx
        );
        if ($raw === false) $curlErr = 'file_get_contents failed — allow_url_fopen may be off';
    }

    if (!$raw) jsonError('Could not reach Steam API: ' . ($curlErr ?: 'empty response'));

    $data = json_decode($raw, true);
    if (!is_array($data)) jsonError('Steam API returned invalid JSON');

    $file   = $data['response']['publishedfiledetails'][0] ?? null;
    $result = (int)($file['result'] ?? 0);

    if (!$file)        jsonError('Steam API returned no details for that ID');
    if ($result === 9) jsonError('This item requires payment and cannot be looked up anonymously');
    if ($result !== 1) jsonError('Workshop item not found or is private (Steam result code: ' . $result . ')');

    jsonResponse([
        'mod_id'      => $workshopId,
        'name'        => $file['title'] ?? '',
        'description' => mb_strimwidth(strip_tags($file['description'] ?? ''), 0, 300, '…'),
        'preview_url' => $file['preview_url'] ?? '',
        'app_id'      => (string)($file['consumer_appid'] ?? ''),
        'source'      => 'steam',
        'workshop_url'=> 'https://steamcommunity.com/sharedfiles/filedetails/?id=' . $workshopId,
    ]);
}

// ── POST add mod ──────────────────────────────────────────────────────────────
if ($method === 'POST' && !$action) {
    if (!$serverId) jsonError('server_id is required');
    $b = getBody();
    $modId = trim($b['mod_id'] ?? '');
    if (!$modId) jsonError('mod_id is required');

    $mod = $db->upsertMod($serverId, $modId, [
        'name'        => trim($b['name'] ?? $modId),
        'description' => trim($b['description'] ?? ''),
        'preview_url' => trim($b['preview_url'] ?? ''),
        'source'      => trim($b['source'] ?? 'steam'),
        'status'      => 'pending',
    ]);

    // For Arma Reforger (Bohemia Workshop): sync mod list into config.json
    $server = $db->getServer($serverId);
    syncArmaConfig($db, $server);

    jsonResponse($mod, 201);
}

// ── POST install (Steam Workshop download via SteamCMD container) ────────────
if ($method === 'POST' && $action === 'install' && $modDbId) {
    $mod    = $db->getMod($modDbId);
    if (!$mod) jsonError('Mod not found', 404);
    if ($mod['source'] !== 'steam') jsonError('Only Steam Workshop mods can be installed this way');

    $server     = $db->getServer((int)$mod['server_id']);
    $appId      = $server['app_id'];
    $workshopId = $mod['mod_id'];

    $steamUser = $db->getSetting('steam_username') ?: '';
    $steamPass = $db->getSetting('steam_password') ?: '';
    $useLogin  = !empty($steamUser) && !empty($steamPass);
    $loginCmd  = $useLogin
        ? '+login ' . escapeshellarg($steamUser) . ' ' . escapeshellarg($steamPass)
        : '+login anonymous';

    $cname = 'gsm-mod-' . $modDbId;
    $d     = docker();
    $d->assertAvailable();

    // Remove any stale previous container
    $existing = $d->inspectContainer($cname);
    if ($existing !== null) $d->removeContainer($cname, true);

    $shCmd = "/home/steam/steamcmd/steamcmd.sh"
           . " $loginCmd"
           . " +workshop_download_item " . escapeshellarg($appId)
           . " " . escapeshellarg($workshopId)
           . " +quit";

    $hostInstallDir = hostPath(rtrim($server['install_dir'], '/'));

    $containerId = $d->createContainer($cname, [
        'Image'      => 'steamcmd/steamcmd:latest',
        'Entrypoint' => ['/bin/sh', '-c'],
        'Cmd'        => [$shCmd],
        'WorkingDir' => '/server',
        'HostConfig' => [
            'Binds'       => [$hostInstallDir . ':/server'],
            'NetworkMode' => 'bridge',
        ],
        'Labels' => ['gsm.mod_id' => (string)$modDbId],
    ]);
    $d->startContainer($containerId);

    $db->setModStatus($modDbId, 'installing');
    jsonResponse(['ok' => true, 'container_id' => $containerId]);
}

// ── GET mod install log ────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'log' && $modDbId) {
    $mod = $db->getMod($modDbId);
    if (!$mod) jsonError('Mod not found', 404);

    $offset = (int)($_GET['offset'] ?? 0);
    $cname  = 'gsm-mod-' . $modDbId;

    // Try to read from the Docker container logs first
    $containerInfo = docker()->inspectContainer($cname);
    if ($containerInfo !== null) {
        $since   = ($offset > 10000) ? $offset : null;
        $raw     = docker()->getLogs($cname, 500, $since);
        $lines   = array_values(array_filter(explode("\n", $raw), fn($l) => $l !== ''));
        $done    = false;
        if (!($containerInfo['State']['Running'] ?? true)) {
            // Container has exited — detect result and clean up
            $done   = true;
            $status = ($containerInfo['State']['ExitCode'] ?? 1) === 0 ? 'installed' : 'error';
            // Also check SteamCMD output text for errors even on exit code 0
            if ($status === 'installed' && str_contains($raw, 'ERROR!')) $status = 'error';
            $db->setModStatus($modDbId, $status);
            try { docker()->removeContainer($cname, true); } catch (RuntimeException) {}
            if ($status === 'installed') {
                $server = $db->getServer((int)$mod['server_id']);
                syncArmaConfig($db, $server);
            }
        }
        jsonResponse(['lines' => $lines, 'offset' => time(), 'done' => $done]);
    }

    // Fallback: file-based log (container already removed or pre-Docker install)
    $logFile = DATA_DIR . '/logs/mod-' . $modDbId . '.log';
    if (!file_exists($logFile)) jsonResponse(['lines' => [], 'offset' => 0, 'done' => false]);

    $content = file_get_contents($logFile, false, null, $offset);
    $lines   = $content === false ? [] : array_filter(explode("\n", $content), fn($l) => $l !== '');
    $newOff  = $offset + strlen((string)$content);

    $done = false;
    if (str_contains((string)$content, 'Success.') || str_contains((string)$content, 'ERROR!')) {
        $done   = true;
        $status = str_contains((string)$content, 'Success.') ? 'installed' : 'error';
        $db->setModStatus($modDbId, $status);
        if ($status === 'installed') {
            $server = $db->getServer((int)$mod['server_id']);
            syncArmaConfig($db, $server);
        }
    }

    jsonResponse(['lines' => array_values($lines), 'offset' => $newOff, 'done' => $done]);
}

// ── DELETE mod ────────────────────────────────────────────────────────────────
if ($method === 'DELETE' && $modDbId) {
    $mod = $db->getMod($modDbId);
    if (!$mod) jsonError('Not found', 404);
    $db->deleteMod($modDbId);

    // Sync Arma config after removal
    $server = $db->getServer((int)$mod['server_id']);
    syncArmaConfig($db, $server);

    jsonResponse(['ok' => true]);
}

jsonError('Bad request', 400);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * For servers with a -config <path> in their launch_args (e.g. Arma Reforger),
 * write the current mod list into config.json under game.mods.
 * Arma Reforger downloads mods automatically on startup from the Bohemia Workshop
 * when they're listed there.
 */
function syncArmaConfig(GSM_DB $db, ?array $server): void {
    if (!$server) return;
    $args = $server['launch_args'] ?? '';
    if (!preg_match('/-config\s+(\S+)/', $args, $m)) return;

    $cfgFile = $m[1];
    if (!file_exists($cfgFile)) return;

    $json = json_decode(file_get_contents($cfgFile), true);
    if (!is_array($json)) return;

    $mods = $db->getMods((int)$server['id']);
    $modsArr = array_values(array_map(fn($mod) => [
        'modId' => $mod['mod_id'],
        'name'  => $mod['name'],
    ], $mods));

    if (!isset($json['game'])) $json['game'] = [];
    $json['game']['mods'] = $modsArr;

    file_put_contents($cfgFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}
