# Queue backlog

## Detect and classify

Use Horizon dashboard through an authorized admin context and CLI status:

```bash
docker compose --env-file <approved-env-file> exec horizon php artisan horizon:status
docker compose --env-file <approved-env-file> exec --user www-data backend php artisan queue:failed
docker compose --env-file <approved-env-file> logs --since=15m horizon
```

Identify queue, oldest age, throughput, retry count, failure class, Redis/MySQL
latency, external dependency, recent deploy, and whether jobs are critical
(booking/payment) or background notification/broadcast. Never dump payloads with
personal data/secrets into a shared incident channel.

## Mitigate

1. Stop the producer or pause affected queue if work is harmful/repeating.
2. Fix dependency/config/code cause before bulk retry.
3. Scale Horizon within database/Redis/external rate capacity; configured queue
   groups and `HORIZON_*_MAX_PROCESSES` are the supported knobs.
4. Keep worker timeout below Redis `retry_after`; preserve `after_commit=true`.
5. Retry only reviewed failed job IDs/batches. Do not bulk retry payment or
   webhook work without proving idempotency and reconciling Midtrans status.

Use graceful `php artisan horizon:terminate` after deploying a fix so the process
manager starts workers on new code. Do not kill workers mid-transaction unless
the incident owner accepts retry effects.

## Exit criteria

Backlog age/count trends down, failures stop, throughput is stable, MySQL/Redis
remain healthy, and sampled domain side effects are single/idempotent. Record
delayed/lost notifications and reconcile booking/payment state separately.
