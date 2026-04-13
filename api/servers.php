<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
requireAuth();

$db     = new GSM_DB();
$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

// ── Templates ───────────────────────────────────────────────────────────────
if (isset($_GET['templates'])) {
    jsonResponse([
        ['name' => 'Counter-Strike 2',        'app_id' => '730',     'launch_executable' => './game/bin/linuxsteamrt64/cs2',   'launch_args' => '-dedicated +map de_dust2 +maxplayers 10',                                                            'port' => 27015, 'max_players' => 10 ],
        ['name' => 'Counter-Strike: GO',       'app_id' => '740',     'launch_executable' => './srcds_run',                     'launch_args' => '-game csgo +maxplayers 10 +map de_dust2',                                                             'port' => 27015, 'max_players' => 10 ],
        ['name' => 'Valheim',                  'app_id' => '896660',  'launch_executable' => './valheim_server.x86_64',          'launch_args' => "-name 'Valheim' -port 2456 -world 'World' -password 'changeme'",                                      'port' => 2456,  'max_players' => 10 ],
        ['name' => 'Rust',                     'app_id' => '258550',  'launch_executable' => './RustDedicated',                  'launch_args' => '-batchmode +server.port 28015 +server.hostname "Rust Server"',                                        'port' => 28015, 'max_players' => 50 ],
        ['name' => 'ARK: Survival Evolved',    'app_id' => '376030',  'launch_executable' => './ShooterGameServer',              'launch_args' => 'TheIsland?SessionName=ARK?Port=7777?QueryPort=27015 -server -log',                                    'port' => 7777,  'max_players' => 70 ],
        ['name' => 'ARK: Survival Ascended',   'app_id' => '2430930', 'launch_executable' => './ArkAscendedServer.sh',           'launch_args' => 'TheIsland_WP?SessionName=ASA?Port=7777?QueryPort=27015 -server -log',                                'port' => 7777,  'max_players' => 70 ],
        ['name' => "Garry's Mod",              'app_id' => '4020',    'launch_executable' => './srcds_run',                     'launch_args' => '-game garrysmod +maxplayers 16 +map gm_flatgrass',                                                   'port' => 27015, 'max_players' => 16 ],
        ['name' => 'Team Fortress 2',          'app_id' => '232250',  'launch_executable' => './srcds_run',                     'launch_args' => '-game tf +maxplayers 24 +map ctf_2fort',                                                              'port' => 27015, 'max_players' => 24 ],
        ['name' => 'Left 4 Dead 2',            'app_id' => '222860',  'launch_executable' => './srcds_run',                     'launch_args' => '-game left4dead2 +map l4d2_c1m1_hotel +maxplayers 8',                                                 'port' => 27015, 'max_players' => 8  ],
        ['name' => '7 Days to Die',            'app_id' => '294420',  'launch_executable' => './7DaysToDieServer.x86_64',        'launch_args' => '-configfile=serverconfig.xml -dedicated -nographics',                                                 'port' => 26900, 'max_players' => 8  ],
        ['name' => 'Project Zomboid',          'app_id' => '380870',  'launch_executable' => './start-server.sh',                'launch_args' => '',                                                                                                    'port' => 16261, 'max_players' => 32 ],
        ['name' => 'Terraria (TShock)',        'app_id' => '105600',  'launch_executable' => './TerrariaServer',                 'launch_args' => '-port 7777 -maxplayers 8',                                                                            'port' => 7777,  'max_players' => 8  ],
        ['name' => 'DayZ',                     'app_id' => '223350',  'launch_executable' => './DayZServer',                    'launch_args' => '-config=serverDZ.cfg -port=2302 -BEpath=battleye',                                                    'port' => 2302,  'max_players' => 60 ],
        ['name' => 'Satisfactory',             'app_id' => '1690800', 'launch_executable' => './FactoryServer.sh',               'launch_args' => '-multihome=0.0.0.0',                                                                                  'port' => 7777,  'max_players' => 4  ],
        ['name' => 'Palworld',                 'app_id' => '2394010', 'launch_executable' => './PalServer.sh',                  'launch_args' => 'EpicApp=PalServer',                                                                                   'port' => 8211,  'max_players' => 32 ],
        ['name' => 'Enshrouded',               'app_id' => '2278520', 'launch_executable' => './enshrouded_server',              'launch_args' => '',                                                                                                    'port' => 15636, 'max_players' => 16 ],
        ['name' => 'Arma Reforger',            'app_id' => '1874900', 'launch_executable' => './ArmaReforgerServer',             'launch_args' => '-config {INSTALL_DIR}/config.json -profile {INSTALL_DIR}/profile -maxFPS 30 -nothrow',                 'port' => 2001,  'max_players' => 32 ],
        ['name' => 'Minecraft (Bedrock)',       'app_id' => '1944420', 'launch_executable' => './bedrock_server',                'launch_args' => '',                                                                                                    'port' => 19132, 'max_players' => 20 ],
        ['name' => 'Space Engineers',          'app_id' => '298740',  'launch_executable' => './SpaceEngineersDedicated.exe',    'launch_args' => '-console -noconsole',                                                                                 'port' => 27016, 'max_players' => 16 ],
        ['name' => 'Squad',                    'app_id' => '403240',  'launch_executable' => './SquadGameServer.sh',             'launch_args' => 'SquadGame -log -Port=7787 -QueryPort=27165',                                                          'port' => 7787,  'max_players' => 100],
        ['name' => 'Conan Exiles',             'app_id' => '443030',  'launch_executable' => './ConanSandboxServer.sh',          'launch_args' => '-MaxPlayers=40 -Port=7777 -QueryPort=27015',                                                          'port' => 7777,  'max_players' => 40 ],
        ['name' => 'The Forest',               'app_id' => '556450',  'launch_executable' => './TheForestDedicatedServer',       'launch_args' => '-serverip 0.0.0.0 -serverport 27015 -serverplayers 8 -servername "The Forest"',                       'port' => 27015, 'max_players' => 8  ],
        ['name' => 'Sons of the Forest',       'app_id' => '1326470', 'launch_executable' => './SonsOfTheForestDS',              'launch_args' => '',                                                                                                    'port' => 8766,  'max_players' => 8  ],
        ['name' => 'Killing Floor 2',          'app_id' => '232130',  'launch_executable' => './KFGameSteamServer.sh',           'launch_args' => 'KF-BioticsLab?Difficulty=0?GameLength=1 -Port=7777',                                                  'port' => 7777,  'max_players' => 6  ],
        ['name' => 'V Rising',                 'app_id' => '1829350', 'launch_executable' => './VRisingServer',                  'launch_args' => '-persistentDataPath ./save-data',                                                                     'port' => 9876,  'max_players' => 40 ],
    ]);
}

