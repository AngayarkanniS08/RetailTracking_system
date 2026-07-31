# Performance Baseline Report

## Environment
- Valkey version: 8.6.2 (Redis-compatible)
- OS: Darwin 25.5.0 arm64
- Uptime: 140,453 seconds (~39 hours)
- Max clients: 10,000
- Connected clients: 1

## Memory
- Used: 974.69K
- Peak: 974.97K
- Maxmemory: 0 (no limit)
- Evictions: 0

## Key Statistics
- Total keys in DB: 3 (vortex:* and location_buffer — unrelated)
- Application cache keys: 0 (all expired in dev environment)
- Expired keys: 0
- Slow log: empty (no slow queries)
- Average latency: ~0.10ms

## Cache Performance
- Keyspace hits: 0
- Keyspace misses: 0
- Total commands processed: 1 (freshly started metrics counter)

## Application Query Performance
- N/A — baseline measured before test load

## Known Issues (pre-fix)
- `KEYS` will block Valkey on production with hundreds of cache keys
- Multiple cache keys per user/per page combination — estimated O(users × pages × searches) keys
- No retry mechanism on invalidation failure
- Silent catch on all cache operations
