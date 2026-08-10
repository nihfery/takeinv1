# ADR-005: Laravel Reverb for realtime delivery

- Status: Accepted
- Date: 2026-08-10

## Context

Chat dan in-app notification memerlukan server-originated realtime delivery.
Business commit tidak boleh bergantung pada keberhasilan koneksi WebSocket.

## Decision

Gunakan Reverb sebagai proses terpisah, dipublikasikan melalui
`wss://ws.takein.id` dan dipublish Laravel melalui private `reverb:8080`.
Allowed origin production berupa hostname eksplisit; wildcard, scheme, port,
dan path ditolak. Client events dinonaktifkan. Private channel memakai
authorization actor, tenant, provider status/document/menu, participant, dan
thread lifecycle. Redis DB 6 mendukung scale-out.

## Consequences

Realtime konsisten dengan auth Laravel dan dapat diskalakan terpisah. Outage
Reverb menunda delivery tetapi tidak membatalkan booking/payment. DNS/TLS/edge
WebSocket tetap resource eksternal yang belum diprovisi oleh repository.
