# Nexora Market — Development Checklist

Checked against the ERP-Components and ERP-Flow docs, and the actual code
as of commit `f3c84df` (29-cart-checkout-orders-on-database).

Legend: `[x]` built and working · `[~]` schema/backend exists, no UI yet · `[ ]` not started

---

## Foundation / Infrastructure

- [x] Laravel + Blade + Tailwind + Alpine.js + Vite stack
- [x] Git repository, connected to GitHub
- [x] MySQL configured, full schema (20 migrations, every role covered)
- [x] Design system (colors, type scale, spacing, reusable components)
- [x] Real email delivery (Gmail SMTP)

---

## Public Storefront

- [x] Landing page — hero, featured sellers, categories, promo banner, new arrivals, trending, reviews, final CTA
- [x] Navbar + footer (shared across the whole site)
- [x] Catalog page — category filter + search, reads real `products` table
- [x] Product detail page — gallery, color/size variants, stock indicator
- [x] Products, categories, and demo sellers seeded in the database

---

## Buyer

**Registration → Approval → Login**
- [x] Registration form (name, sex, birthday, auto-age, email, contact, PSGC address, ID upload)
- [x] Real email verification
- [x] Admin approval gate (blocks login until approved)
- [x] Login (blocked on unverified / pending / disapproved / suspended)
- [x] Logout

**Main Menu**
- [x] Browse categories, search — `/catalog`
- [x] View product details, choose quantity/variations, add to cart — `/products/{id}`
- [x] View cart, select items, quantity adjusters, apply voucher, choose payment, place order — `/cart`, `/checkout`
- [x] View order status — To Ship / In Transit / Out for Delivery / Completed tabs, with a timeline widget mapping all 13 ERP statuses
- [x] Confirm Receipt, Leave Feedback (writes to `reviews`), Raise Dispute (writes to `disputes`)
- [ ] Chat/Messaging — schema exists (`conversations`, `messages`), no UI
- [x] Account Management — edit profile, edit address (PSGC dropdowns), change password
- [x] Buyer dashboard — real stats (cart/active/completed orders), recent orders, menu (Overview / My Orders / My Account)

---

## Seller

**Registration → Approval → Login**
- [x] Registration form (base fields + business name, line of business, business permit upload)
- [x] Same email verification + admin approval flow as buyer
- [x] Login, routed to `/seller/dashboard`
- [x] Logout

**Seller Dashboard & Operations Hub** — complete, fully integrated, database-backed ERP seller subsystem
- [x] **Store Profile Management & Public Storefront** — `/seller/store`, `/stores/{seller}`: store branding (name, description, logo, banner, contact email/phone), public storefront page with catalog filtering and vouchers, vacation mode toggle with custom notice, and strict cart/checkout blocking during vacation.
- [x] **Store Category Management** — `/seller/categories`: hierarchical seller store categories (parent/subcategories, order index, visibility toggle, duplicate prevention, and safe deletion unlinking).
- [x] **Product Management & Catalog** — `/seller/products`: full CRUD, draft vs publish, category assignment, SKU, dimensions, weights, multi-image gallery upload, duplicate product action, archive/reactivate, bulk operations, and deletion protection against active orders.
- [x] **Product Variants Matrix** — variation options (size, color, custom attributes), price/SKU/stock matrix synchronization, catalog minimum pricing, and buyer variant selection.
- [x] **Inventory Management & Low-Stock Alerts** — `/seller/inventory`: stock tracking, manual adjustments (`set`, `add`, `subtract`) with audit reasons, low-stock threshold customization, automatic out-of-stock badges, and real-time synchronization.
- [x] **Product Discounts & Flash Sales** — `/seller/promotions`: compare-at strikethrough pricing, scheduled flash sales with server-side price validation, countdown indicators, and manual early termination.
- [x] **Store Vouchers** — `/seller/vouchers`: percentage and flat discounts, minimum spend, maximum discount caps, usage limits, expiration dates, activation toggles, buyer voucher claiming, and claims audit viewer (`/seller/vouchers/claims`).
- [x] **Order Processing & Printable Waybills** — `/seller/orders`: order search, ERP status tabs, date filters, timeline history, seller order cancellation with automatic inventory stock restoration, and printable A6 logistics waybills with sender/recipient manifests and COD barcodes (`/seller/orders/{order}/waybill`).
- [x] **Returns & Refunds Arbitration** — `/seller/returns`: return dispute review, photo/video evidence inspection, approval with return packaging instructions and optional inventory restock, rejection with validated reasons.
- [x] **Customer Reviews & Feedback** — `/seller/feedback`: ratings breakdown, filter by star rating and reply status, merchant replies with timestamps, and public display of seller replies on buyer product show page.
- [x] **Buyer-Seller Real-time Chat** — `/seller/chat`: persistent threaded messaging between buyers and sellers, unread tracking, and media/file attachments support.
- [x] **Financial Reports & Payout Requests** — `/seller/reports`: gross sales, platform commission tracking, net earnings, available balance vs pending settlement, and payout withdrawal request system with validation (`/seller/payouts/request`).
- [x] **Seller Dashboard Hub & Navigation** — `/seller/dashboard`: real-time KPI metrics, vacation notice alert, actionable order queues, pending dispute alerts, quick launchpad, and updated navigation bar with badge counts.

---

## Courier / Rider

