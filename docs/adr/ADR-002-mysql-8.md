# ADR-002: MySQL 8 as transaction truth

- Status: Accepted
- Date: 2026-08-10

## Context

Booking, slot, payment, subscription, dan audit memerlukan constraint,
transaction, dan row lock yang konsisten. Runtime sebelumnya dan migration
existing menggunakan MySQL semantics.

## Decision

Gunakan MySQL 8.0 dengan InnoDB sebagai sumber kebenaran production. Redis tidak
menggantikan database transaction. Deployment check memverifikasi driver,
tabel booking penting, dan engine InnoDB. Migration historis dipertahankan;
perubahan schema baru bersifat additive dan diuji pada MySQL.

## Consequences

Concurrency invariant dapat memakai unique index, transaction, serta
`lockForUpdate`. Test integration memerlukan MySQL terpisah, bukan SQLite.
Operasi backup/restore dan upgrade MySQL menjadi tanggung jawab operasional.
