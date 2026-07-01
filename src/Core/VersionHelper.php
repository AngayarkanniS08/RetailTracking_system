<?php
namespace Core;

class VersionHelper
{
    private static ?string $version = null;

    public static function getVersion(): string
    {
        if (self::$version !== null) return self::$version;

        $rootDir = __DIR__ . '/../..';
        $gitDir = $rootDir . '/.git';
        $versionFile = $rootDir . '/version.txt';

        // Try reading from version.txt (Docker/production)
        if (file_exists($versionFile)) {
            $v = trim(file_get_contents($versionFile));
            if ($v) {
                self::$version = $v;
                return self::$version;
            }
        }

        // Try reading from .git (local dev)
        if (!is_dir($gitDir)) {
            self::$version = 'v0.1-dev';
            return self::$version;
        }

        $hash = '';
        $headFile = $gitDir . '/HEAD';
        if (file_exists($headFile)) {
            $head = trim(file_get_contents($headFile));
            if (str_starts_with($head, 'ref: ')) {
                $refPath = $gitDir . '/' . substr($head, 5);
                $hash = file_exists($refPath) ? trim(file_get_contents($refPath)) : $head;
            } else {
                $hash = $head;
            }
            $hash = substr($hash, 0, 7);
        }

        $count = 0;
        $logFile = $gitDir . '/logs/HEAD';
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $count = is_array($lines) ? count($lines) : 0;
        }

        $tag = 'v0.1';
        $tagOut = trim((string) shell_exec('git describe --tags --abbrev=0 2>/dev/null'));
        if ($tagOut) $tag = $tagOut;

        $dirty = '';
        $status = trim((string) shell_exec('git status --porcelain 2>/dev/null'));
        if ($status) $dirty = '-dirty';

        self::$version = "$tag-$count-g$hash$dirty";

        // Cache to version.txt for Docker
        file_put_contents($versionFile, self::$version);

        return self::$version;
    }
}