// ── Sync helper ─────────────────────────────────────────────────────────────
function syncStatus(array &$s): void {
    global $db;
    if (in_array($s['status'], ['running', 'installing']) && $s['pid']) {
        if (!isProcessRunning((int)$s['pid'])) {
            $newStatus = ($s['status'] === 'installing') ? 'stopped' : 'stopped';
            $db->updateServer((int)$s['id'], ['status' => $newStatus, 'pid' => null]);
            $s['status'] = $newStatus;
            $s['pid']    = null;
        }
    }
}

// ── GET list / single ────────────────────────────────────────────────────────
if ($method === 'GET' && $id === 0 && $action === '') {
    $servers = $db->getServers();
    foreach ($servers as &$s) syncStatus($s);
    jsonResponse($servers);
}

if ($method === 'GET' && $id > 0) {
    $s = $db->getServer($id);
    if (!$s) jsonError('Not found', 404);
    syncStatus($s);
    jsonResponse($s);
}

// ── GET stats ────────────────────────────────────────────────────────────────
if (isset($_GET['stats'])) {
    $servers  = $db->getServers();
    $total    = count($servers);
    $running  = 0; $stopped = 0; $installing = 0;
    foreach ($servers as $s) {
        syncStatus($s);
        match ($s['status']) {
            'running'    => $running++,
            'installing' => $installing++,
            default      => $stopped++,
        };
    }
    jsonResponse(compact('total', 'running', 'stopped', 'installing'));
}

