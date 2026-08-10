# Migration policy

## Rules

1. Never edit, rename, reorder, or delete an applied historical migration.
2. Add a new timestamped migration under
   `backend/laravel-core/database/migrations`.
3. Preserve route/migration baseline parity; additive files require an explicit
   reviewed allowlist entry in the validation gate.
4. Use MySQL 8/InnoDB-compatible DDL. Booking/payment critical tables must remain
   transactional.
5. Prefer expand/backfill/verify/contract over one-step destructive changes.
   Large backfills should be resumable and bounded.
6. Provide a safe `down()` only when rollback does not destroy post-deploy data.
   Otherwise document forward-fix/restore requirements rather than pretending a
   destructive rollback is safe.
7. Test clean migration, upgrade from a realistic snapshot, rollback behavior
   where supported, application boot, and `app:deployment-check`.

## Deployment order

The backend entrypoint clears stale config, detects pending migrations, creates
a single-transaction gzip MySQL dump when
`DEPLOY_BACKUP_BEFORE_MIGRATE=true`, runs `migrate --force`, then runs
`app:deployment-check`. Failure stops the backend before PHP-FPM starts.

Migration design must be compatible with rolling deploys: old and new code may
overlap unless the runbook explicitly schedules maintenance. Do not drop/rename
a field read by the previous image in the same rollout.

## Validation

```bash
cd backend/laravel-core
php artisan migrate:status
php artisan migrate --pretend
php artisan app:deployment-check
```

From repository root:

```powershell
pwsh -File tools/validation/compare-migrations.ps1
```

`--pretend` is review assistance, not proof that data backfill and lock duration
are safe. Run staging migration with production-like cardinality before release.
