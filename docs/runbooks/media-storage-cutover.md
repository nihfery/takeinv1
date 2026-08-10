# Media object-storage cutover runbook

This runbook prepares a cutover; it does not mean an S3/R2 bucket has been provisioned. Obtain and test the external bucket, credentials, lifecycle policy, backup policy, encryption, CORS/CDN policy for public objects, and private network/egress policy before selecting an S3 disk.

## 1. Configure and validate

Keep the application on local disks while provisioning:

```env
MEDIA_PUBLIC_DISK=media_public
MEDIA_PRIVATE_DISK=media_private
CHAT_ATTACHMENTS_DISK=media_private
PROVIDER_DOCUMENTS_DISK=provider_documents
MEDIA_LEGACY_PUBLIC_DISK=public
MEDIA_LEGACY_ARCHIVE_DISK=media_private
MEDIA_LEGACY_ARCHIVE_PREFIX=legacy-retirement
MEDIA_LEGACY_RETIREMENT_ENABLED=false
MEDIA_LEGACY_RETIREMENT_MIN_AGE_DAYS=30
```

Configure the placeholder `AWS_*` values and `MEDIA_PUBLIC_PREFIX` / `MEDIA_PRIVATE_PREFIX`. Validate access with non-production objects. Never commit real credentials.

After the bucket is verified, choose targets explicitly:

```env
MEDIA_PUBLIC_DISK=media_public_s3
MEDIA_PRIVATE_DISK=media_private_s3
CHAT_ATTACHMENTS_DISK=media_private_s3
PROVIDER_DOCUMENTS_DISK=media_private_s3
MEDIA_LEGACY_ARCHIVE_DISK=media_private_s3
```

Run `php artisan config:clear` after changing environment values. The archive disk must declare private visibility and its lifecycle/backup policy must retain archives for the approved rollback window. Do not change the legacy public disk until all fallback objects have either been retained or deliberately retired.

## 2. Plan with no mutations

The command is dry-run unless `--execute` is present:

```bash
php artisan media:migrate-legacy --scope=provider-documents --stage=copy
php artisan media:migrate-legacy --scope=chat-attachments --stage=copy
```

Review every source/target mapping and investigate database pointers whose source object is missing. `--id=<positive-id>` restricts the allowlisted profile or message only when paired with the explicit `provider-documents` or `chat-attachments` scope; it is rejected with `--scope=all` because IDs can collide across tables. `--chunk=1..1000` controls iteration size.

## 3. Copy and checksum-verify

```bash
php artisan media:migrate-legacy --scope=provider-documents --stage=copy --execute
php artisan media:migrate-legacy --scope=chat-attachments --stage=copy --execute
```

This stage:

1. creates or resumes a unique manifest row;
2. reads the source as a stream and calculates SHA-256;
3. writes the target privately without overwriting a different existing object;
4. recalculates both checksums as streams;
5. marks the manifest `verified` only when source-before, source-after, and target match.

It does not change the application database pointer and never deletes the source.

## 4. Cut over verified pointers

Preview, then execute:

```bash
php artisan media:migrate-legacy --scope=all --stage=cutover
php artisan media:migrate-legacy --scope=all --stage=cutover --execute
```

Cutover locks the manifest and owning row, re-verifies both objects, refuses concurrent pointer changes, and then updates only the allowlisted field. Re-running it is idempotent. Monitor application storage errors and sample owner/admin/provider downloads after cutover.

## 5. Retire a retained public source (separate approval)

Do not combine this with the initial copy/cutover change window. Retirement is hard-disabled by default, and the default 30-day minimum age makes a first-rollout deletion impossible even after an operator deliberately enables it. First complete the acceptance window: confirm the approved retention period, backups, sampled downloads, target durability, incident-free cutover age, and rollback ownership.

Keep `MEDIA_LEGACY_RETIREMENT_MIN_AGE_DAYS` at the approved value (it must be at least `1`; `30` is the repository default). Only for the approved retirement window, set `MEDIA_LEGACY_RETIREMENT_ENABLED=true`, clear the config cache, and preview before execution:

```bash
php artisan media:migrate-legacy --scope=all --stage=retire
php artisan media:migrate-legacy --scope=all --stage=retire --execute
```

Only manifests still in `cutover` state are eligible. Under manifest and owning-row locks, retirement:

1. requires retirement to be enabled and the latest `cutover_at` to satisfy the minimum age;
2. requires the database pointer to remain on the deterministic private target;
3. verifies the retained source and target against the manifest SHA-256 checksum;
4. copies the source to a deterministic path below `MEDIA_LEGACY_ARCHIVE_PREFIX` on the private archive disk;
5. verifies source, target, and archive again and commits archive metadata;
6. only then deletes the public source and records retirement metadata.

The two persisted steps make the operation idempotent and resumable if the process stops after archive verification or source deletion. It never deletes the target or private archive. The private archive is rollback material, not a public-serving fallback and not a substitute for an independent bucket backup. Set `MEDIA_LEGACY_RETIREMENT_ENABLED=false` again and clear the config cache when the approved window closes.

Execute retirement only in an approved exclusive-writer maintenance window:
put HTTP traffic in maintenance mode, pause Horizon, drain or account for every
media-writing job, and prevent parallel operator commands. Database row locks
cannot lock an S3/local object against a separate writer. Where supported,
enable bucket versioning and retain the deleted public version for the rollback
window. Resume workers and HTTP traffic only after sampling the manifest,
target, archive, and expected absence of the public source. Recover a crash by
re-running the same idempotent command; never start a competing retirement.

## 6. Roll back the pointer

Rollback verifies a retained source checksum. If retirement removed the source, rollback first verifies the allowlisted deterministic private archive, restores the public source from it, and verifies the restored checksum before changing the database pointer:

```bash
php artisan media:migrate-legacy --scope=all --stage=rollback
php artisan media:migrate-legacy --scope=all --stage=rollback --execute
```

Rollback leaves both target and archive intact and records the rollback count/time. If a private target temporarily fails before retirement, the secure read resolver also attempts the verified retained source for a cut-over manifest.

## Stop conditions

Stop before cutover or retirement if any checksum differs, a required object is missing, a private disk is unexpectedly public, a manifest points to a non-allowlisted subject, or the database pointer changed since planning. A rollback from retirement must stop if archive metadata or content differs. Do not repair these cases by deleting or overwriting objects; investigate and rerun only after the cause is understood.