// ── POST create ──────────────────────────────────────────────────────────────
if ($method === 'POST' && $id === 0 && $action === '') {
    $b = getBody();
    if (empty(trim($b['name'] ?? '')))       jsonError('Server name is required');
    if (empty(trim($b['app_id'] ?? '')))     jsonError('Steam App ID is required');
    if (!ctype_digit($b['app_id']))          jsonError('App ID must be numeric');
    if (empty(trim($b['install_dir'] ?? ''))) jsonError('Install directory is required');
    $newServer = $db->createServer($b);

    // Pre-create config.json immediately so the setup modal can load and edit it
    $args = $newServer['launch_args'] ?? '';
    if (preg_match('/-config\s+(\S+)/', $args, $cfgM)) {
        $cfgFile = $cfgM[1];
        if (!file_exists($cfgFile)) {
            $cfgDir = dirname($cfgFile);
            if (!is_dir($cfgDir)) mkdir($cfgDir, 0755, true);
            $port = (int)($newServer['port'] ?? 2001);
            $maxP = (int)($newServer['max_players'] ?? 32);
            file_put_contents($cfgFile, json_encode([
                'dedicatedServerId'              => '',
                'region'                         => 'EU',
                'gameHostBindAddress'             => '',
                'gameHostBindPort'                => $port,
                'gameHostRegisterBindAddress'     => '',
                'gameHostRegisterPort'            => $port,
                'adminPassword'                  => 'changeme',
                'game' => [
                    'name'                       => $newServer['name'],
                    'password'                   => '',
                    'scenarioId'                 => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf',
                    'maxPlayers'                 => $maxP,
                    'visible'                    => true,
                    'supportedGameClientTypes'   => ['PLATFORM_PC'],
                ],
            ], JSON_PRETTY_PRINT) . "\n");
        }
    }
    // Pre-create profile directory
    if (preg_match('/-profile\s+(\S+)/', $args, $profM) && !is_dir($profM[1])) {
        mkdir($profM[1], 0755, true);
    }

    jsonResponse($newServer, 201);
}

// ── PUT update ───────────────────────────────────────────────────────────────
if ($method === 'PUT' && $id > 0) {
    if (!$db->getServer($id)) jsonError('Not found', 404);
    jsonResponse($db->updateServer($id, getBody()));
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($method === 'DELETE' && $id > 0) {
    $s = $db->getServer($id);
    if (!$s) jsonError('Not found', 404);
    if (in_array($s['status'], ['running', 'installing'])) jsonError('Stop the server before deleting it');
    $db->deleteServer($id);
    // Delete game files from disk if the install directory exists
    $installDir = $s['install_dir'] ?? '';
    if ($installDir && is_dir($installDir)) {
        shell_exec('rm -rf ' . escapeshellarg($installDir));
    }
    // Remove logs for this server
    @unlink(DATA_DIR . '/logs/server-' . $id . '.log');
    @unlink(DATA_DIR . '/logs/install-' . $id . '.log');
    jsonResponse(['ok' => true]);
}

// ── Actions ──────────────────────────────────────────────────────────────────
if ($method === 'POST' && $id > 0 && $action !== '') {
    $s = $db->getServer($id);
    if (!$s) jsonError('Not found', 404);
    syncStatus($s);

    switch ($action) {

        case 'start':
            if ($s['status'] === 'running') jsonError('Server is already running');
            try {
                $pid = startServer($s);
                $db->updateServer($id, ['status' => 'running', 'pid' => $pid]);
                jsonResponse(['ok' => true, 'pid' => $pid]);
            } catch (RuntimeException $e) {
                jsonError($e->getMessage());
            }

        case 'stop':
            if ($s['status'] !== 'running') jsonError('Server is not running');
            if ($s['pid']) killProcess((int)$s['pid']);
            $db->updateServer($id, ['status' => 'stopped', 'pid' => null]);
            jsonResponse(['ok' => true]);

        case 'install':
            if ($s['status'] === 'running')    jsonError('Stop the server before installing');
            if ($s['status'] === 'installing') jsonError('Installation already in progress');
            $steamcmd = $db->getSetting('steamcmd_path') ?: '/opt/steamcmd/steamcmd.sh';
            try {
                $pid = installServer($s, $steamcmd);
                $db->updateServer($id, ['status' => 'installing', 'pid' => $pid]);
                jsonResponse(['ok' => true, 'pid' => $pid]);
            } catch (RuntimeException $e) {
                jsonError($e->getMessage());
            }

        case 'cancel-install':
            if ($s['status'] !== 'installing') jsonError('No active installation');
            if ($s['pid']) killProcess((int)$s['pid']);
            $db->updateServer($id, ['status' => 'stopped', 'pid' => null]);
            jsonResponse(['ok' => true]);

        case 'restart':
            if ($s['pid']) killProcess((int)$s['pid']);
            sleep(1);
            try {
                $pid = startServer($s);
                $db->updateServer($id, ['status' => 'running', 'pid' => $pid]);
                jsonResponse(['ok' => true, 'pid' => $pid]);
            } catch (RuntimeException $e) {
                $db->updateServer($id, ['status' => 'stopped', 'pid' => null]);
                jsonError($e->getMessage());
            }

        default:
            jsonError("Unknown action: $action", 404);
    }
}

jsonError('Not found', 404);
