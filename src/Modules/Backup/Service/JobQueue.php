<?php
namespace Modules\Backup\Service;

use Core\Cache\ValkeyCache;

class JobQueue
{
    private const QUEUE_KEY = 'backup:queue';
    private const STATUS_PREFIX = 'backup:status:';
    private const PROGRESS_PREFIX = 'backup:progress:';
    private const STATUS_TTL = 86400;

    private $valkey;

    public function __construct()
    {
        $this->valkey = ValkeyCache::getClient();
    }

    public function enqueue(string $jobId): void
    {
        $this->valkey->lpush(self::QUEUE_KEY, $jobId);
    }

    public function dequeue(int $timeout = 30): ?string
    {
        $result = $this->valkey->brpop(self::QUEUE_KEY, $timeout);
        if ($result && is_array($result) && count($result) >= 2) {
            return $result[1];
        }
        return null;
    }

    public function setStatus(string $jobId, string $status): void
    {
        $this->valkey->setex(self::STATUS_PREFIX . $jobId, self::STATUS_TTL, $status);
    }

    public function getStatus(string $jobId): ?string
    {
        $val = $this->valkey->get(self::STATUS_PREFIX . $jobId);
        return $val !== false ? $val : null;
    }

    public function setProgress(string $jobId, string $step): void
    {
        $this->valkey->setex(self::PROGRESS_PREFIX . $jobId, self::STATUS_TTL, $step);
    }

    public function getProgress(string $jobId): ?string
    {
        $val = $this->valkey->get(self::PROGRESS_PREFIX . $jobId);
        return $val !== false ? $val : null;
    }

    public function clearJob(string $jobId): void
    {
        $this->valkey->del(self::STATUS_PREFIX . $jobId);
        $this->valkey->del(self::PROGRESS_PREFIX . $jobId);
    }
}
