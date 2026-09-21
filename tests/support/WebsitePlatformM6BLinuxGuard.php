<?php

declare(strict_types=1);

/** Test-launcher policy only. No application bootstrap, connection, credentials or Docker invocation. */
function m6linuxRequire(bool $condition, string $code): void
{
    if (!$condition) throw new RuntimeException('M6B guard: ' . $code);
}
/** Keep JSON objects distinct from lists in the two inspected mount-list fields. */
function m6linuxDecodeInspection(string $input, bool $engine = false): array
{
    $typed = json_decode($input, false, 32, JSON_THROW_ON_ERROR);
    $data = json_decode($input, true, 32, JSON_THROW_ON_ERROR);
    m6linuxRequire($engine ? $typed instanceof stdClass : is_array($typed), 'inspection_shape');
    // Engine metadata is an object; container/image inspection is a list of objects.
    // Associative decoding alone collapses {} and numeric-key objects into PHP lists.
    if (is_array($typed)) foreach ($typed as $index => $row) {
        m6linuxRequire($row instanceof stdClass, 'inspection_shape');
        if (property_exists($row, 'Mounts') && $row->Mounts instanceof stdClass) {
            $data[$index]['Mounts'] = $row->Mounts;
        }
        if (($row->HostConfig ?? null) instanceof stdClass && property_exists($row->HostConfig, 'Mounts')
            && $row->HostConfig->Mounts instanceof stdClass) {
            $data[$index]['HostConfig']['Mounts'] = $row->HostConfig->Mounts;
        }
    }
    return $data;
}
function m6linuxPlatform(string $architecture): string
{
    return match ($architecture) { 'amd64', 'x86_64' => 'linux/amd64', 'arm64', 'aarch64' => 'linux/arm64', default => '' };
}
function m6linuxEngine(array $info, string $platform): string
{
    m6linuxRequire(($info['OSType'] ?? '') === 'linux' && m6linuxPlatform($info['Architecture'] ?? '') === $platform, 'engine_platform');
    m6linuxRequire(in_array('name=rootless', $info['SecurityOptions'] ?? [], true), 'rootless_required');
    m6linuxRequire(($info['CgroupVersion'] ?? '') === '2' && ($info['CgroupDriver'] ?? '') === 'systemd', 'cgroup_v2_systemd_required');
    m6linuxRequire(empty($info['Warnings']), 'engine_warnings');
    m6linuxRequire(($info['NCPU'] ?? 0) >= 2 && ($info['MemTotal'] ?? 0) >= 3 * 1024 ** 3, 'host_resources');
    $path = $info['DockerRootDir'] ?? '';
    m6linuxRequire(is_string($path) && str_starts_with($path, '/') && !str_contains($path, "\n"), 'engine_data_path');
    return $path;
}
function m6linuxImage(array $image, string $digest, string $platform): string
{
    m6linuxRequire(preg_match('/^sha256:[a-f0-9]{64}$/D', $digest) === 1, 'image_digest_argument');
    $refs = $image['RepoDigests'] ?? [];
    m6linuxRequire(in_array('mysql@' . $digest, $refs, true) || in_array('docker.io/library/mysql@' . $digest, $refs, true), 'official_image_digest');
    m6linuxRequire(($image['Os'] ?? '') === 'linux' && 'linux/' . ($image['Architecture'] ?? '') === $platform, 'image_platform');
    m6linuxRequire(preg_match('/^sha256:[a-f0-9]{64}$/D', $image['Id'] ?? '') === 1, 'image_id');
    m6linuxRequire(is_int($image['Size'] ?? null) && $image['Size'] >= 0 && $image['Size'] <= 4 * 1024 ** 3, 'image_storage_budget');
    return $image['Id'];
}
function m6linuxClientConfig(string $path, int $uid): void
{
    m6linuxRequire(preg_match('~^/run/user/'. $uid .'/ubo-m6b-cli\.[a-zA-Z0-9]{8}$~D', $path) === 1
        && realpath($path) === $path && !is_link($path) && fileowner($path) === $uid
        && (fileperms($path) & 0777) === 0700, 'client_config_directory');
    $file = $path . '/config.json';
    m6linuxRequire(is_file($file) && !is_link($file) && fileowner($file) === $uid && (fileperms($file) & 0777) === 0600
        && filesize($file) <= 4 && trim((string) file_get_contents($file)) === '{}'
        && array_values(array_diff(scandir($path), ['.', '..'])) === ['config.json'], 'client_config_contents');
}
function m6linuxEnvironment(array $container, array $image, string $password): void
{
    $parse = static function ($entries): array {
        m6linuxRequire(is_array($entries), 'container_environment');
        $map = [];
        foreach ($entries as $entry) {
            m6linuxRequire(is_string($entry) && str_contains($entry, '='), 'container_environment');
            [$key, $value] = explode('=', $entry, 2);
            m6linuxRequire(preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $key) === 1 && !array_key_exists($key, $map), 'container_environment');
            $map[$key] = $value;
        }
        return $map;
    };
    $expected = $parse($image['Config']['Env'] ?? []);
    m6linuxRequire(preg_match('/^[a-f0-9]{64}$/D', $password) === 1, 'test_credentials');
    $expected['MYSQL_ROOT_PASSWORD'] = $password; $expected['MYSQL_ROOT_HOST'] = '%';
    $actual = $parse($container['Config']['Env'] ?? null);
    ksort($expected); ksort($actual);
    // Compare to pinned-image defaults, not an arbitrary generic environment allowlist. Never echo entries.
    m6linuxRequire($actual === $expected, 'container_environment');
}
function m6linuxLaunchContainer(array $rows, string $digest, string $platform): string
{
    m6linuxRequire(count($rows) === 2 && m6linuxImage($rows[1], $digest, $platform) === getenv('M6B_MYSQL_IMAGE_ID'), 'container_image');
    m6linuxEnvironment($rows[0], $rows[1], (string) getenv('MYSQL_ROOT_PASSWORD'));
    return m6linuxContainer($rows[0], (string) getenv('M6B_MYSQL_RUN_TOKEN'), (string) getenv('M6B_MYSQL_IMAGE_ID'), (string) getenv('M6B_MYSQL_CONTAINER_ID'));
}
/** PHP harness reinspection after engine/image checks and before any connection. */
function m6linuxMySqlIdentity(array $container, array $image, string $token, string $imageId, string $id, string $password, string $port): array
{
    m6linuxEnvironment($container, $image, $password);
    m6linuxRequire(m6linuxContainer($container, $token, $imageId, $id) === $port, 'port_changed');
    return ['token' => $token, 'id' => $id, 'port' => (int) $port, 'hostname' => $container['Config']['Hostname']];
}
function m6linuxOwner(array $container, string $token, string $imageId, ?string $expectedId): string
{
    $id = $container['Id'] ?? '';
    m6linuxRequire(preg_match('/^[a-f0-9]{32}$/D', $token) === 1 && preg_match('/^[a-f0-9]{64}$/D', $id) === 1, 'run_identity');
    m6linuxRequire(($expectedId === null || $id === $expectedId) && ($container['Name'] ?? '') === '/ubo-m6b-' . $token
        && ($container['Config']['Labels']['ubo.m6b.owner'] ?? '') === $token
        && ($container['Config']['Labels']['ubo.m6b.launcher'] ?? '') === 'linux-v1'
        && ($container['Image'] ?? '') === $imageId, 'container_ownership');
    return $id;
}
function m6linuxContainer(array $container, string $token, string $imageId, string $id): string
{
    m6linuxOwner($container, $token, $imageId, $id);
    $h = $container['HostConfig'] ?? [];
    m6linuxRequire(($container['State']['Running'] ?? false) === true && ($container['Platform'] ?? '') === 'linux', 'container_running_platform');
    m6linuxRequire(($h['Privileged'] ?? true) === false && ($h['NetworkMode'] ?? '') === 'bridge'
        && ($h['PidMode'] ?? '') === '' && ($h['IpcMode'] ?? '') === 'private'
        && ($h['UTSMode'] ?? '') === '' && ($h['UsernsMode'] ?? '') !== 'host'
        && empty($h['Devices']) && empty($h['DeviceRequests']) && empty($h['Binds']) && empty($h['VolumesFrom'])
        && empty($h['CapAdd']) && ($h['CgroupnsMode'] ?? '') === 'private', 'container_isolation');
    m6linuxRequire(($h['SecurityOpt'] ?? []) === ['no-new-privileges=true'], 'container_security_options');
    m6linuxRequire(($h['NanoCpus'] ?? 0) === 1000000000 && ($h['Memory'] ?? 0) === 1610612736
        && ($h['MemorySwap'] ?? -1) === 1610612736 && ($h['PidsLimit'] ?? 0) === 128, 'container_resource_request');
    m6linuxRequire(($h['Tmpfs'] ?? []) === ['/var/lib/mysql' => 'rw,nosuid,size=1073741824'], 'database_tmpfs');
    m6linuxRequire(!array_key_exists('Mounts', $h) || $h['Mounts'] === [], 'container_mount_requests');
    m6linuxRequire(array_key_exists('Mounts', $container) && is_array($container['Mounts'])
        && array_is_list($container['Mounts']), 'container_mounts');
    $mounts = $container['Mounts'];
    // --tmpfs may be reported only in HostConfig.Tmpfs, even while effectively mounted.
    m6linuxRequire($mounts === [] || (count($mounts) === 1 && is_array($mounts[0])
        && ($mounts[0]['Type'] ?? '') === 'tmpfs' && ($mounts[0]['Destination'] ?? '') === '/var/lib/mysql'), 'container_mounts');
    m6linuxRequire(($h['LogConfig']['Type'] ?? '') === 'local'
        && ($h['LogConfig']['Config']['max-size'] ?? '') === '1m'
        && ($h['LogConfig']['Config']['max-file'] ?? '') === '2'
        && ($h['LogConfig']['Config']['compress'] ?? '') === 'false', 'container_logs');
    $bindings = $container['NetworkSettings']['Ports'] ?? [];
    $port = $bindings['3306/tcp'][0]['HostPort'] ?? '';
    m6linuxRequire(count(array_filter($bindings, static fn ($v): bool => $v !== null)) === 1
        && count($bindings['3306/tcp'] ?? []) === 1 && ($bindings['3306/tcp'][0]['HostIp'] ?? '') === '127.0.0.1'
        && is_string($port) && preg_match('/\A[0-9]+\z/', $port) === 1
        && (int) $port > 0 && (int) $port <= 65535, 'loopback_binding');
    return $port;
}

