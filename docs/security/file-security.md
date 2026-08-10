# File security

## Private paths

New provider KTP/NIB and chat attachments use private disks. Provider documents
are served only to the owner/admin through short-lived signed routes. Chat
attachments use a relative signed route plus authenticated actor, tenant,
participant, menu, provider state, and thread state checks. Responses are
private/no-store/nosniff and raw object paths are not browser URLs.

Validation currently permits:

- KTP: JPG/JPEG/PNG/WebP image, maximum 4 MiB.
- NIB: PDF/JPG/JPEG/PNG/WebP, maximum 5 MiB.
- Chat attachment: JPG/JPEG/PNG/WebP/GIF image, maximum 4 MiB.
- Other public image flows generally restrict image MIME/extension and size,
  but remain legacy public storage.

Both MIME/type rule and extension allowlist are used where implemented. SVG and
arbitrary executable/document formats are not accepted by the private flows.
Malware scanning/content disarm is not implemented and remains a production
control gap for accepted PDF/images.

## Storage state

Local `media_private`/`provider_documents` are the safe initial default.
S3-compatible disks are configuration targets only; no bucket, encryption key,
versioning, lifecycle, CORS, or CDN is provisioned here. Public provider,
service, staff, branch, and review imagery still uses legacy `public` disk flows.

Legacy KTP/NIB/chat cutover is allowlisted, checksum-verified, dry-run-first,
and retains source on initial cutover. Retirement is disabled by default,
minimum-aged, privately archived, and requires an exclusive-writer maintenance
window. Follow `docs/runbooks/media-storage-cutover.md`.

## Operational rules

- Never expose `/storage/app/private` through Nginx/static hosting.
- Do not put object keys, signed URLs, or query signatures in logs.
- Validate disk visibility and download authorization after every storage
  configuration change.
- Back up private/public media independently; migration archive is not a backup.
