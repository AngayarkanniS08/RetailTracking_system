<?php

namespace Core\Cache;

use Redis;

class ValkeyCache
{
    private static ?Redis $instance = null;

    public static function getClient(): Redis
    {
        if (self::$instance === null) {
            $host = getenv('VALKEY_HOST') ?: 'valkey';
            $port = (int)(getenv('VALKEY_PORT') ?: 6379);
            self::$instance = new Redis();
            self::$instance->connect($host, $port);
        }
        return self::$instance;
    }
}
