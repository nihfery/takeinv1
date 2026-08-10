# System context

## Actors dan sistem eksternal

| Actor/sistem | Interaksi dengan TAKEIN |
| --- | --- |
| Customer | Mencari branch/service, mengelola activity/cart, booking, pembayaran, profile, dan review melalui customer Next.js/API |
| Provider owner | Onboarding, dokumen, branch, service, staff, jadwal, booking, payment view, subscription, chat melalui landing + Blade/API |
| Provider branch account | Mengoperasikan branch yang ditetapkan dengan menu permission dan tenant scope |
| Administrator | Moderasi provider/dokumen, katalog, booking, coupon, support, user, dan dashboard melalui Blade/API admin |
| Midtrans | Charge/status API outbound dan notification webhook inbound |
| MySQL 8 | Sumber kebenaran transaksi dan audit |
| Redis | Session/cache/queue/rate-limit/Horizon/Reverb coordination |
| Object storage | Target optional S3/R2-compatible; belum diprovisi oleh repository |
| Edge/DNS/TLS/CDN | Tanggung jawab platform eksternal; belum diprovisi oleh repository |
| OTLP/Grafana/Loki | Template optional observability; endpoint eksternal tidak diklaim tersedia |

## Trust boundaries

```text
Internet
  -> HTTPS edge / access policy / rate protection
  -> public Next.js or private Laravel HTTP gateway
  -> Laravel application authorization
  -> MySQL + Redis + local/private media networks

Midtrans
  -> hooks.takein.id
  -> signature check + authoritative status fetch
  -> transactional state transition + audit
```

Laravel origin harus private. `backend-http` Nginx menerima HTTP pada container
port `8080` dan meneruskan FastCGI ke `backend:9000`. Container PHP-FPM bukan
HTTP server. Compose hanya memetakan origin ke host loopback `127.0.0.1:8000`.

## Production hosts

- `takein.id`: customer Next.js.
- `partners.takein.id`: provider public landing Next.js.
- `provider.takein.id`: provider Laravel Blade.
- `admin.takein.id`: admin Laravel Blade, dengan kontrol ingress tambahan.
- `api.takein.id`: external REST API Laravel.
- `hooks.takein.id`: external webhook ingress Laravel.
- `ws.takein.id`: Reverb WebSocket melalui edge/Nginx.
- `assets.takein.id`: rencana CDN/public media; belum diprovisi.

Session cookie default-nya host-only. Shared cookie `.takein.id` bukan asumsi
arsitektur dan hanya boleh diaktifkan setelah threat-model review.
