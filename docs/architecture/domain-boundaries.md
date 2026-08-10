# Domain boundaries

Laravel modules berada di `backend/laravel-core/app/Modules`. Struktur umum yang
dipakai adalah `Domain`, `Application`, `Infrastructure`, dan `Presentation`,
tetapi tidak setiap modul membutuhkan keempat lapisan.

| Domain | Ownership utama |
| --- | --- |
| Identity | User, login/register, role/guard |
| Customer | Profile dan saved customer activity |
| Provider | Provider profile, onboarding, document lifecycle, role/menu entitlement |
| Branch | Provider branch dan assigned branch scope |
| Catalog | Category hierarchy dan provider service |
| Staff | Staff, skill, dan schedule persistence |
| Availability | Conflict checking dan eligible-staff resolution |
| Booking | Booking lifecycle, participant, selected service, admin/provider/customer presentation |
| Checkout | Orkestrasi checkout boundary; persistence masih dimiliki Customer/Booking/Payment |
| Payment | Payment, gateway transaction, Midtrans integration/webhook |
| Subscription | Plan dan provider subscription entitlement/payment state |
| Promotion | Coupon management dan validation |
| Review | Branch/staff reviews |
| Notification | In-app notification dan broadcast event |
| Chat | Thread/message/access rule/realtime event |
| Support | Admin-provider support/ticket web workflows |
| Media | Storage contract, private delivery, legacy object migration manifest |
| Audit | Security/business audit event persistence |

## Boundary rules

- Module lain boleh mengacu pada public Application service/action/query atau
  model yang saat ini menjadi compatibility seam; dependency cycle baru harus
  dihindari.
- `Shared` hanya untuk primitive teknis lintas domain. Saat ini folder tersebut
  tidak menampung business rule.
- MySQL relation lintas domain tidak mengalihkan ownership tabel.
- Availability membaca Catalog/Staff/Booking tetapi tidak memiliki tabel.
- Checkout belum memindahkan seluruh cart/checkout controller legacy; jangan
  menganggap folder modul kosong sebagai implementasi penuh.
- `app/Http/Controllers/Api/Customer/CartController.php` dan beberapa admin
  controller masih merupakan compatibility code. Refactor selanjutnya harus
  kecil, parity-tested, dan tidak mengubah route.
- Registrar partner API tersedia sebagai reserved boundary tetapi saat ini
  mendaftarkan nol route; jangan mengklaim partner API sudah aktif.

Dokumen setiap domain berada di `docs/domains`. Ownership tabel rinci ada di
`docs/database/table-ownership.md`.