// CLI bridge for Bash. Output is a single bounded value; raw inspection/Env is never logged.
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    // PHP warnings can contain input values; classify them without printing their text.
    set_error_handler(static function (): never { throw new ErrorException(); });
    try {
        $input = stream_get_contents(STDIN, 1048577);
        m6linuxRequire(strlen($input) <= 1048576, 'inspection_size');
        $mode = $argv[1] ?? '';
        $data = m6linuxDecodeInspection($input, $mode === 'engine');
        $row = $mode === 'engine' ? $data : ($data[0] ?? []);
        $result = match ($mode) {
            'engine' => m6linuxEngine($row, $argv[2] ?? ''),
            'image' => m6linuxImage($row, $argv[2] ?? '', $argv[3] ?? ''),
            'owner' => m6linuxOwner($row, (string) getenv('M6B_MYSQL_RUN_TOKEN'), (string) getenv('M6B_MYSQL_IMAGE_ID'), getenv('M6B_MYSQL_CONTAINER_ID') ?: null),
            'container' => m6linuxLaunchContainer($data, $argv[2] ?? '', $argv[3] ?? ''),
            default => throw new RuntimeException('M6B guard: unknown_mode'),
        };
        echo $result . "\n";
    } catch (Throwable $e) {
        // Never forward parser values, inspected Env, dependency errors or arbitrary exception text.
        $code = $e instanceof JsonException ? 'invalid_json' : 'guard_runtime_error';
        if ($e instanceof RuntimeException && str_starts_with($e->getMessage(), 'M6B guard: ')) $code = substr($e->getMessage(), 11);
        fwrite(STDERR, "M6B guard rejected inspection: " . $code . "\n");
        exit(2);
    }
}
