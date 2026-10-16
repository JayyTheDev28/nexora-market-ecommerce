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

**Seller Dashboard** — real now, not a placeholder
- [x] Dashboard overview — stat cards (active products, low stock, new orders, revenue this month) + recent orders list. Charts not built (numbers only).
- [x] Manage inventory — add/edit/archive/activate products, prices, compare-at price, stock, status filters, search, low-stock flag
- [x] Order notifications — new-orders count badge, orders list grouped new/active/shipped
- [x] Accept/confirm order, prepare, mark ready for pickup — real status transitions with a history timeline; buyer is emailed on every change
- [~] Hand over to courier, track shipment — "confirm pickup" is a manual seller action standing in for real courier confirmation until that role exists; no live tracking yet
- [x] Confirm delivery (seller notified) — buyer's Confirm Receipt is unchanged; seller side still needs its own "notified" surface beyond email
- [x] Handle customer feedback — reviews list + average rating, scoped to their own products
- [x] Generate financial/profit reports — date range picker, gross sales, 10% commission, net payout, orders-by-status
- [ ] Vouchers/discounts — `vouchers` table exists, no seller-facing UI to create them yet
- [ ] Chat/Messaging
- [ ] Account management

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
- [x] **Manage account registrations** — real, functional: pending applications table, view uploaded ID/permits, approve/decline, applicant notified by email either way
- [ ] View dashboard — platform overview, stats, notifications (only the approvals table exists so far)
- [~] Manage user accounts — activate/suspend/deactivate: `account_status` column and full login/middleware enforcement already work; no admin UI to actually toggle it yet
- [~] Monitor seller compliance — `seller_warnings` table exists; no UI to issue warnings or review category mismatches
- [~] Manage complaints and disputes — buyers can already raise disputes (`/buyer/orders`); no admin UI to review/resolve them
- [~] Manage commission (10%) — every order automatically creates a `Commission` record at checkout; no admin UI to view/report on them
- [ ] Generate reports — sales summary, commission report
- [~] Manage platform settings — `announcements` and `platform_settings` tables exist; no UI to post/edit
- [ ] Chat/Messaging
- [ ] Account management

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
4. **Admin: the rest** — account activate/suspend, seller compliance, dispute resolution, commission/sales reports, announcements.
5. **Chat/Messaging** — cuts across every role, probably easiest once the dashboards that need it (seller↔buyer, courier↔logistics) already exist.
6. **Password reset**, shipping label generation, voucher creation UI, polish.
