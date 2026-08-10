# ADR-007: Next.js for public web surfaces

- Status: Accepted
- Date: 2026-08-10

## Context

Customer marketplace dan provider marketing memiliki lifecycle/deployment UI
berbeda dari Blade operations, tetapi tetap menggunakan Laravel sebagai backend.

## Decision

Tempatkan customer Next.js di `apps/customer-web` untuk `takein.id` dan provider
landing Next.js di `apps/provider-landing` untuk `partners.takein.id`. Build
menjadi image/service terpisah. Proxy server-side mengarah ke Nginx Laravel HTTP
(`backend-http:8080`) di Compose; frontend tidak berbicara FastCGI.

## Consequences

Public UI dapat dirilis dan diskalakan terpisah. Konfigurasi URL merupakan
build/runtime contract yang harus konsisten dengan Laravel CORS/Sanctum. Laravel
tetap pemilik validation dan transaction; fallback/demo frontend tidak boleh
menjadi sumber kebenaran production.
