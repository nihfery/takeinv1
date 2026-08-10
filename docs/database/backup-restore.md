# Backup and restore

## What is implemented

When the backend entrypoint sees pending migrations and
`DEPLOY_BACKUP_BEFORE_MIGRATE=true`, it runs `mysqldump` with
`--single-transaction --quick --skip-lock-tables --triggers`, compresses the
result, sets mode `0600`, and stores it at:

```text
/var/www/html/storage/app/deployment-backups/pre-migrate-<UTC>.sql.gz
```

This file lives on the persistent `youyaku_storage` volume. It is a pre-migration
safety snapshot, not a complete backup program. Repository code does not
provision encryption-at-rest, retention pruning, off-site copy, restore drills,
MySQL point-in-time recovery, or backups for S3/R2/public media.

## Required production backup program

Platform owners must separately provide encrypted scheduled MySQL backups,
binary-log/PITR policy where required, object/media backup and versioning,
off-site copy, retention/legal policy, access logging, and restore drills with
documented RPO/RTO. Until those controls are verified, production readiness is
blocked regardless of the entrypoint snapshot.

## Inspect a pre-migration snapshot

List exact files without exposing content:

```bash
docker compose --env-file <approved-env-file> exec backend \
  sh -lc 'find /var/www/html/storage/app/deployment-backups -maxdepth 1 -type f -name "pre-migrate-*.sql.gz" -printf "%f %s bytes\n"'
```

Choose one exact filename, copy it to an encrypted operator workspace, then
validate gzip and calculate a checksum:

```bash
docker compose --env-file <approved-env-file> cp \
  backend:/var/www/html/storage/app/deployment-backups/pre-migrate-20260810T120000Z.sql.gz \
  ./pre-migrate-20260810T120000Z.sql.gz
gzip -t ./pre-migrate-20260810T120000Z.sql.gz
sha256sum ./pre-migrate-20260810T120000Z.sql.gz
```

Replace the example timestamp only after inventorying the exact file. Never
print the SQL to shared logs or attach it to tickets.

## Restore drill to an isolated database

Prefer a fresh isolated MySQL instance/database. Confirm the target hostname,
database name, and backup checksum with a second operator. Create no connection
from restored data to production mail, webhook, or payment services. Then:

```bash
gzip -dc ./pre-migrate-20260810T120000Z.sql.gz \
  | mysql --defaults-extra-file=<approved-private-client-config> \
      --host=<isolated-host> --user=<isolated-user> <isolated-database>
```

The client config must be injected through an approved private secret path with
restricted permissions; do not place a database password in shell history.

Run migration status, `app:deployment-check`, row-count/business sampling, and
application smoke tests against the isolated restore. Record duration and
whether the measured RPO/RTO meets the external policy.

## Production restore

A production restore is a destructive incident action and requires explicit
incident commander/database owner approval. First stop writes, preserve logs,
create an emergency current-state backup if MySQL allows it, validate the exact
target and snapshot twice, and follow `docs/runbooks/rollback.md`. Restore to a
new database/instance and switch only after validation where practical; avoid
in-place import. Resume Horizon/scheduler/HTTP only after schema, deployment
check, payment reconciliation, and smoke tests pass.

Media restoration follows the external object backup/versioning policy.
`media:migrate-legacy --stage=rollback` only restores an allowlisted cutover
pointer/source from its verified archive; it is not a general bucket or database
restore.
