# Database table ownership

MySQL 8/InnoDB is the persistent transaction truth. Ownership below indicates
which module approves schema and invariant changes; foreign keys/relations do
not transfer ownership.

| Owner | Current tables |
| --- | --- |
| Identity | `users`, `admin_profiles`, `personal_access_tokens`, `password_reset_tokens` |
| Customer | `customer_profiles`, `customer_activities` |
| Provider | `provider_profiles`, `provider_roles`, `provider_role_menu_permissions` |
| Branch | `provider_branches` |
| Catalog | `service_categories`, `services` |
| Staff | `provider_staffs`, `staff_skills`, `staff_schedules` |
| Booking | `bookings`, `booking_services`, `booking_participants`, `booking_participant_services` |
| Payment | `payments`, `payment_gateway_transactions` |
| Subscription | `subscription_plans`, `provider_subscriptions` |
| Promotion | `coupons` |
| Review | `branch_reviews`, `staff_reviews` |
| Chat | `chat_threads`, `chat_messages` |
| Notification | `app_notifications` |
| Media | `media_migration_entries`; object paths also live on Provider/Chat rows |
| Audit | `audit_logs` |
| Laravel platform | `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, `failed_jobs` |

Production session/cache/queue target Redis. Laravel platform tables remain in
migration history for compatibility/fallback metadata; their existence does not
authorize silently switching production runtime away from Redis.

## Shared-write rules

- Booking is the only owner allowed to define slot/participant lifecycle.
- Payment may update booking payment-derived status only through its explicit
  transaction/state transition service.
- Availability reads booking/staff/catalog but owns no table.
- Checkout orchestrates Customer/Booking/Promotion/Payment and owns no table.
- Media owns migration manifest and object I/O; Provider/Chat retain ownership
  of their path fields and authorization.
- Audit references many aggregates but never becomes their source of truth.

Historical migrations created `reviews` and `customer_carts`; later migrations
split/renamed them. They are migration history, not current table ownership.
