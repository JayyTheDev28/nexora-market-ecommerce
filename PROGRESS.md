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
- [ ] Account Management — view/edit profile, change password — not started
- [x] Buyer dashboard (links into the real storefront)

---

## Seller

**Registration → Approval → Login**
- [x] Registration form (base fields + business name, line of business, business permit upload)
- [x] Same email verification + admin approval flow as buyer
- [x] Login, routed to `/seller/dashboard`
- [x] Logout

**Seller Dashboard** — currently a placeholder (shows account info + business profile only)
- [ ] Dashboard overview — stats, charts
- [ ] Manage inventory — add/update/archive products, prices, discounts, vouchers, stock levels (the `products`/`vouchers` tables are ready; no seller-facing CRUD UI)
- [ ] Order notifications — view new orders, review details
- [ ] Accept/confirm order, prepare, pack, print waybill/shipping label
- [ ] Hand over to courier, track shipment status
- [ ] Confirm delivery (notified once buyer receives)
- [ ] Handle customer feedback
- [ ] Generate financial/profit reports (date range)
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

**Logistics Dashboard** — currently a placeholder
- [ ] Rider management — approve/disapprove courier applications, activate/deactivate (per the ERP doc, this is where courier approval actually belongs, not admin)
- [ ] Confirm/approve parcel pickup requests from sellers
- [ ] Management of incoming parcels (Receive → Scan → Read Address)
- [ ] Sorting parcels by destination area
- [ ] Delivery assignment per area/rider — `delivery_areas` table exists, no assignment UI
- [ ] Delivery monitoring
- [ ] Generation of reports
- [ ] Chat/Messaging
- [ ] Account management

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
- [ ] Seller accepts/confirms order → `confirmed`
- [ ] Seller prepares, packs, prints shipping label → `preparing` → `ready_for_pickup`
- [ ] Rider picks up from seller → `picked_up` (needs courier phase)
- [ ] Sorting center receives, scans, sorts, assigns rider → `at_sorting_center` → `sorted` → `assigned_to_rider`
- [ ] Rider delivers to buyer → `out_for_delivery` → `delivered`
- [x] Buyer confirms receipt → `completed`
- [~] Failed delivery / reschedule / return — `delivery_attempts` table exists, no trigger UI yet

Right now, every status transition **between** "placed" and "completed" has
no UI to actually move an order through it — that's the seller/courier/
logistics dashboards above.

---

## Not Yet Covered Anywhere

- [ ] Password reset ("Forgot password?" link exists on the login page but goes nowhere)
- [ ] Chat/Messaging UI (schema ready, listed under every role)
- [ ] Any reports (sales, commission, delivery)
- [ ] Rider/courier registration and dashboard (deferred)

---

## Suggested Order for What's Left

1. **Seller dashboard** — inventory CRUD + order management. Nothing moves past "placed" without this.
2. **Logistics dashboard** — rider approval (unblocks courier registration), parcel sorting, delivery assignment.
3. **Courier registration + dashboard** — the two-stage pickup/delivery flow.
4. **Admin: the rest** — account activate/suspend, seller compliance, dispute resolution, commission/sales reports, announcements.
5. **Chat/Messaging** — cuts across every role, probably easiest once the dashboards that need it (seller↔buyer, courier↔logistics) already exist.
6. **Password reset**, polish, and anything cosmetic.
