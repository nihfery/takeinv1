# Arsitektur TAKEIN

TAKEIN adalah modular monolith Laravel dengan dua public web application
Next.js. Laravel tetap menjadi sumber kebenaran transaksi, pemilik database,
REST API, webhook, dashboard Blade, queue, dan realtime authorization.

```text
Customer browser  -> takein.id          -> customer-web (Next.js)
Provider visitor  -> partners.takein.id -> provider-landing (Next.js)
Provider operator -> provider.takein.id -> Laravel Blade
Administrator     -> admin.takein.id    -> Laravel Blade
API clients       -> api.takein.id      -> Laravel REST API
Payment gateway   -> hooks.takein.id    -> Laravel webhook ingress
Realtime clients  -> ws.takein.id       -> Nginx/edge -> Reverb
Public media      -> assets.takein.id    -> planned CDN/public-media origin
                                             (not provisioned here)
```

## Prinsip implementasi

- MySQL 8/InnoDB adalah transaction truth untuk booking dan payment.
- Redis dipisah secara logis untuk default, cache, session, queue, rate limit,
  Horizon metadata, dan Reverb scaling.
- Horizon, scheduler, Reverb, PHP-FPM, dan Nginx berjalan sebagai proses
  terpisah dari image Laravel yang sama bila relevan.
- Dashboard admin/provider tetap Blade dan memakai session server-side; token
  API tidak diekspos ke JavaScript dashboard.
- Modul di `backend/laravel-core/app/Modules` memiliki ownership bisnis.
  Ekstraksi dari controller legacy masih bertahap; `app/Http` tidak boleh
  menjadi tempat logic domain baru.
- Integrasi optional seperti Reverb delivery dan telemetry tidak membatalkan
  commit booking/payment ketika backend eksternal gagal.

## Dokumen detail

- [System context](docs/architecture/system-context.md)
- [Containers](docs/architecture/containers.md)
- [Domain boundaries](docs/architecture/domain-boundaries.md)
- [Module dependencies](docs/architecture/module-dependencies.md)
- [Deployment](docs/architecture/deployment.md)
- [Concurrency validation](docs/architecture/concurrency-validation.md)
- [Table ownership](docs/database/table-ownership.md)
- [Threat model](docs/security/threat-model.md)
- [ADR index](docs/adr/ADR-001-laravel-modular-monolith.md)

Kontrak API ada di `contracts/openapi/v1`. Route file berada di folder `v1`
untuk menandai ownership, tetapi URL existing tetap `/api/*`; belum ada prefix
publik `/api/v1` karena kompatibilitas perilaku dipertahankan.

## Batas yang belum diprovisi

Repository tidak membuktikan keberadaan DNS/TLS, WAF/edge access, Midtrans
production credentials, S3/R2 bucket, CDN `assets.takein.id`, collector OTLP,
Grafana/Loki, atau backup off-site. Konfigurasi template untuk sebagian layanan
itu ada di `platform`, dan setiap aktivasi memerlukan validasi operasional
terpisah. Laravel Pulse sengaja belum diaktifkan; lihat
`docs/adr/0001-defer-laravel-pulse.md`.
