# MySQL unavailable

## Impact

`/api/health` and `/api/readiness` return failure, backend deployment-check
fails, and transactional reads/writes including booking/payment are unavailable.
Do not accept booking/payment into an alternate cache truth.

## Triage

```bash
docker compose --env-file <approved-env-file> ps db backend backend-http
docker compose --env-file <approved-env-file> logs --since=15m db backend backend-http
docker compose --env-file <approved-env-file> exec db \
  sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -h 127.0.0.1 -uroot --silent'
```

Check host disk/inode capacity, OOM/restart events, MySQL error log, connection
count, slow/blocked transactions, credential/config change, network membership,
and persistent volume attachment. Do not delete/recreate the container volume,
run repair, or initialize a blank database as a retry.

## Containment and recovery

Stop or maintenance-gate writes. Pause queue workers if jobs write MySQL; retain
job payloads in Redis. Follow the database platform's approved failover/restart
procedure. This repository does not provision MySQL HA or managed failover.

After recovery:

```bash
docker compose --env-file <approved-env-file> exec --user www-data backend php artisan migrate:status
docker compose --env-file <approved-env-file> exec --user www-data backend php artisan app:deployment-check
curl --fail --show-error https://api.takein.id/api/readiness
```

Inspect failed jobs, incomplete deployment backup/migration, deadlocks, booking
holds, and payment webhooks during the outage. Reconcile Midtrans authoritative
status before retrying payment work. Resume Horizon and traffic gradually.

If corruption or data loss is suspected, do not resume writes; follow
`docs/database/backup-restore.md` and `docs/runbooks/rollback.md`.
