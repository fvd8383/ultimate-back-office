<?php

declare(strict_types=1);
require_once __DIR__ . '/SiteBuildContract.php';

/** Internal bounded file primitives. Paths are relative artifact names, never URLs. */
final class SiteArtifactFiles
{
    public const MAX_FILES = 2000;
    public const MAX_ASSET = 20971520;
    public const MAX_HTML = 1048576;
    public const MAX_PACKAGE = 104857600;
    public const MAX_MANIFEST = 1048576;
    public const MAX_VALIDATION = 16384;

    public static function fail(string $code = 'artifact_invalid'): never
    {
        throw new SiteArtifactException($code);
    }

    public static function path(string $path): string
    {
        if (strlen($path) > 500 || !preg_match('~^[a-zA-Z0-9_-]+(?:[a-zA-Z0-9_./-]*[a-zA-Z0-9])?$~D', $path)) self::fail();
        foreach (explode('/', $path) as $part) {
            if ($part === '' || str_contains($part, '..') || str_ends_with($part, '.')
                || preg_match('/^(?:con|prn|aux|nul|com[0-9]|lpt[0-9])(?:\.|$)/i', $part)) self::fail();
        }
        return $path;
    }

    public static function route(string $slug): string
    {
        if ($slug === '') return 'public/index.html';
        self::path($slug);
        if (strlen($slug) > 255 || !preg_match('~^[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*$~D', $slug)) self::fail();
        foreach (explode('/', $slug) as $part) if (in_array($part, ['assets','metadata','public','index'], true)) self::fail();
        return 'public/' . $slug . '/index.html';
    }

    /** Inspect every ancestor; callers require privately owned roots to exclude rename races. */
    public static function regular(string $path, bool $directory = false): array
    {
        clearstatcache(true, $path);
        $s = @lstat($path);
        if ($s === false || is_link($path) || ($s['mode'] & 0170000) !== ($directory ? 0040000 : 0100000)
            || (!$directory && $s['nlink'] !== 1)) self::fail();
        return $s;
    }

    public static function contained(string $root, string $relative, bool $directory = false): string
    {
        self::path($relative);
        self::regular($root, true);
        $path = $root;
        $parts = explode('/', $relative);
        foreach ($parts as $i => $part) {
            $path .= '/' . $part;
            self::regular($path, $i < count($parts) - 1 || $directory);
        }
        $real = realpath($path);
        $base = realpath($root);
        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) self::fail();
        return $path;
    }

    public static function read(string $path, int $max, ?callable $pulse = null): string
    {
        $before = self::regular($path);
        if ($before['size'] < 0 || $before['size'] > $max) self::fail();
        $h = @fopen($path, 'rb');
        if ($h === false) self::fail('storage_unavailable');
        try {
            $s = fstat($h);
            if ($s === false || $s['ino'] !== $before['ino'] || $s['dev'] !== $before['dev'] || $s['nlink'] !== 1) self::fail();
            $bytes = '';
            do {
                if ($pulse !== null) $pulse();
                $chunk = @fread($h, min(1048576, $max + 1 - strlen($bytes)));
                if ($chunk === false) self::fail('storage_unavailable');
                $bytes .= $chunk;
                if ($pulse !== null) $pulse();
                if (strlen($bytes) > $max) self::fail();
                if ($chunk === '' && !feof($h)) self::fail('storage_unavailable');
            } while (!feof($h));
            if (strlen($bytes) !== $before['size']) self::fail();
            return $bytes;
        } finally { fclose($h); }
    }

    public static function writeNew(string $path, string $bytes, int $max): void
    {
        if (strlen($bytes) > $max) self::fail();
        $h = @fopen($path, 'xb');
        if ($h === false) self::fail('storage_unavailable');
        try {
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $n = @fwrite($h, substr($bytes, $offset, 1048576));
                if ($n === false || $n === 0) self::fail('storage_unavailable');
                $offset += $n;
            }
            if (!@fflush($h) || !@fsync($h)) self::fail('storage_unavailable');
        } finally { fclose($h); }
        if (!@chmod($path, 0600)) self::fail('storage_unavailable');
    }

    public static function mkdir(string $path): void
    {
        if (!@mkdir($path, 0700)) self::fail('storage_unavailable');
        self::regular($path, true);
    }

    /** @return list<string> Complete bounded inventory, rejecting empty/unexpected directories later. */
    public static function inventory(string $root, ?callable $pulse = null): array
    {
        self::regular($root, true);
        $files = []; $names = []; $directories = 0;
        $walk = function (string $relative) use (&$walk, &$files, &$names, &$directories, $root, $pulse): void {
            if ($pulse !== null) $pulse();
            $iterator = new DirectoryIterator($root . ($relative === '' ? '' : '/' . $relative));
            $count = 0;
            foreach ($iterator as $entry) {
                if ($entry->isDot()) continue;
                if ($pulse !== null) $pulse();
                if (++$count > self::MAX_FILES) self::fail();
                $name = self::path(($relative === '' ? '' : $relative . '/') . $entry->getFilename());
                if (isset($names[strtolower($name)]) || substr_count($name, '/') > 20) self::fail();
                $names[strtolower($name)] = true;
                $s = @lstat($root . '/' . $name);
                if ($s !== false && ($s['mode'] & 0170000) === 0040000) {
                    if (++$directories > self::MAX_FILES) self::fail();
                    self::regular($root . '/' . $name, true);
                    $walk($name);
                } else {
                    self::regular($root . '/' . $name);
                    if (count($files) >= self::MAX_FILES) self::fail();
                    $files[] = $name;
                }
            }
            if ($relative !== '' && $count === 0) self::fail();
        };
        $walk(''); sort($files, SORT_STRING); return $files;
    }
}

/** Bounded worker vocabulary, independent of request-error classifications. */
final class SiteArtifactException extends RuntimeException
{
    public function __construct(public readonly string $failureCode)
    {
        parent::__construct(SiteBuildContract::FAILURES[$failureCode][1] ?? 'Artifact verification failed.');
    }
}
