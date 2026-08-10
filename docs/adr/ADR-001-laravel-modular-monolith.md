# ADR-001: Laravel modular monolith

- Status: Accepted
- Date: 2026-08-10

## Context

Laravel telah menjadi pemilik booking, payment, provider/admin Blade, API,
webhook, dan database. Memecahnya menjadi service jaringan saat refactor akan
menambah failure mode dan berisiko mengubah behavior existing.

## Decision

Pertahankan satu Laravel enterprise core di `backend/laravel-core`. Kelompokkan
business capability di `app/Modules/<Domain>` dengan lapisan Domain,
Application, Infrastructure, dan Presentation seperlunya. `Shared` hanya untuk
primitive teknis lintas domain. Ekstraksi controller legacy dilakukan bertahap
dengan route/migration parity dan test.

## Consequences

Transaction lintas booking/payment tetap lokal dan sederhana, deployment serta
debugging tetap satu unit. Batas modul membutuhkan review karena belum ada
enforcement build-time. Scaling proses queue/Reverb dapat dilakukan terpisah,
tetapi scaling business service independen tidak tersedia tanpa ADR baru.
