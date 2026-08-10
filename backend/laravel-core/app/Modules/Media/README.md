# Media module

Media owns the Laravel filesystem boundary for public and private objects. `MediaStorage` generates object keys, writes streams with explicit visibility, calculates SHA-256 checksums from streams, and hides the selected local or S3-compatible disk from callers.

Current private integrations:

- provider KTP/NIB files, with the existing signed and authorized access contract preserved;
- support/chat attachments, exposed only through relative, short-lived signed routes plus actor and tenant authorization.

The public media disks are prepared targets, but existing provider/service/staff/review public upload flows still use the legacy `public` disk and are not integrated with this module in Phase 7. New sensitive integrations must use `MediaVisibility::Private`; callers must never construct a permanent public URL for a private object.

Legacy public provider documents and chat attachments are handled by the allowlisted `media:migrate-legacy` command and `media_migration_entries` manifest. Copy/verification and DB pointer cutover never delete the source. A separate dry-run-first retirement stage is disabled by default, enforces a minimum cutover age, archives the source privately, verifies source/target/archive checksums, and only then removes the public copy. Rollback restores a retired source from the verified archive before changing the pointer; target and archive are retained.

S3/R2 configuration is available but no external bucket is assumed to exist. See `docs/runbooks/media-storage-cutover.md` before changing any disk environment variable.
