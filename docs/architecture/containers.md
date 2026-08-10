# Container architecture

Root `docker-compose.yml` mendefinisikan runtime berikut.

| Service | Image/proses | Network/data | Port host default |
| --- | --- | --- | --- |
| `db` | `mysql:8.0` | internal `youyaku_data`, volume `youyaku_mysql` | tidak diekspos |
| `redis` | `redis:7.4-alpine`, AOF, `noeviction` | internal `youyaku_data`, volume `youyaku_redis` | tidak diekspos |
| `backend` | Laravel image, PHP-FPM | app + data network, volume storage | private `9000` |
| `backend-http` | dedicated Nginx FastCGI | app network, storage read-only | `127.0.0.1:8000 -> 8080` |
| `horizon` | `php artisan horizon` | app + data network | tidak diekspos |
| `scheduler` | `php artisan schedule:work` | app + data network | tidak diekspos |
| `reverb` | `php artisan reverb:start` | app + data network | `127.0.0.1:8080 -> 8080` |
| `customer` | standalone Next.js | app network | `5174` |
| `provider` | standalone Next.js | app network | `5173` |
| `nginx` | optional WebSocket gateway profile | app network | `127.0.0.1:8088` |

## Runtime invariants

- Hanya service `backend` menjalankan migration entrypoint. Sidecar Laravel
  berjalan sebagai `www-data` dan `RUN_DATABASE_MIGRATIONS=false`.
- Entrypoint membuat gzip `mysqldump` sebelum pending migration ketika
  `DEPLOY_BACKUP_BEFORE_MIGRATE=true`, lalu menjalankan migration dan
  `app:deployment-check`.
- Writable tree storage dinormalisasi secara terbatas dan ditolak jika memuat
  symlink. Nginx hanya me-mount storage read-only.
- `backend-http` dan PHP-FPM tidak menulis query string ke access log. Header
  proxy/client ID ditimpa pada boundary internal.
- Queue `retry_after` harus lebih besar daripada Horizon worker timeout.
- Named volume bersifat persisten; redeploy normal tidak menggunakan `down -v`.

## Health model

- FPM health: koneksi TCP lokal port `9000`.
- HTTP gateway: `GET /api/health`.
- Readiness: `GET /api/readiness`, memeriksa MySQL dan Redis connection yang
  benar-benar dipakai cache/session/queue/rate-limit/Horizon.
- Horizon: `horizon:status`.
- Reverb: listener TCP port `8080`.
- Next.js: respons HTTP di bawah status 500.

`/api/health` menyentuh MySQL dan bukan pure process-only liveness. Operational
probe harus memahami perbedaan ini saat menentukan restart policy.
