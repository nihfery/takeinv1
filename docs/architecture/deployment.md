# Deployment architecture

## Host responsibility

| Host | Runtime | Catatan boundary |
| --- | --- | --- |
| `takein.id` | `apps/customer-web` | Public customer UI |
| `partners.takein.id` | `apps/provider-landing` | Public provider marketing/sign-in UI |
| `provider.takein.id` | Laravel Blade | Session host-only lebih disukai |
| `admin.takein.id` | Laravel Blade | Tambahkan edge access policy/MFA/RBAC monitoring |
| `api.takein.id` | Laravel REST | Sanctum/cookie atau token existing sesuai endpoint |
| `hooks.takein.id` | Laravel webhook | Batasi path ke ingress webhook; signature tetap wajib |
| `ws.takein.id` | Nginx/edge -> Reverb | Upgrade WebSocket, explicit allowed origins |
| `assets.takein.id` | Future CDN/public media | Belum diprovisi dalam repository |

Satu image Laravel digunakan oleh PHP-FPM, Horizon, scheduler, dan Reverb,
namun setiap proses mempunyai service/lifecycle terpisah. `backend-http` Nginx
memiliki kontrak HTTP dan FastCGI ke `backend:9000`. MySQL dan Redis hanya pada
network data internal.

## Configuration contract

- `APP_URL=https://api.takein.id`.
- `CUSTOMER_FRONTEND_URL=https://takein.id`.
- `PROVIDER_FRONTEND_URL=https://partners.takein.id`.
- Session/cache/queue memakai Redis; cookie secure/http-only/SameSite Lax.
- Jangan otomatis memakai `SESSION_DOMAIN=.takein.id`.
- `SANCTUM_STATEFUL_DOMAINS` hanya memuat browser/API host yang benar-benar
  memerlukan cookie stateful.
- Public Reverb adalah `ws.takein.id:443/https`; publish hop internal adalah
  `reverb:8080/http`.
- Secret dan credential berasal dari orchestrator/secret manager.

## Artifact dan rollout

CI memublikasikan image immutable bertag commit SHA untuk Laravel dan
`backend-http`; kedua artifact backend itu dipromosikan dengan digest/tag yang
sama. Image customer/provider tidak dipublikasikan karena konfigurasi publik
Next.js dibake saat build. Workflow deployment memverifikasi exact commit SHA
dan worktree bersih, lalu membangun kedua frontend di target dengan environment
yang disetujui. Deploy staging lebih dulu, jalankan migration backup + migrate +
deployment-check, health/readiness, queue, Reverb negative/positive auth, dan
smoke test semua surface. Workflow tidak boleh berisi secret palsu.

Detail operasional dan rollback ada di:

- `docs/runbooks/deployment.md`
- `docs/runbooks/rollback.md`
- `docs/database/backup-restore.md`
- `docs/runbooks/reverb.md`
- `docs/runbooks/media-storage-cutover.md`

## External boundary

Compose/Dokploy template bukan bukti provisioning. DNS, certificate, edge
routing, admin access policy, object storage/CDN, OTLP collector, dashboard,
alert receiver, email delivery, Midtrans production activation, serta backup
off-site harus dicatat dan diuji oleh platform owner sebelum go-live.
