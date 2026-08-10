# Security policy

## Melaporkan kerentanan

Jangan membuka public issue yang berisi exploit, credential, data pribadi, atau
detail tenant. Gunakan fitur **Private vulnerability reporting / Security
Advisory** pada repository GitHub `nihfery/DITAKEIN`. Jika fitur tersebut tidak
tersedia, hubungi maintainer melalui kanal privat organisasi yang telah
disepakati. Repository ini tidak menetapkan SLA respons publik.

Sertakan versi/commit, surface yang terkena, langkah reproduksi minimum,
dampak, dan bukti yang sudah disanitasi. Jangan mengakses data tenant lain,
menjalankan denial-of-service, atau menguji production tanpa izin tertulis.

## Baseline yang diterapkan

- Laravel Sanctum untuk API authenticated dan session guard terpisah untuk
  admin/provider/provider-branch.
- Scope provider/branch, menu permission, status akun, dan verifikasi dokumen
  diperiksa oleh middleware serta query/action domain.
- Midtrans webhook diverifikasi signature-nya, diambil ulang dari API gateway,
  dicocokkan order/amount/currency, diproses dalam transaksi/lock, dan diaudit.
- Provider KTP/NIB serta chat attachment baru disimpan private dan diunduh
  melalui route pendek bertanda tangan plus pemeriksaan actor/tenant.
- Rate limit tersedia untuk login, registrasi, search, availability, booking,
  payment, provider writes, coupon, dan webhook.
- Structured logging meredaksi password, token, authorization, cookie,
  signature, signed URL, dan field sensitif yang dikonfigurasi.
- Request ID dan correlation ID dihasilkan pada trust boundary; inbound ID
  tidak dipercaya secara default.

Dokumentasi detail:

- [Authentication](docs/security/authentication.md)
- [Authorization](docs/security/authorization.md)
- [Data classification](docs/security/data-classification.md)
- [File security](docs/security/file-security.md)
- [Threat model](docs/security/threat-model.md)
- [Security incident runbook](docs/runbooks/security-incident.md)

## Tanggung jawab deployment

Secret wajib diinjeksi melalui environment/secret manager, bukan Git. Production
wajib HTTPS, `APP_DEBUG=false`, cookie secure/http-only, origin Reverb eksplisit,
dan origin Laravel hanya dapat dijangkau melalui proxy terpercaya. Admin ingress
memerlukan kontrol edge lebih kuat (misalnya identity-aware access), MFA, dan
monitoring; repository saat ini menyediakan auth/RBAC Laravel tetapi tidak
memprovisi edge policy atau MFA eksternal.

Hasil dependency/secret/container scan dari CI adalah signal, bukan pengganti
review. Patch keamanan diterapkan pada branch yang masih dioperasikan; saat ini
tidak ada jadwal dukungan versi formal di luar default branch.
