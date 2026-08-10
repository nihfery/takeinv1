# Deployment Dokploy (tanpa menghapus database)

Konfigurasi proyek ini memakai Docker Compose. Data MySQL berada pada named
volume `youyaku_mysql`, AOF Redis pada `youyaku_redis`, dan file upload pada
`youyaku_storage`. Deploy, rebuild, restart, dan update dari GitHub tidak
menghapus ketiga volume tersebut.

## Konfigurasi awal di Dokploy

1. Buat service bertipe **Docker Compose** dan hubungkan repository ke branch
   `main`.
2. Gunakan `docker-compose.yml` di root repository.
3. Salin nilai dari `platform/deploy/dokploy.env.example` ke tab **Environment** di
   Dokploy, lalu ganti seluruh contoh URL dan secret. Gunakan URL HTTPS tanpa
   trailing slash. Isi `SANCTUM_STATEFUL_DOMAINS` dengan hostname saja, tanpa
   `https://`.
4. Atur domain pada Dokploy ke service dan container port berikut:

   - Laravel HTTP: service `backend-http`, container port `8080`;
   - PHP-FPM: service `backend`, container port `9000` (internal only; jangan
     pasang domain atau host port);
   - Reverb: service `reverb`, container port `8080`;
   - provider: service `provider`, container port `3000`;
   - customer: service `customer`, container port `5174`.

   Compose mengikat host port HTTP Laravel lama (`8000`) ke `127.0.0.1` secara
   default melalui service `backend-http`. Traefik mengakses container port
   `8080` service tersebut melalui network Compose. Listener FastCGI
   `backend:9000` tidak dipublikasikan. Port frontend dapat diubah melalui
   `PROVIDER_HOST_PORT` dan `CUSTOMER_HOST_PORT`.
5. Jalankan **Deploy**. Hanya entrypoint service `backend` yang menjalankan:

   ```sh
   php artisan migrate --force --no-interaction
   ```

   Sebelum pending migration dijalankan, entrypoint membuat SQL dump ke
   `storage/app/deployment-backups` pada volume persisten. Migration atau
   pemeriksaan InnoDB yang gagal akan menghentikan backend dan membuat deployment
   terlihat gagal di log. Setelah pemeriksaan deployment selesai, backend
   menjalankan PHP-FPM di jaringan internal. Service `backend-http` menunggu FPM
   sehat lalu menerima HTTP melalui Nginx. Service `horizon`, `scheduler`, dan
   `reverb` memakai image Laravel yang sama, menunggu backend sehat, lalu
   menjalankan prosesnya masing-masing tanpa migrasi. Tidak ada `migrate:fresh`,
   `db:wipe`, atau `down -v`.

Nginx `backend-http` memasang `youyaku_storage` read-only dan hanya menyajikan
`storage/app/public`; media/dokumen private serta backup deployment tetap di luar
web root. Access log Nginx hanya merekam path `$uri` tanpa query string. Jangan
mengubahnya ke format `combined`, `$request`, `$request_uri`, `$args`, atau
`$query_string`, karena signed URL mengandung token pada query.

Saat start, entrypoint backend memperbaiki ownership/mode hanya pada direktori
writable Laravel yang sudah dibatasi dan berhenti bila menemukan symlink. Ini
memigrasikan file root-owned dari runtime `php -S` lama dengan aman. Horizon,
scheduler, dan Reverb berjalan sebagai `www-data` agar ownership tersebut tetap
stabil. Nginx juga menimpa `REMOTE_ADDR` dan `X-Forwarded-For` dengan alamat peer
langsung; header forwarding dari klien tidak diteruskan ke Laravel.

Image tidak memuat file `.env` dari repository. Seluruh konfigurasi runtime dan
secret disuntikkan oleh Dokploy/Compose; jangan menambahkan `.env` ke Dockerfile
atau menghapus pola proteksi `.dockerignore`.

