<?php
declare(strict_types=1);

namespace Core\Cache;

use Redis;
use Throwable;

class CacheInvalidationService
{
    private const MAX_RETRIES = 3;
    private const SCAN_BATCH_SIZE = 100;
    private const BASE_DELAY_MS = 100;

    private Redis $valkey;

    public function __construct(?Redis $valkey = null)
    {
        $this->valkey = $valkey ?? ValkeyCache::getClient();
    }

    /**
     * Invalidate cache keys matching a glob pattern using SCAN (non-blocking).
     * Retries on transient failures with exponential backoff.
     *
     * @param string $pattern  Glob pattern (e.g. 'billing:invoices:*')
     * @param array  $context  Optional context for structured logging
     *
     * @return CacheInvalidationResult
     */
    public function invalidatePattern(string $pattern, array $context = []): CacheInvalidationResult
    {
        $startTime = microtime(true);
        $attempts = 0;
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $attempts = $attempt;
            try {
                $deletedKeys = $this->scanAndDelete($pattern);
                $duration = (microtime(true) - $startTime) * 1000;

                $this->log('Cache invalidation succeeded', [
                    'pattern'  => $pattern,
                    'deleted'  => count($deletedKeys),
                    'duration_ms' => round($duration, 2),
                    'attempt'  => $attempt,
                    'success'  => true,
                ] + $context);

                return new CacheInvalidationResult(
                    pattern: $pattern,
                    deletedKeys: $deletedKeys,
                    durationMs: $duration,
                    attempts: $attempt,
                    success: true,
                    error: null,
                );
            } catch (Throwable $e) {
                $lastError = $e;

                if ($attempt < self::MAX_RETRIES && $this->isTransient($e)) {
                    $delayMs = self::BASE_DELAY_MS * (2 ** ($attempt - 1));
                    usleep($delayMs * 1000);
                    continue;
                }

                break;
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        $this->log('Cache invalidation FAILED', [
            'pattern'     => $pattern,
            'error'       => $lastError?->getMessage(),
            'duration_ms' => round($duration, 2),
            'attempt'     => $attempts,
            'success'     => false,
        ] + $context);

        return new CacheInvalidationResult(
            pattern: $pattern,
            deletedKeys: [],
            durationMs: $duration,
            attempts: $attempts,
            success: false,
            error: $lastError?->getMessage(),
        );
    }

    /**
     * Invalidate multiple patterns in sequence.
     *
     * @param string[] $patterns
     * @param array    $context
     *
     * @return CacheInvalidationResult[]
     */
    public function invalidatePatterns(array $patterns, array $context = []): array
    {
        $results = [];
        foreach ($patterns as $pattern) {
            $results[] = $this->invalidatePattern($pattern, $context);
        }
        return $results;
    }

    /**
     * Invalidate using version counter (INCR) strategy.
     * Used by the vendor module.
     */
    public function bumpVersion(string $counterKey, array $context = []): CacheInvalidationResult
    {
        $startTime = microtime(true);
        $attempts = 0;
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $attempts = $attempt;
            try {
                $newVersion = $this->valkey->incr($counterKey);
                $duration = (microtime(true) - $startTime) * 1000;

                $this->log('Cache version bump succeeded', [
                    'counter_key' => $counterKey,
                    'new_version' => $newVersion,
                    'duration_ms' => round($duration, 2),
                    'attempt'     => $attempt,
                    'success'     => true,
                ] + $context);

                return new CacheInvalidationResult(
                    pattern: $counterKey,
                    deletedKeys: [],
                    durationMs: $duration,
                    attempts: $attempt,
                    success: true,
                    error: null,
                    metadata: ['new_version' => $newVersion],
                );
            } catch (Throwable $e) {
                $lastError = $e;
                if ($attempt < self::MAX_RETRIES && $this->isTransient($e)) {
                    usleep((self::BASE_DELAY_MS * (2 ** ($attempt - 1))) * 1000);
                    continue;
                }
                break;
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;
        $this->log('Cache version bump FAILED', [
            'counter_key' => $counterKey,
            'error'       => $lastError?->getMessage(),
            'duration_ms' => round($duration, 2),
            'attempt'     => $attempts,
            'success'     => false,
        ] + $context);

        return new CacheInvalidationResult(
            pattern: $counterKey,
            deletedKeys: [],
            durationMs: $duration,
            attempts: $attempts,
            success: false,
            error: $lastError?->getMessage(),
        );
    }

    /**
     * Scan for keys matching pattern and delete them.
     *
     * @return string[] List of deleted keys
     */
    private function scanAndDelete(string $pattern): array
    {
        $keysToDelete = [];
        $cursor = null;

        do {
            $batch = $this->valkey->scan($cursor, $pattern, self::SCAN_BATCH_SIZE);
            if (!empty($batch)) {
                $keysToDelete = array_merge($keysToDelete, $batch);
            }
        } while ($cursor > 0);

        if (!empty($keysToDelete)) {
            $this->valkey->del($keysToDelete);
        }

        return $keysToDelete;
    }

    /**
     * Determine if an error is transient (network, timeout, connection).
     */
    private function isTransient(Throwable $e): bool
    {
        $msg = $e->getMessage();
        $transient = [
            'connection refused',
            'timed out',
            'timeout',
            'reset by peer',
            'broken pipe',
            'connect',
            'network',
            'read error',
        ];

        foreach ($transient as $keyword) {
            if (stripos($msg, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function log(string $message, array $context = []): void
    {
        $entry = json_encode([
            'ts'      => date('Y-m-d\TH:i:s.v'),
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES);
        error_log((string)$entry);
    }
}

class CacheInvalidationResult
{
    public readonly string $pattern;
    public readonly array $deletedKeys;
    public readonly float $durationMs;
    public readonly int $attempts;
    public readonly bool $success;
    public readonly ?string $error;
    public readonly array $metadata;

    public function __construct(
        string $pattern,
        array $deletedKeys,
        float $durationMs,
        int $attempts,
        bool $success,
        ?string $error = null,
        array $metadata = [],
    ) {
        $this->pattern = $pattern;
        $this->deletedKeys = $deletedKeys;
        $this->durationMs = $durationMs;
        $this->attempts = $attempts;
        $this->success = $success;
        $this->error = $error;
        $this->metadata = $metadata;
    }

    public function deletedCount(): int
    {
        return count($this->deletedKeys);
    }
}
