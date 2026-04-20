<?php

namespace KumaGames\GamePlayerManager\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Log;
use KumaGames\GamePlayerManager\Services\Nbt\NbtService;
use Throwable;

class MinecraftPlayerProvider implements GamePlayerService
{
    private NbtService $nbtService;

    /** @var array<string, array<string, string>> */
    private array $serverPropertiesCache = [];

    public function __construct()
    {
        $this->nbtService = new NbtService();
    }

    public function sendRconCommand(string $serverId, string $command): ?string
    {
        $server = $this->resolveServer($serverId, true);
        if (!$server) {
            Log::error("Server not found for UUID: {$serverId}");
            return null;
        }

        $response = $this->runRconCommand($server, $command);
        if ($response !== null) {
            return $response;
        }

        // Fallback for environments where the panel command proxy still works.
        try {
            $server->send($command);
            Log::info("Fallback server command sent to {$serverId}: {$command}");
            return 'Command sent';
        } catch (Throwable $e) {
            Log::error("Failed to send command to {$serverId}: {$e->getMessage()}");
            return null;
        }
    }

    public function getPlayers(string $serverId): array
    {
        $server = $this->resolveServer($serverId, true);
        if (!$server) {
            return [];
        }

        $allPlayers = [];
        $fileRepository = $this->getFileRepository($server);

        $opNames = $this->readNamedList($fileRepository, 'ops.json');
        foreach ($opNames as $lowerName => $name) {
            $allPlayers[$lowerName] = [
                'id' => $name,
                'name' => $name,
                'online' => false,
                'is_op' => true,
                'is_banned' => false,
            ];
        }

        $bannedNames = $this->readNamedList($fileRepository, 'banned-players.json');
        foreach ($bannedNames as $lowerName => $name) {
            if (!isset($allPlayers[$lowerName])) {
                $allPlayers[$lowerName] = [
                    'id' => $name,
                    'name' => $name,
                    'online' => false,
                    'is_op' => isset($opNames[$lowerName]),
                    'is_banned' => true,
                ];
                continue;
            }

            $allPlayers[$lowerName]['is_banned'] = true;
        }

        $cachedPlayers = $this->readJsonFile($fileRepository, 'usercache.json');
        foreach ($cachedPlayers as $player) {
            $name = $player['name'] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }

            $lowerName = strtolower($name);
            if (isset($allPlayers[$lowerName])) {
                continue;
            }

            $allPlayers[$lowerName] = [
                'id' => $name,
                'name' => $name,
                'online' => false,
                'is_op' => isset($opNames[$lowerName]),
                'is_banned' => isset($bannedNames[$lowerName]),
            ];
        }

        $onlinePlayers = $this->getOnlinePlayers($server);
        foreach ($onlinePlayers as $playerName) {
            $lowerName = strtolower($playerName);

            if (!isset($allPlayers[$lowerName])) {
                $allPlayers[$lowerName] = [
                    'id' => $playerName,
                    'name' => $playerName,
                    'online' => true,
                    'is_op' => isset($opNames[$lowerName]),
                    'is_banned' => isset($bannedNames[$lowerName]),
                ];
                continue;
            }

            $allPlayers[$lowerName]['online'] = true;
        }

