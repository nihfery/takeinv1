# Redis unavailable

## Impact

Production session, cache, rate limiter, queue, Horizon metadata, and Reverb
scaling use Redis. Readiness becomes unavailable; users may be unable to
authenticate and asynchronous work stops. Booking/payment MySQL rows remain the
transaction truth, but writes that require session/rate/queue state should fail
closed rather than silently degrading.

## Triage

```bash
docker compose --env-file <approved-env-file> ps redis horizon reverb backend
docker compose --env-file <approved-env-file> logs --since=15m redis horizon reverb backend
docker compose --env-file <approved-env-file> exec redis \
  sh -lc 'redis-cli --no-auth-warning -a "$REDIS_PASSWORD" ping'
```

Check host memory/disk/inodes, AOF errors, OOM/restart events, authentication,
network, connection count, latency, persistence health, and `noeviction` memory
pressure. Do not use `FLUSHALL`, delete `youyaku_redis`, disable the password,
or change `maxmemory-policy` as an incident shortcut.

## Recovery

Use the platform-approved restart/failover/restore action. Redis HA/managed
failover is not provisioned here. Do not switch production queue to `sync` or
session/cache to local files to make readiness green; that breaks multi-instance
semantics and can execute side effects in request paths.

After PONG, verify `/api/readiness`, log in through separate Laravel instances,
Horizon status/backlog, scheduler, rate limits, and Reverb scale-out. Review
failed/delayed jobs and session loss. Payment/booking jobs may be retried only
after checking idempotency and authoritative database/gateway state.