Gunakan domain HTTPS melalui Dokploy/Traefik sejak deployment pertama. Jangan
mengubah `BACKEND_BIND_ADDRESS` menjadi `0.0.0.0` di production. Nilai
`TRUSTED_PROXIES=*` hanya aman selama invariant ini dijaga: firewall dan mapping
Compose tidak menyediakan jalur langsung menuju origin Laravel. Bila topology
berubah, isi `TRUSTED_PROXIES` dengan IP/CIDR proxy yang eksplisit sebelum origin
dibuka.

Biarkan `OBSERVABILITY_TRUST_INBOUND_IDS=false` sampai ingress HTTP terbukti
menghapus header `X-Request-ID` dan `X-Correlation-ID` dari klien lalu menimpa
keduanya dengan ID buatan ingress. Status proxy tepercaya saja tidak membuktikan
bahwa header tersebut aman untuk dipakai sebagai metadata log/audit.

## Aturan data yang wajib dijaga

- Jangan gunakan aksi Dokploy yang menghapus volume database.
- Jangan menjalankan `docker compose down -v` pada project ini.
- Jangan mengganti `MYSQL_PASSWORD`/`MYSQL_ROOT_PASSWORD` pada volume MySQL yang
  sudah pernah diinisialisasi; perubahan env tidak mengubah password MySQL yang
  sudah tersimpan dan dapat membuat aplikasi tidak dapat terhubung.
- Jangan mengganti `APP_KEY` setelah aplikasi memiliki data terenkripsi atau
  session aktif. Simpan nilai pertama sebagai secret permanen Dokploy.
- Jangan mengganti `REDIS_PASSWORD` tanpa prosedur rotasi terkoordinasi pada
  service dan seluruh proses Laravel. Redis tidak menerbitkan port host, tetapi
  password tetap wajib dan harus disimpan sebagai secret Dokploy.
- Lakukan backup volume/database sebelum perubahan schema besar.

## Setelah push GitHub

Dokploy akan clone commit baru, build image, lalu membuat ulang container. Named
volume tetap dipasang kembali, sehingga migration hanya menambahkan/mengubah
schema yang diperlukan dan data booking lama tetap ada.

Periksa **Deployments** dan log service `backend`, `backend-http`, `horizon`,
`scheduler`, serta `reverb`. Deployment hanya dianggap berhasil bila baris
migration selesai tanpa `SQLSTATE` atau `Migration failed`, Redis berstatus
healthy, FPM menerima koneksi internal, dan HTTP Laravel tetap berjalan. Gunakan
endpoint readiness untuk pemeriksaan dependency database/cache, bukan hanya
healthcheck TCP FPM.

Lakukan pemeriksaan berikut dari luar VPS setelah deployment:

```sh
curl --fail https://api.example.com/api/health
curl --fail https://api.example.com/api/readiness
curl --head --fail https://app.example.com
curl --head --fail https://business.example.com
```

Endpoint health harus mengembalikan JSON dengan `status` bernilai `ok`, sedangkan
readiness harus mengembalikan HTTP 200 hanya ketika dependency runtime siap.

Di host deployment, verifikasi proses dan Redis tanpa menerbitkan port Redis:

```sh
docker compose ps
docker compose exec redis sh -lc \
  'redis-cli --no-auth-warning -a "$REDIS_PASSWORD" ping'
docker compose exec horizon php artisan horizon:status
```

Perintah Redis harus mengembalikan `PONG`. `horizon:status` harus melaporkan
Horizon aktif. Session, cache, queue, rate limiter, metadata Horizon, dan scaling
Reverb memakai logical database Redis yang berbeda; jangan menyatukan nomor DB
tersebut tanpa pemeriksaan migrasi data dan collision key.

## Pemeriksaan jadwal booking

Di terminal service `backend`, jalankan:

```sh
php artisan migrate:status
```

Di terminal service database, pastikan tabel booking memakai InnoDB:

```sh
mysql -uyouyaku -p"$MYSQL_PASSWORD" youyaku -e "
SELECT TABLE_NAME, ENGINE
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('bookings', 'provider_staffs', 'booking_participants');
"
```

Ketiganya harus berstatus `InnoDB`, karena booking memakai database transaction
dan row lock untuk mencegah satu staff menerima dua jadwal yang bertabrakan.