- [ ] **Explicitly deferred at your request** — registration, login, and dashboard not started
- [~] Schema is ready: `courier_profiles` table (vehicle, plate number, OR/CR, license), `pickup_courier_id`/`delivery_courier_id` on orders, `delivery_attempts` table for failed/rescheduled deliveries

---

## Logistics / Sorting Center

**Registration → Approval → Login**
- [x] Registration form (base fields + business name, business/DTI permit upload)
- [x] Same email verification + admin approval flow
- [x] Login, routed to `/logistics/dashboard`
- [x] Logout

**Logistics Dashboard** — real, database-driven operational sorting-center management system
- [x] Rider management — approve/disapprove courier applications, activate/deactivate, zone assignment, live workload monitoring
- [x] Confirm/approve parcel pickup requests from sellers — assign pickup rider, physical collection confirmation, reject with reason
- [x] Management of incoming parcels (Receive → Scan → Read Address) — barcode scanner check-in, tracking lookup, address validation
- [x] Sorting parcels by destination area — automatic municipality matching, manual override with reason, batch sorting
- [x] Delivery assignment per area/rider — live courier workload, zone eligibility enforcement, concurrency-safe dispatch
- [x] Delivery monitoring — in-flight tracking, failure exceptions resolution (reschedule or return to seller)
- [x] Generation of reports — date-range filters, volume throughput, zone breakdown, rider delivery performance
- [~] Chat/Messaging — schema exists (`conversations`, `messages`), documented integration point
- [x] Account management — sorting center profile, facility physical address, password management

---

## Admin

- [x] Login, routed to `/admin/dashboard`
- [x] Logout
- [x] **Manage account registrations** — real, functional: dedicated applications table, view uploaded ID/permits, approve/decline with reason, applicant notified by email either way
- [x] **View dashboard** — executive platform overview, real-time KPI metrics (GMV, take-rate, orders, active users), active disputes, pending applications, and recent audit logs
- [x] **Manage user accounts** — activate/suspend/deactivate with required reason, session eviction enforcement, user notifications, and full audit logging
- [x] **Monitor seller compliance** — issue compliance warnings with violation types, link to infringing products, option to auto-archive listings, and seller notifications
- [x] **Manage complaints and disputes** — full arbitration board: inspect buyer complaints, review uploaded photo/video evidence gallery, resolve or reject disputes, and option to transition order to returned/refunded
- [x] **Manage commission (10%)** — platform commission tracking, pending vs settled amounts, individual settlement and one-click batch settlement
- [x] **Generate reports** — sales summary, commission analytics, merchant volume rankings, order status pipeline distribution, and downloadable CSV exports
- [x] **Manage platform settings** — dynamic configuration of platform name, commission rate, return window, support contacts, and maintenance mode; broadcast announcements with target role filters
- [ ] Chat/Messaging — cross-role messaging schema exists
- [x] **Account management** — administrator profile updates and security password management

---

## Order Lifecycle (ERP-Flow doc)

The full 13-status lifecycle is correctly modeled as the `orders.status` enum
end to end: `placed → confirmed → preparing → ready_for_pickup → picked_up →
at_sorting_center → sorted → assigned_to_rider → out_for_delivery →
delivered → completed` (plus `delivery_failed` / `returned`).

- [x] Buyer places order (creates real `Order` + `OrderItem` rows, in a DB transaction)
- [x] Seller accepts/confirms order → `confirmed`
- [x] Seller prepares, marks ready for pickup → `preparing` → `ready_for_pickup`
- [~] Rider picks up from seller → `picked_up` — currently a manual seller-side button standing in for this, since the courier role doesn't exist yet to confirm pickup themselves
- [x] Sorting center receives, scans, sorts, assigns rider → `at_sorting_center` → `sorted` → `assigned_to_rider`
- [ ] Rider delivers to buyer → `out_for_delivery` → `delivered`
- [x] Buyer confirms receipt → `completed`
- [x] Failed delivery / reschedule / return — `delivery_attempts` table integrated with sorting center monitoring and resolution UI

Every transition now has a real path from `placed` through `picked_up`
(seller dashboard). From `picked_up` onward — sorting center receiving/
sorting/assigning, and courier pickup/delivery — still has no UI; that's
the logistics dashboard and courier role, next up per the suggested order
below. Print waybill/shipping label (part of "prepare orders" in the ERP
doc) also isn't built yet — "Mark Ready for Pickup" updates status but
doesn't generate a label.

---

## Not Yet Covered Anywhere

- [ ] Password reset ("Forgot password?" link exists on the login page but goes nowhere)
- [ ] Chat/Messaging UI (schema ready, listed under every role)
- [ ] Any reports (sales, commission, delivery)
- [ ] Rider/courier registration and dashboard (deferred)

---

## Suggested Order for What's Left

1. ~~**Seller dashboard**~~ — done (inventory CRUD, order management through `picked_up`, reports, feedback).
2. **Logistics dashboard** — rider approval (unblocks courier registration), incoming parcel management, sorting, delivery assignment per area/rider.
3. **Courier registration + dashboard** — the two-stage pickup/delivery flow; this is what actually drives `picked_up → at_sorting_center → ... → delivered`.
4. ~~**Admin: the rest**~~ — done (dashboard KPIs, account activate/suspend, seller compliance, dispute resolution, commission settlements & reports, platform settings & announcements, immutable audit trail).
5. **Chat/Messaging** — cuts across every role, probably easiest once the dashboards that need it (seller↔buyer, courier↔logistics) already exist.
6. **Password reset**, shipping label generation, voucher creation UI, polish.
