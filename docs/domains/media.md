# Media domain

The Phase 7 Media boundary is active for new private provider KTP/NIB files, new private chat attachments, their controlled delivery, and the allowlisted legacy migration workflow. Business modules retain ownership of their database records and authorization rules.

Existing public provider, service, staff, and review upload flows still use the legacy `public` disk directly. The `media_public` / `media_public_s3` configuration is a prepared target for a later, separately validated integration; Phase 7 does not claim that those public flows generate keys through `MediaStorage` yet.

## Logical storage

| Purpose | Current / prepared disk | Visibility | S3-compatible option |
| --- | --- | --- | --- |
| Public provider/service/staff/review images | current `public`; prepared `media_public` | public | prepared `media_public_s3` under the `public` prefix |
| Support/chat attachments | `media_private` | private | `media_private_s3` under the `private` prefix |
| Provider KTP/NIB | `provider_documents` | private | set `PROVIDER_DOCUMENTS_DISK=media_private_s3` after migration |

Local disks remain the safe default. The S3 disks use Laravel Flysystem and work with S3-compatible endpoints such as Cloudflare R2 when valid endpoint, bucket, and credentials are supplied. The repository does not provision or claim an external bucket.

## Private delivery

Provider documents retain their existing owner/admin signed routes. Chat attachment payloads contain a relative, short-lived signed path so the same realtime payload works on admin and provider hosts without relying on a cross-subdomain session cookie. The download action independently enforces:

- valid relative signature and expiry;
- authenticated admin or provider guard;
- active and document-verified provider account;
- chat menu permission;
- thread participant/tenant scope;
- approved or closed support-thread state.

Responses are streamed with private, no-store, nosniff headers. Raw object paths are not public URLs.

## Legacy migration manifest

`media_migration_entries` contains an immutable idempotency key, allowlisted subject/field, source, target, and retirement-archive fingerprints, streamed SHA-256 checksums, lifecycle timestamps, retirement/rollback counters, and the last failure. Only provider profile KTP/NIB fields and chat message attachment paths are accepted; the CLI accepts no arbitrary model class, disk, or path.

Copy and initial cutover always retain the public source. A later, explicit `retire` stage is hard-disabled by default and enforces a configurable minimum age from the latest cutover (30 days by default, never below one). Once separately approved and enabled, it can remove the source only after a deterministic private archive and the private target both match the verified source checksum. Rollback restores a retired public source from that verified archive before moving the pointer; neither target nor archive is deleted.
