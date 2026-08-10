# Catalog domain

`Catalog` owns `service_categories` and `services`, public catalog queries,
admin category/service management, and provider service CRUD.

Categories implement a two-level hierarchy with `parent_id`: root categories
and leaf subcategories. The hierarchy endpoint returns active roots that have
active children. New provider form flows validate the selected root/leaf pair
and persist the leaf `category_id`; API responses include leaf and parent names.

Public branches/services require active services, an active and
document-verified provider, and a normalized category relation. A public
service is eligible only when `category_id` points to an active leaf
subcategory whose parent is also active. Uncategorized, root-only, inactive,
non-leaf, and legacy string-only services remain available to provider/admin
management for cleanup but are excluded from every public catalog surface.
Category filters match only the normalized leaf or its parent; the legacy
string `category` is not a public fallback.

The database column remains nullable so existing provider/admin maintenance
flows and historical data stay compatible. Public-query enforcement is the
current integrity boundary; a later data-cleanup migration may make the
database constraint stricter after all legacy rows are normalized.

Service identifiers are scoped to provider. Branch assignment is stored on the
service and resolved through the Branch relation; price/availability are always
revalidated during booking.
