# Rollback runbook

## Choose the rollback type

- Application-only regression with backward-compatible schema: restore the last
  known-good immutable backend/backend-http/frontend image set.
- Configuration regression: restore the last reviewed secret/config version,
  clear/rebuild Laravel config, and restart only affected services.
- Migration/data corruption: stop writes and use database-owner-led forward fix
  or restore. Do not run `migrate:rollback` blindly.
- Media cutover: use the checksum-aware procedure in
  `docs/runbooks/media-storage-cutover.md`.

## Application rollback

1. Declare incident/change, record current and target image digests, and confirm
   the previous image can read the current expanded schema.
2. Stop new writes at the edge or use Laravel maintenance mode. Pause Horizon
   when queued writes are incompatible; preserve failed-job evidence.
3. Point the orchestrator at the exact last-known-good image set and redeploy.
   Do not rebuild an old Git commit under a mutable tag during the incident.
4. Run `app:deployment-check`, migration status, health/readiness, Horizon,
   scheduler, Reverb, tenant-denial smoke tests, booking, and payment status.
5. Re-enable workers and traffic gradually, then reconcile jobs/webhooks created
   during the window.

## Schema/data recovery

Prefer a forward-compatible fix. If restore is required, follow
`docs/database/backup-restore.md`: obtain incident commander/database owner
approval, stop every writer, preserve an emergency current-state snapshot,
validate exact source/target/checksum twice, restore to a new isolated instance
where possible, and switch only after verification.

An entrypoint pre-migration dump is only the database state before that rollout.
Restoring it loses legitimate writes after the snapshot and does not restore
media, Redis jobs/sessions, or external Midtrans state. Define the reconciliation
set before traffic resumes.

## Exit criteria

- All host surfaces and negative authorization smoke tests pass.
- Booking concurrency/idempotency invariants and payment state transitions are
  intact.
- MySQL/Redis/Horizon/Reverb are stable and no migration is unexpectedly pending.
- Midtrans transactions in the window are reconciled against authoritative
  gateway status.
- Root cause, lost/replayed work, data gap, exact commands, and follow-up are
  recorded. Maintenance mode is removed only after the incident owner approves.
