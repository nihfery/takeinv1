# TAKEIN / YouYaku

Monorepo reservasi salon dengan Laravel sebagai core permanen, dua public surface
Next.js, serta dashboard admin dan provider yang tetap server-rendered dengan
Blade. Sejumlah label lama `JasaKu`/`SalonKu` masih dipertahankan di kode dan UI
untuk menjaga perilaku produk yang sudah ada.

## Struktur utama

| Path | Tanggung jawab |
| --- | --- |
| `backend/laravel-core` | Laravel 12: REST API, webhook, Blade admin/provider, queue, Reverb |
| `apps/customer-web` | Marketplace customer Next.js, port lokal `5174` |
| `apps/provider-landing` | Landing dan sign-in provider Next.js, port lokal `5173` |
| `platform` | Image runtime, Nginx, deployment example, dan template observability |
| `contracts/openapi/v1` | Kontrak OpenAPI per surface |
| `docs` | Arsitektur, ADR, domain, database, security, dan runbook |
| `tools/validation` | Gate parity dan validasi runtime |

Arsitektur lengkap ada di [ARCHITECTURE.md](ARCHITECTURE.md). Aturan keamanan
ada di [SECURITY.md](SECURITY.md), dan status eksekusi refactor ada di
[PHASE-STATUS.md](PHASE-STATUS.md).

## Mulai cepat dengan Docker

Jalur ini menjalankan topologi lokal yang paling dekat dengan deployment.
Prasyaratnya Git, Docker Engine/Desktop, Docker Compose v2, dan PHP 8.2+ hanya
untuk membuat secret lokal. Port `8000`, `5173`, `5174`, dan `8080` harus bebas.

1. Clone repository dan buat environment lokal:

   ```bash
   git clone https://github.com/nihfery/DITAKEIN.git
   cd DITAKEIN
   cp .env.example .env
   ```

   PowerShell menggunakan `Copy-Item .env.example .env`.

2. Buat nilai acak dan masukkan ke `.env`:

   ```bash
   php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
   php -r 'echo bin2hex(random_bytes(32)).PHP_EOL;'
   ```

   Gunakan hasil pertama untuk `APP_KEY`. Jalankan perintah kedua tiga kali dan
   gunakan hasil yang berbeda untuk `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`,
   dan `REDIS_PASSWORD`.
   File contoh sudah berisi URL dan origin lokal; `.env` sudah masuk
   `.gitignore` dan tidak boleh di-commit.

3. Validasi konfigurasi, build, lalu nyalakan stack:

   ```bash
   docker compose --env-file .env config --quiet
   docker compose --env-file .env up -d --build
   docker compose --env-file .env ps
   ```

   Tunggu hingga `db`, `redis`, `backend`, `backend-http`, `horizon`,
   `scheduler`, `reverb`, `customer`, dan `provider` berstatus sehat. Profile
   `gateway` hanya diperlukan untuk menguji proxy WebSocket Nginx lokal.

4. Jalankan migration secara eksplisit dan periksa runtime. Entrypoint backend
   juga menjalankan pending migration secara aman saat startup, sehingga
   perintah ini idempotent:

   ```bash
   docker compose --env-file .env exec --user www-data backend php artisan migrate --force --no-interaction
   docker compose --env-file .env exec --user www-data backend php artisan app:deployment-check
   ```

5. Opsional, pulihkan snapshot data demo hanya pada database testing:

   ```bash
   docker compose --env-file .env exec --user www-data backend php artisan db:seed --force
   ```

   Seeder ini menghapus seluruh data tabel aplikasi selain riwayat `migrations`,
   lalu memulihkan snapshot `database/seeders/data/youyaku.sql`. Jangan jalankan
   pada database staging atau production yang menyimpan data nyata. Snapshot
   menyediakan 20 provider, 100 cabang, 750 layanan, dan 20 booking. Login demo:
   admin `admin@gmail.com` / `admin12345`; provider contoh
   `provider-cantika-beauty-salon@directory.test` / `salon12345`; customer
   `customer@gmail.com` / `customer12345`.

## Membuka seluruh surface

| Surface | URL lokal |
| --- | --- |
| Customer Next.js | `http://127.0.0.1:5174` |
| Provider landing Next.js | `http://127.0.0.1:5173` |
| Provider Blade sign-in/dashboard | `http://127.0.0.1:8000/provider/login` / `/provider/dashboard` |
| Admin Blade sign-in/dashboard | `http://127.0.0.1:8000/admin/login` / `/admin/dashboard` |
| Liveness / readiness | `http://127.0.0.1:8000/api/health` / `/api/readiness` |

