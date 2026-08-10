# Contributing

## Alur perubahan

1. Baca [arsitektur](ARCHITECTURE.md), ADR, dan dokumen domain yang disentuh.
2. Buat branch kecil dengan satu tujuan. Jangan commit `.env`, credential,
   database dump, media pengguna, atau output build.
3. Pertahankan URL/route, migration historis, copy UI, dan behavior existing
   kecuali perubahan kontrak disetujui dan diuji.
4. Tempatkan business rule di modul pemiliknya. Controller baru idealnya hanya
   authorize, validate/map input, memanggil action/query, lalu membentuk response.
5. Tambahkan negative authorization test untuk perubahan auth/scope; tambahkan
   concurrency/idempotency test untuk booking/payment yang relevan.
6. Jalankan gate yang sesuai, lalu buka pull request dengan risiko, rollback,
   migration impact, dan bukti validasi.

## Quality gates backend

Dari `backend/laravel-core`:

```bash
composer validate --strict --no-check-publish
composer audit --locked
composer dump-autoload --optimize
php ../../tools/ci/check-pint-baseline.php
php artisan test
php artisan test --testsuite=Concurrency --colors=never
php artisan route:list
php artisan migrate:status
php artisan app:deployment-check
```

The Pint ratchet is the current green style gate: every acknowledged legacy
violation is bound to an exact file hash, while every new or modified PHP file
must pass Pint. A repository-wide `vendor/bin/pint --test` remains the cleanup
target and is expected to stay red until the recorded legacy debt is removed.

PHPUnit memakai database MySQL `salonku_testing_fresh`; concurrency suite
menjalankan child process nyata dengan start barrier dan tidak boleh dijalankan
bersamaan dengan suite lain pada schema yang sama. Lihat langkah pembuatan
database test di [README](README.md). Jangan memakai database development atau
production untuk test dengan `RefreshDatabase`.

Gate parity dari root:

```powershell
pwsh -File tools/validation/compare-routes.ps1
pwsh -File tools/validation/compare-migrations.ps1
pwsh -File tools/validation/validate-http-runtime.ps1
```

Script parity memakai baseline/allowlist yang disimpan repository. Perubahan
route atau migration yang memang disengaja harus direview beserta alasan dan
compatibility impact; jangan memperbarui baseline hanya untuk menyembunyikan
drift.

## Quality gates frontend

Di masing-masing `apps/customer-web` dan `apps/provider-landing`:

```bash
npm ci
npm run build
```

Package saat ini tidak mendefinisikan script lint/test terpisah. Jangan
menuliskan job CI yang memanggil script yang tidak ada.

## Database dan migration

- Jangan mengubah migration historis yang sudah pernah dirilis.
- Tambahkan migration additive dan rollback yang masuk akal; hindari operasi
  data-loss tanpa rencana pemulihan.
- Perubahan booking/payment harus mempertahankan InnoDB, unique constraint,
  transaction, row lock, dan idempotency invariant yang relevan.
- Ikuti [migration policy](docs/database/migration-policy.md).

## Security dan media

Gunakan validation MIME/extension/size yang eksplisit dan disk private untuk
data sensitif. URL sementara tidak menggantikan authorization pada download.
Jangan memperlebar `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, trusted proxy,
atau Reverb origin tanpa threat-model review. Laporkan kerentanan mengikuti
[SECURITY.md](SECURITY.md).

## Pull request checklist

- Behavior existing dan route/migration parity telah dicek.
- Backend test serta build frontend yang terpengaruh lulus.
- Tidak ada secret atau data pengguna pada diff/log/screenshot.
- Migration, deployment, backup, dan rollback impact dijelaskan.
- Dokumen domain/ADR/OpenAPI diperbarui bila kontrak berubah.
- Item eksternal yang belum diprovisi ditandai sebagai future/blocker, bukan
  diklaim sudah aktif.
