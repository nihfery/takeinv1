# Checkout domain

Checkout is the ownership boundary for quote construction, promotion
application, and quote finalization. It intentionally owns no table yet.

The current implementation remains distributed across customer activity/cart
compatibility code, `BookingFlowService`, `CouponService`, and Payment to
preserve behavior. There is no duplicate pricing engine in the Checkout module.
Customer payload totals are untrusted hints; server-side service price, coupon,
availability, participant, and payment rules are recalculated before commit.

Future extraction should move one tested use case at a time behind Application
actions/DTOs without changing public URLs or persisting a second quote truth.