Dashboard Blade mengharuskan login. Root backend (`/`) diarahkan ke login admin.
Backend HTTP hanya diekspos ke loopback oleh Compose; MySQL dan Redis tidak
memiliki port publik.

## Menjalankan test

Suite backend membutuhkan database MySQL terpisah bernama
`salonku_testing_fresh`. Jangan menunjuk test ke database development. Buat dan
beri hak database test melalui container MySQL.

Bash:

```bash
printf "%s\n" "CREATE DATABASE IF NOT EXISTS salonku_testing_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON salonku_testing_fresh.* TO 'youyaku'@'%'; FLUSH PRIVILEGES;" \
  | docker compose --env-file .env exec -T db sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot'
```

PowerShell:

```powershell
"CREATE DATABASE IF NOT EXISTS salonku_testing_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON salonku_testing_fresh.* TO 'youyaku'@'%'; FLUSH PRIVILEGES;" |
  docker compose --env-file .env exec -T db sh -lc 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot'
```

Image production tidak membawa dependency development. Perintah berikut
memasang dependency dev hanya di container sementara, lalu menjalankan suite:

```bash
docker compose --env-file .env run --rm \
  -e APP_ENV=testing \
  -e BCRYPT_ROUNDS=4 \
  -e CACHE_STORE=array \
  -e MAIL_MAILER=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -e ALLOW_CUSTOMER_MANUAL_PAYMENT_CONFIRMATION=true \
  -e OBSERVABILITY_TELEMETRY_ENABLED=false \
  -e DB_DATABASE=salonku_testing_fresh \
  backend sh -lc 'composer install --prefer-dist --no-interaction && php artisan test'
```

Gate concurrency wajib dijalankan terpisah pada MySQL yang sama:

```bash
docker compose --env-file .env run --rm \
  -e APP_ENV=testing \
  -e BCRYPT_ROUNDS=4 \
  -e CACHE_STORE=array \
  -e MAIL_MAILER=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  -e ALLOW_CUSTOMER_MANUAL_PAYMENT_CONFIRMATION=true \
  -e OBSERVABILITY_TELEMETRY_ENABLED=false \
  -e DB_DATABASE=salonku_testing_fresh \
  backend sh -lc 'composer install --prefer-dist --no-interaction && php artisan test --testsuite=Concurrency --colors=never'
```

Test tersebut memakai beberapa child process dan filesystem start barrier,
bukan simulasi sequential. Lihat [laporan concurrency](docs/architecture/concurrency-validation.md).

Validasi frontend:

```bash
cd apps/customer-web && npm ci && npm run build
cd ../provider-landing && npm ci && npm run build
```

Tidak ada script lint/test frontend terpisah pada package saat ini; build Next
adalah gate frontend yang tersedia. Perintah quality dan kontribusi lainnya ada
di [CONTRIBUTING.md](CONTRIBUTING.md).

## Pengembangan native (opsional)

Untuk iterasi tanpa Docker, gunakan PHP 8.2+, Composer 2, Node.js yang didukung
Next.js 16, MySQL 8, Redis, serta extension `pdo_mysql`, `mbstring`, `pcntl`, dan
PhpRedis. Dari `backend/laravel-core`, jalankan `composer install`, salin
environment root menjadi `.env`, lalu `php artisan migrate` dan
`php artisan serve --host=0.0.0.0 --port=8000`. Jalankan masing-masing frontend
dengan `npm ci && npm run dev`. Konfigurasi URL frontend tersedia pada
`.env.example` di folder aplikasi masing-masing.

## Production

Production menggunakan host `takein.id`, `partners.takein.id`,
`provider.takein.id`, `admin.takein.id`, `api.takein.id`, `hooks.takein.id`,
`ws.takein.id`, dan `assets.takein.id`. Repository menyediakan konfigurasi dan
template, tetapi tidak memprovisi DNS, TLS, edge policy, bucket S3/R2, CDN,
collector OpenTelemetry, Grafana, atau backup off-site. Ikuti
[runbook deployment](docs/runbooks/deployment.md) dan jangan menganggap template
tersebut sebagai resource eksternal yang sudah aktif.

Untuk berhenti tanpa menghapus data:

```bash
docker compose --env-file .env down
```

Jangan memakai `down -v` kecuali reset penuh database, Redis, dan media lokal
memang telah disetujui; named volume merupakan data persisten.