        $players = array_values($allPlayers);
        usort($players, fn (array $a, array $b) => strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? '')));

        return $players;
    }

    public function getPlayerDetails(string $serverId, string $playerId): array
    {
        $server = $this->resolveServer($serverId, true);
        if (!$server) {
            return [];
        }

        $fileRepository = $this->getFileRepository($server);
        $uuid = $this->resolveUuidFromUsercache($fileRepository, $playerId);
        $lowerPlayerId = strtolower($playerId);

        $opNames = $this->readNamedList($fileRepository, 'ops.json');
        $bannedNames = $this->readNamedList($fileRepository, 'banned-players.json');

        $onlinePlayers = array_map('strtolower', $this->getOnlinePlayers($server));
        $isOnline = in_array($lowerPlayerId, $onlinePlayers, true);

        $details = [
            'id' => $playerId,
            'name' => $playerId,
            'uuid' => $uuid ?? 'Unknown',
            'status' => $isOnline ? 'Online' : 'Offline',
            'is_op' => isset($opNames[$lowerPlayerId]),
            'is_banned' => isset($bannedNames[$lowerPlayerId]),
            'raw_stats' => $isOnline ? 'Online' : 'Offline',
            'health' => null,
            'food' => null,
            'level' => null,
            'gamemode' => null,
            'inventory_data' => [],
            'inventory_summary' => null,
            'play_time' => null,
            'walk_distance' => null,
            'mobs_killed' => null,
            'deaths' => null,
        ];

        if ($this->isRconEnabled()) {
            $liveDetails = $this->getLiveDetailsViaRcon($server, $playerId);
            if ($liveDetails !== null) {
                $details = array_merge($details, $liveDetails);
            }
        } else {
            $details['raw_stats'] = $isOnline ? 'Online (RCON disabled)' : 'Offline';
        }

        if (($details['status'] ?? 'Offline') !== 'Online') {
            $nbtDetails = $this->getOfflineDetailsFromNbt($server, $uuid ?? $playerId);
            if (!empty($nbtDetails)) {
                $details = array_merge($details, $nbtDetails);
                $details['raw_stats'] = $this->isRconEnabled()
                    ? 'Offline (Data from Save File)'
                    : 'RconDisabled';
            }
        }

        $this->fillStatsFromJson($fileRepository, $server, $uuid, $details);

        return $details;
    }

    public function kick(string $serverId, string $playerId, string $reason = ''): bool
    {
        $cmd = trim("kick {$playerId} {$reason}");
        return $this->sendRconCommand($serverId, $cmd) !== null;
    }

    public function ban(string $serverId, string $playerId, string $reason = ''): bool
    {
        $cmd = "ban {$playerId}";
        if ($reason !== '') {
            $cmd .= " {$reason}";
        }

        return $this->sendRconCommand($serverId, $cmd) !== null;
    }

    public function pardon(string $serverId, string $playerId): bool
    {
        return $this->sendRconCommand($serverId, "pardon {$playerId}") !== null;
    }

    public function op(string $serverId, string $playerId): bool
    {
        return $this->sendRconCommand($serverId, "op {$playerId}") !== null;
    }

    public function deop(string $serverId, string $playerId): bool
    {
        return $this->sendRconCommand($serverId, "deop {$playerId}") !== null;
    }

    public function clearInventory(string $serverId, string $playerId): bool
    {
        return $this->sendRconCommand($serverId, "clear {$playerId}") !== null;
    }

    public function getServerProperties(string $serverId): array
    {
        $server = $this->resolveServer($serverId, true);
        if (!$server) {
            return [];
        }

        $rawProps = $this->getServerPropertiesFromFile($server);
        $maxPlayers = isset($rawProps['max-players']) ? (int) $rawProps['max-players'] : 20;
        $motdRaw = trim((string) ($rawProps['motd'] ?? 'A Minecraft Server'));
        $levelName = trim((string) ($rawProps['level-name'] ?? 'world'));

        return [
            'max_players' => $maxPlayers,
            'motd' => $this->decodeMotd($motdRaw),
            'level_name' => $levelName !== '' ? $levelName : 'world',
        ];
    }

    private function getOnlinePlayers(Server $server): array
    {
        $onlineByRcon = [];
        if ($this->isRconEnabled()) {
            $onlineByRcon = $this->getOnlinePlayersViaRcon($server);
        }

        if (!empty($onlineByRcon)) {
            return $onlineByRcon;
        }

        return $this->getOnlinePlayersViaQuery($server);
    }

    private function getOnlinePlayersViaRcon(Server $server): array
    {
        $response = $this->runRconCommand($server, 'list');
        if ($response === null || trim($response) === '') {
            return [];
        }

        if (!str_contains($response, ':')) {
            return [];
        }

        $playerListString = trim((string) substr(strrchr($response, ':'), 1));
        if ($playerListString === '') {
            return [];
        }

        $players = [];
        foreach (explode(',', $playerListString) as $name) {
            $clean = trim((string) preg_replace('/§[0-9A-FK-OR]/i', '', $name));
            if ($clean !== '') {
                $players[] = $clean;
            }
        }

        return array_values(array_unique($players));
    }

    private function getOnlinePlayersViaQuery(Server $server): array
    {
        $players = [];

        try {
            if (!$server->relationLoaded('allocation')) {
                $server->load('allocation');
            }

            if (!$server->allocation) {
                return [];
            }

            $ip = $server->allocation->alias ?: $server->allocation->ip;
            $port = (int) $server->allocation->port;

            if (empty($ip) || $port <= 0) {
                return [];
            }

            $socket = @fsockopen("udp://{$ip}", $port, $errno, $errstr, 2);
            if (!$socket) {
                return [];
            }

            stream_set_timeout($socket, 2);

            $sessionId = random_int(1, 99999999) & 0x0F0F0F0F;
            $challengeRequest = pack('c', 0xFE) . pack('c', 0xFD) . pack('c', 0x09) . pack('N', $sessionId);
            fwrite($socket, $challengeRequest);

            $challengeResponse = fread($socket, 4096);
            if (!$challengeResponse || strlen($challengeResponse) < 6) {
                fclose($socket);
                return [];
            }

            $challenge = (int) trim((string) substr($challengeResponse, 5));

            $statRequest = pack('c', 0xFE) . pack('c', 0xFD) . pack('c', 0x00)
                . pack('N', $sessionId)
                . pack('N', $challenge)
                . pack('N', 0x00);

            fwrite($socket, $statRequest);
            $fullResponse = fread($socket, 4096);
            fclose($socket);

            if (!$fullResponse || strlen($fullResponse) < 17) {
                return [];
            }

            $body = substr($fullResponse, 16);
            $split = explode("\x00\x01player_\x00\x00", $body);
            if (count($split) < 2) {
                return [];
            }

            foreach (explode("\x00", $split[1]) as $playerName) {
                $playerName = trim($playerName);
                if ($playerName !== '') {
                    $players[] = $playerName;
                }
            }
        } catch (Throwable $e) {
            // Query can fail depending on firewall/network settings.
            Log::debug('Minecraft query failed: ' . $e->getMessage());
        }

        return array_values(array_unique($players));
    }

    private function getLiveDetailsViaRcon(Server $server, string $playerId): ?array
    {
        $result = $this->withRcon($server, function (RconService $rcon) use ($playerId) {
            $healthResponse = $rcon->sendCommand("data get entity {$playerId} Health");
            if ($healthResponse === null) {
                return null;
            }

            if ($this->isNoEntityResponse($healthResponse)) {
                return [
                    'status' => 'Offline',
                    'raw_stats' => 'Player is Offline',
                ];
            }

            $details = [
                'status' => 'Online',
                'raw_stats' => 'Live RCON',
            ];

            $details['health'] = $this->extractFloatFromRconResponse($healthResponse);

            $foodResponse = $rcon->sendCommand("data get entity {$playerId} foodLevel");
            if ($foodResponse === null || $this->isNoEntityResponse($foodResponse)) {
                $foodResponse = $rcon->sendCommand("data get entity {$playerId} FoodLevel");
            }
            $details['food'] = $this->extractIntFromRconResponse($foodResponse);

            $levelResponse = $rcon->sendCommand("data get entity {$playerId} XpLevel");
            if ($levelResponse === null || $this->isNoEntityResponse($levelResponse)) {
                $levelResponse = $rcon->sendCommand("data get entity {$playerId} xpLevel");
            }
            $details['level'] = $this->extractIntFromRconResponse($levelResponse);

            $gamemodeResponse = $rcon->sendCommand("data get entity {$playerId} playerGameType");
            $gamemodeValue = $this->extractIntFromRconResponse($gamemodeResponse);
            if ($gamemodeValue !== null) {
                $gamemodeById = [
                    0 => 'Survival',
                    1 => 'Creative',
                    2 => 'Adventure',
                    3 => 'Spectator',
                ];
                $details['gamemode'] = $gamemodeById[$gamemodeValue] ?? 'Unknown';
            }

            $details['inventory_data'] = $this->fetchInventoryViaRcon($rcon, $playerId);

            return $details;
        });

        return is_array($result) ? $result : null;
    }

    private function fetchInventoryViaRcon(RconService $rcon, string $playerId): array
    {
        $inventory = [];

        for ($i = 0; $i < 50; $i++) {
            $itemResponse = $rcon->sendCommand("data get entity {$playerId} Inventory[{$i}]");

            if ($itemResponse === null || $this->isNoEntityResponse($itemResponse)) {
                break;
            }

            if (str_contains($itemResponse, 'No such element') || str_contains($itemResponse, 'Found no elements')) {
                break;
            }

            $itemString = $itemResponse;
            $dataPos = strpos($itemResponse, 'data: ');
            if ($dataPos !== false) {
                $itemString = substr($itemResponse, $dataPos + 6);
            }

            $item = [];

            if (preg_match('/id:\s*"?(?:minecraft:)?([^",\}\s]+)"?/i', $itemString, $idMatch)) {
                $item['id'] = trim($idMatch[1]);
            }

            if (preg_match('/Count:\s*(-?\d+)b?/i', $itemString, $countMatch)) {
                $item['count'] = (int) $countMatch[1];
            } else {
                $item['count'] = 1;
            }

            if (preg_match('/Slot:\s*(-?\d+)b?/i', $itemString, $slotMatch)) {
                $item['slot'] = (int) $slotMatch[1];
            }

            if (isset($item['id'], $item['slot'])) {
                $inventory[] = $item;
            }
        }

        return $inventory;
    }

    private function runRconCommand(Server $server, string $command): ?string
    {
        $result = $this->withRcon($server, function (RconService $rcon) use ($command) {
            $response = $rcon->sendCommand($command);
            return $response ?? '';
        });

        if ($result === null) {
            return null;
        }

        return (string) $result;
    }

    private function withRcon(Server $server, callable $callback)
    {
        if (!$this->isRconEnabled()) {
            return null;
        }

        $config = $this->getRconConfig($server);
        if ((int) $config['port'] <= 0 || trim((string) $config['password']) === '') {
            Log::warning('RCON config is incomplete (missing port/password).');
            return null;
        }

        if ($config['server_rcon_enabled'] === false) {
            Log::warning('RCON disabled in server.properties (enable-rcon=false).');
            return null;
        }

        $hosts = $this->getRconHostsToTry($server, $config);
        $lastError = '';

        foreach ($hosts as $host) {
            $rcon = new RconService($host, (int) $config['port'], (string) $config['password'], 3);
            if (!$rcon->connect()) {
                $lastError = $rcon->getLastError();
                continue;
            }

            try {
                return $callback($rcon);
            } catch (Throwable $e) {
                Log::error("RCON command failed on {$host}: {$e->getMessage()}");
                return null;
            } finally {
                $rcon->disconnect();
            }
        }

        if ($lastError !== '') {
            Log::error('All RCON hosts failed: ' . $lastError);
        }

        return null;
    }

    private function getRconConfig(Server $server): array
    {
        $properties = $this->getServerPropertiesFromFile($server);

        $configuredHost = trim((string) env('MC_PLAYER_MANAGER_RCON_HOST', ''));
        $configuredPort = (int) env('MC_PLAYER_MANAGER_RCON_PORT', 0);
        $configuredPassword = trim((string) env('MC_PLAYER_MANAGER_RCON_PASSWORD', ''));

        $host = $configuredHost;
        if ($host === '') {
            $host = (string) ($server->allocation->alias ?: $server->allocation->ip ?: '127.0.0.1');
        }

        $portFromProperties = isset($properties['rcon.port']) ? (int) $properties['rcon.port'] : 0;
        $port = $configuredPort > 0 ? $configuredPort : ($portFromProperties > 0 ? $portFromProperties : 25575);

        $password = $configuredPassword;
        if ($password === '') {
            $password = trim((string) ($properties['rcon.password'] ?? ''));
        }

        $serverRconEnabled = null;
        if (isset($properties['enable-rcon'])) {
            $serverRconEnabled = strtolower(trim((string) $properties['enable-rcon'])) === 'true';
        }

        return [
            'host' => $host,
            'configured_host' => $configuredHost !== '',
            'port' => $port,
            'password' => $password,
            'server_rcon_enabled' => $serverRconEnabled,
        ];
    }

    /**
     * @param array{host:string, configured_host:bool, port:int, password:string, server_rcon_enabled:?bool} $config
     * @return array<int, string>
     */
    private function getRconHostsToTry(Server $server, array $config): array
    {
        if ($config['configured_host']) {
            return [$config['host']];
        }

        $hosts = [
            $config['host'],
            $server->allocation->alias ?? null,
            $server->allocation->ip ?? null,
            '127.0.0.1',
            'localhost',
        ];

        return array_values(array_unique(array_filter($hosts, static function ($host) {
            return is_string($host) && trim($host) !== '';
        })));
    }

    private function isRconEnabled(): bool
    {
        $value = env('MC_PLAYER_MANAGER_RCON_ENABLED', false);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function resolveServer(string $serverId, bool $withAllocation = false): ?Server
    {
        $query = Server::query();

        if ($withAllocation) {
            $query->with('allocation');
        }

        return $query->where('uuid', $serverId)->orWhere('uuid_short', $serverId)->first();
    }

    private function getFileRepository(Server $server)
    {
        /** @var \App\Repositories\Daemon\DaemonFileRepository $fileRepository */
        $fileRepository = app(\App\Repositories\Daemon\DaemonFileRepository::class);
        $fileRepository->setServer($server);

        return $fileRepository;
    }

    /**
     * @return array<string, string>
     */
    private function readNamedList($fileRepository, string $path): array
    {
        $entries = $this->readJsonFile($fileRepository, $path);
        $names = [];

        foreach ($entries as $entry) {
            $name = $entry['name'] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }

            $names[strtolower($name)] = $name;
        }

        return $names;
    }

    private function resolveUuidFromUsercache($fileRepository, string $playerId): ?string
    {
        $entries = $this->readJsonFile($fileRepository, 'usercache.json');
        $lowerPlayerId = strtolower($playerId);

        foreach ($entries as $entry) {
            $name = $entry['name'] ?? null;
            $uuid = $entry['uuid'] ?? null;

            if (!is_string($name) || !is_string($uuid)) {
                continue;
            }

            if (strtolower($name) === $lowerPlayerId) {
                return $uuid;
            }
        }

        return null;
    }

    private function fillStatsFromJson($fileRepository, Server $server, ?string $uuid, array &$details): void
    {
        if (!$uuid) {
            return;
        }

        $properties = $this->getServerPropertiesFromFile($server);
        $levelName = trim((string) ($properties['level-name'] ?? 'world'));
        if ($levelName === '') {
            $levelName = 'world';
        }

        $statsPath = "{$levelName}/stats/{$uuid}.json";
        $statsJson = $this->readJsonFile($fileRepository, $statsPath);
        if (empty($statsJson['stats']) || !is_array($statsJson['stats'])) {
            return;
        }

        $custom = $statsJson['stats']['minecraft:custom'] ?? [];
        if (!is_array($custom)) {
            return;
        }

        $ticks = (int) ($custom['minecraft:play_time'] ?? 0);
        $details['play_time'] = floor($ticks / 20 / 60) . ' ' . __('minecraft-player-manager::messages.units.mins');
        $details['mobs_killed'] = (int) ($custom['minecraft:mob_kills'] ?? 0);
        $details['deaths'] = (int) ($custom['minecraft:deaths'] ?? 0);

        $walkCm = (int) ($custom['minecraft:walk_one_cm'] ?? 0);
        $details['walk_distance'] = floor($walkCm / 100) . ' m';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readJsonFile($fileRepository, string $path): array
    {
        try {
            $content = $fileRepository->getContent($path);
            $decoded = json_decode($content, true);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array<string, string>
     */
    private function getServerPropertiesFromFile(Server $server): array
    {
        $cacheKey = (string) ($server->uuid ?? spl_object_hash($server));

        if (isset($this->serverPropertiesCache[$cacheKey])) {
            return $this->serverPropertiesCache[$cacheKey];
        }

        $properties = [];

        try {
            $content = $this->getFileRepository($server)->getContent('server.properties');
            $properties = $this->parseServerProperties($content);
        } catch (Throwable $e) {
            // Keep defaults when server.properties is unavailable.
        }

        $this->serverPropertiesCache[$cacheKey] = $properties;

        return $properties;
    }

    /**
     * @return array<string, string>
     */
    private function parseServerProperties(string $content): array
    {
        $properties = [];

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (!is_string($line)) {
                continue;
            }

            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $properties[trim($key)] = trim($value);
        }

        return $properties;
    }

    private function decodeMotd(string $motd): string
    {
        if ($motd === '') {
            return 'A Minecraft Server';
        }

        $decoded = json_decode('"' . addslashes($motd) . '"');
        return is_string($decoded) ? $decoded : $motd;
    }

    private function extractIntFromRconResponse(?string $response): ?int
    {
        if (!is_string($response) || $this->isNoEntityResponse($response)) {
            return null;
        }

        if (!preg_match('/(-?\d+)(?:[bBsSfFdDlL])?$/', trim($response), $match)) {
            return null;
        }

        return (int) $match[1];
    }

    private function extractFloatFromRconResponse(?string $response): ?float
    {
        if (!is_string($response) || $this->isNoEntityResponse($response)) {
            return null;
        }

        if (!preg_match('/(-?\d+(?:\.\d+)?)(?:[bBsSfFdDlL])?$/', trim($response), $match)) {
            return null;
        }

        return (float) $match[1];
    }

    private function isNoEntityResponse(?string $response): bool
    {
        if (!is_string($response) || $response === '') {
            return true;
        }

        return str_contains($response, 'No entity')
            || str_contains($response, 'No player')
            || str_contains($response, 'No entity was found')
            || str_contains($response, 'Found no elements');
    }

    private function getOfflineDetailsFromNbt(Server $server, string $uuid): ?array
    {
        if ($uuid === '' || $uuid === 'Unknown') {
            return null;
        }

        $properties = $this->getServerPropertiesFromFile($server);
        $levelName = trim((string) ($properties['level-name'] ?? 'world'));
        if ($levelName === '') {
            $levelName = 'world';
        }

        $path = "{$levelName}/playerdata/{$uuid}.dat";

        try {
            $content = $this->getFileRepository($server)->getContent($path);
            $data = $this->nbtService->parseString($content);

            if (empty($data) || !is_array($data)) {
                return null;
            }

            $result = [];

            if (isset($data['Health'])) {
                $result['health'] = (float) $data['Health'];
            }

            if (isset($data['foodLevel'])) {
                $result['food'] = (int) $data['foodLevel'];
            }

            if (isset($data['XpLevel'])) {
                $result['level'] = (int) $data['XpLevel'];
            }

            if (isset($data['playerGameType'])) {
                $gamemodeById = [
                    0 => 'Survival',
                    1 => 'Creative',
                    2 => 'Adventure',
                    3 => 'Spectator',
                ];
                $result['gamemode'] = $gamemodeById[(int) $data['playerGameType']] ?? 'Unknown';
            }

            if (isset($data['Inventory']) && is_array($data['Inventory'])) {
                $inventory = [];

                foreach ($data['Inventory'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $slot = $item['Slot'] ?? $item['slot'] ?? null;
                    $id = $item['id'] ?? $item['Id'] ?? null;
                    $count = $item['Count'] ?? $item['count'] ?? 1;

                    if (!is_string($id) || $id === '' || $slot === null) {
                        continue;
                    }

                    $inventory[] = [
                        'slot' => (int) $slot,
                        'id' => $id,
                        'count' => (int) $count,
                    ];
                }

                $result['inventory_data'] = $inventory;
            }

            return $result;
        } catch (Throwable $e) {
            return null;
        }
    }
}
