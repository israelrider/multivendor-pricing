## Multi-Vendor Order Processing with Dynamic Pricing Engine

### Quick start (Windows localhost, native, no Docker)

**Prerequisites:** PHP 8.3+ (built and tested on 8.4), Composer, MySQL 8, Node 22 and pnpm. You don't need Redis,
IIS or XAMPP, because the queue uses the same MySQL database.

**PHP extensions:** `pdo_mysql` for the app and `pdo_sqlite` for the tests, on top of what Laravel needs (`mbstring`, `openssl`,
`fileinfo`, …). They are declared in `backend/composer.json`, so `composer install` names any that are missing, and
`composer check-platform-reqs` lists them at any time. On Windows, uncomment `extension=pdo_mysql` and `extension=pdo_sqlite` in
`php.ini` (`php --ini` shows which file). On Linux, install the packages, e.g. `apt install php8.4-mysql php8.4-sqlite3`.

```powershell
# one-time setup (from the repo root)
mysql -u root -p -e "CREATE DATABASE multivendor_pricing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

cd backend
composer install
copy .env.example .env               # then set DB_USERNAME / DB_PASSWORD
php artisan key:generate
php artisan migrate --seed           # tables, queue tables and demo data

cd ..\frontend
pnpm install
```

Then run three terminals:

| Terminal | Folder      | Command                | What it does |
|----------|-------------|------------------------|--------------|
| 1        | `backend/`  | `php artisan serve`    | API on http://localhost:8000 |
| 2        | `backend/`  | `php artisan queue:work` | Runs `NotifyVendorJob` for each sub-order |
| 3        | `frontend/` | `pnpm dev`             | App on http://localhost:5173, with `/api` proxied to :8000 (no CORS setup) |

Open **http://localhost:5173**. Vendor notifications are written to `backend/storage/logs/laravel.log` as `Vendor notified` entries.

**Tests:** run `php artisan test` in `backend/`. They use in-memory SQLite (set in `phpunit.xml`), so they need no database server
and never touch the MySQL data.

**Other useful commands:**

- `php artisan migrate` after pulling new code applies new migrations (e.g. the `idempotency_key` columns on `orders`). It only
  adds, so existing data stays.
- `php artisan db:seed` resets the demo catalog and customers. The seeders match rows on natural keys, so rerunning them is safe.
- `pnpm build` type-checks the frontend and builds it. `pnpm lint` runs oxlint.

These are plain artisan and pnpm commands, so every step is visible. Composer scripts and Docker are not set up yet.

### Walkthrough

The seed data has 3 vendors, 4 categories, 10 products and 3 customers. Each step below shows one rule, and you can check every
result on the Cart and Review pages. Use the **Shopping as** selector in the header to switch between customers.

**Discount rules** (`backend/config/discounts.php`): quantity ≥ 5 gives 5% and quantity ≥ 10 gives 10%. Electronics get 8% and
Books get 5%. Loyal customers get 3%. The single best discount applies to each line, and the others show as "also eligible".

1. **Lowest price wins.** The products page shows the best offer per product. *Wireless Mouse* goes to Brightline at $22.99, the
   cheapest of three vendors.
2. **Tie-break.** *USB-C Charger 65W* costs $39.99 at both Acme and Corner Market. Acme wins because it has the lower vendor id.
3. **Inactive offers are ignored.** *Cast Iron Skillet* goes to Corner Market at $34.99, not to Acme's cheaper $29.99 offer,
   which is inactive.
4. **Loyalty.** Shop as **Alex Morgan** (loyal) and add *Wooden Puzzle*, a toy with no category discount: Loyalty 3% applies.
   Switch to **Jamie Lee**, and the discount disappears.
5. **Best discount wins.** As Alex, add *Clean Code*. Books 5% applies, and Loyalty 3% shows as also eligible.
6. **Quantity tiers.** Set *Wireless Mouse* to 5: Electronics 8% still beats the 5% tier. Raise it to 10, and the 10% tier takes over.
7. **Split by vendor.** With the mouse, *Clean Code* and *French Press* in the cart, the Review page shows three vendor cards
   (Brightline, Acme, Corner Market). Each card becomes one sub-order.
8. **Price changed during review (409).** Open the Review page, then change a price from `backend/`:
   ```powershell
   php artisan tinker --execute="App\Models\ProductOffer::where('price_cents', 1599)->update(['price_cents' => 1699])"
   ```
   This makes *Wooden Puzzle* cost $16.99. Press **Place order**, and you get a "Prices changed while you were reviewing" banner
   with the new total. Nothing is saved until you confirm again. Run `php artisan db:seed` afterwards to restore the price.
9. **Async vendor notification.** Place the order. The order page shows each sub-order as `pending`, then polls every 2 seconds for up to a minute. Once
   `queue:work` has processed the jobs, every sub-order and then the order turn `notified`, and `laravel.log` has one
   `Vendor notified` entry per vendor. If the worker isn't running, the order stays `pending`. Start the worker and press **Refresh**.
10. **Double submit is safe.** Fill the cart again and double-click **Place order**. Both requests reach the API with the same
    `Idempotency-Key`, but only one order is created: the second request gets the same order back (HTTP 200,
    `Idempotent-Replayed: true` in the Network tab), and each vendor is notified once.

**Known limitation:** order items store the discount rule and percentage but not the category name, so a placed order shows
"Category: 5%" where the cart showed "Books: 5%".

---

### Four things to emphasize:

1. **The discount engine.** "Bind the discount rules and resolve them dynamically" and "without significantly
   changing the main order-processing logic" describe the Strategy pattern, with rules registered in the
   service container. Adding a rule should take **one new class and one config line**.
2. **Correct money handling.** This is a payments-adjacent company, so there are no floats anywhere and rounding is predictable.
3. **Transactional integrity.** The order and its sub-orders are created atomically. Vendor jobs are dispatched **only after
   commit**, so a job never runs for an order that was rolled back.
4. **Structure and tests.** That means thin controllers, a service layer, Form Requests, API Resources, and real tests.

### Tech stack

| Layer    | Choice | 
|----------|--------|
| Backend  | Laravel 13, PHP 8.4 | 
| DB       | **MySQL 8** (local service) for the app; SQLite in-memory for tests | 
| Queue    | `database` driver (in MySQL) | 
| Tests    | PHPUnit 12 (Laravel feature and unit tests) | 
| Frontend | React 19, TypeScript, Vite, TanStack Query, pnpm | 
| Styling  | Tailwind CSS v4 (`@tailwindcss/vite` plugin, no config file) and a few small in-house components | 

**Repo layout: one git repository holding two separate apps.** To be precise, this is *not* a monorepo in the tooling
sense. There are no workspaces, no shared packages, and no Turborepo or Nx. It's one project with two independent parts side by side,
each with its own dependency manager (`composer` and `pnpm`), its own tests and its own dev server.

```
multivendor-pricing/                 ← single git repo, one README, one link for reviewers
├── backend/                         Laravel 13, API only (Composer, PHPUnit)
│   ├── app/
│   │   ├── Http/Controllers/        thin controllers
│   │   ├── Http/Requests/           QuoteCartRequest, StoreOrderRequest
│   │   ├── Http/Resources/          API Resources
│   │   ├── Models/  Enums/
│   │   ├── Domain/                  business logic, framework-light (namespace App\Domain\*)
│   │   │   ├── Pricing/             OfferSelector, PricingService, DTOs (Quote, PricedLine, …)
│   │   │   ├── Discounts/           DiscountRuleInterface, DiscountEngine, Combiners/, Rules/
│   │   │   └── Orders/              OrderService, QuoteChangedException, IdempotencyKeyReusedException
│   │   ├── Jobs/                    NotifyVendorJob
│   │   └── Providers/               DiscountServiceProvider
│   ├── config/discounts.php
│   ├── database/{migrations,factories,seeders}
│   ├── routes/api.php
│   └── tests/{Unit,Feature}
├── frontend/                        React 19, TypeScript, Vite (pnpm)
│   └── src/
│       ├── api/                     typed client + TanStack Query hooks
│       ├── features/{products,cart,customer,review,order}/
│       ├── components/              shared UI (Spinner, ErrorBanner, Money, …), layout, error boundary
│       └── lib/                     money formatting, debounce and idempotency-key hooks
│
├── .editorconfig  .gitignore
└── README.md
```

### Domain model

```
customers      id, name, email, is_loyal
vendors        id, name, email
categories     id, name, slug
products       id, category_id, name, sku
product_offers id, product_id, vendor_id, price_cents, is_active      UNIQUE(product_id, vendor_id)

orders         id, customer_id, idempotency_key, idempotency_request_hash,
               status, subtotal_cents, discount_cents, total_cents, timestamps            UNIQUE(idempotency_key)
sub_orders     id, order_id, vendor_id, status, subtotal_cents, discount_cents, total_cents, notified_at   UNIQUE(order_id, vendor_id)
order_items    id, sub_order_id, product_id, product_name, quantity,
               unit_price_cents, line_subtotal_cents,
               discount_rule, discount_bps, discount_cents, line_total_cents
```

- **Snapshotting:** each order item stores price, product name, vendor (through its sub-order) and the applied discount. Later price
  changes never rewrite historical orders.
- **Enums:** `OrderStatus` and `SubOrderStatus` (`pending`, `notified`) as backed PHP enums with Eloquent casts. A sub-order becomes `notified` when its vendor job runs; the order becomes `notified` once all of its sub-orders are.
- **Totals are always sums of line totals.** So `order.total == Σ sub_order.total == Σ item.line_total`, exactly to the cent.
  A test asserts this invariant.

### API

| Method | Path | Purpose |
|--------|------|---------|
| GET  | `/api/products`     | Products with category, all offers, and the best offer |
| GET  | `/api/customers`    | Demo customers to "act as" (there's no auth, see Assumptions) |
| POST | `/api/cart/quote`   | Price a cart: lines, chosen vendor, discounts, vendor groups, totals |
| POST | `/api/orders`       | Create the order. Returns 201 with the order, sub-orders and items, or 200 with the existing order when the `Idempotency-Key` was already used for the same cart |
| GET  | `/api/orders/{id}`  | Show an order (used by the result page and on refresh) |

**`POST /api/orders` in detail.** Body: `{customer_id, items: [{product_id, quantity}], expected_total_cents?}`. Optional
header: `Idempotency-Key` (1–100 printable ASCII characters, no spaces).

| Status | When | Body |
|--------|------|------|
| 201 | Order placed | `{data: order}` |
| 200 | Same key and cart as an earlier order (header `Idempotent-Replayed: true`) | `{data: order}`, the existing order |
| 409 | `expected_total_cents` is stale | `{message, expected_total_cents, quote}` |
| 422 | Invalid input, including a malformed key | `{message, errors: {"items.1.product_id": [...], "idempotency_key": [...]}}` |
| 422 | A product lost its last offer after validation | `{message, product_id}` |
| 422 | The key was already used for a different customer or cart | `{message}` |

The backend also includes: `StoreOrderRequest`/`QuoteCartRequest` (items 1–50, `distinct` product ids, quantity 1–999,
`customer_id` exists), API Resources for every response, and consistent JSON error shapes (422, 404, 409).

### Frontend flow

`Products → Cart → Review → Result`, using React Router (`/`, `/cart`, `/review`, `/orders/:id`).

- **Products:** a list from `GET /api/products`, showing the best price and vendor, plus an "Add to cart" button.
- **Cart:** quantity steppers and remove buttons. Cart state lives in `useReducer` and context, persisted to `localStorage`. Each change
  re-requests `/cart/quote` (debounced), so the chosen vendor, unit price and discounts shown always come from the server.
  While you type in a quantity box it keeps your text as a draft, so it can be cleared and retyped. Valid numbers update the
  cart at once; leaving the box with an empty or invalid value restores the cart's quantity.
- **Customer:** a "Shopping as" selector in the sticky header, saved to `localStorage`. It's global rather than on the Review page
  because every quote needs `customer_id`: a loyal customer sees the loyalty discount already in the cart.
- **Review:** lines grouped by vendor (one card per future sub-order), with the applied discount and "also eligible" ones, totals,
  and who is ordering. Place order runs a mutation that sends the reviewed total as `expected_total_cents` and an
  `Idempotency-Key` header. The key comes from `useIdempotencyKey` and stays the same while the customer and cart do, so a
  double click (both clicks can land before the button re-renders as disabled) or a retry returns the same order.
- **Result:** the created order with each vendor sub-order and its status (`pending`, then `notified` once the worker runs, with a
  manual refresh or light polling).
- **Loading and errors:** skeletons and spinners for queries, a disabled button and spinner while submitting, inline 422 messages,
  a 409 "prices changed, please review" banner, and a generic error boundary.
- Folders: `api/` (typed client and query hooks), `features/{products,cart,review,order}/`, `components/` (shared UI).
- **Look and feel:** Tailwind CSS v4 with a restrained design: a neutral palette and one accent color, cards on a light background,
  a sticky header with a cart badge, and a responsive product grid (one column on mobile, three on desktop). Shared components are
  `Button`, `Card`, `Badge` (for "best price", the applied discount and sub-order status), `Money`, `Spinner`, `Skeleton` and `ErrorBanner`.
  There's no component library (shadcn/ui, MUI). It would add a lot of generated or third-party code for little gain at this size.

### Tests

**Unit**
- `OfferSelector`: picks the lowest offer, breaks ties by lowest vendor id, ignores inactive offers, and fails when no offer exists.
- Each discount rule: applies and doesn't apply, and quantity tiers are chosen correctly at the boundaries.
- `DiscountEngine` / `BestDiscountWins`: the highest discount wins, every applicable discount is reported, and no rule means no discount.
- Money rounding cases.

**Feature**
- `POST /api/orders` with products from 2 vendors creates 1 order, 2 sub-orders and correctly grouped items.
  `Queue::fake()` then `assertPushed(NotifyVendorJob::class, 2)`.
- Order totals equal the sum of sub-order totals, which equal the sum of item totals.
- Validation failures (422), and no jobs are pushed when the transaction fails.
- Quote drift returns 409, and it isn't written to the log (`Exceptions::fake()`).
- Idempotency: the same key twice gives one order and one job per vendor; a replay after a price change returns the stored
  order; different keys give different orders; a key reused for another cart or customer gives 422; a 409 leaves the key
  unused; a simulated concurrent insert returns the winner's order; malformed keys fail validation.
- Running `NotifyVendorJob` writes the log entry (`Log::spy`), marks the sub-order notified, and doesn't log a second time on a rerun.
- Rule extensibility: binding an extra test rule into the tag makes the engine pick it up, with no other change.

### Assumptions (spec gaps, decided rather than asked)

1. **No authentication.** The customer is chosen from seeded demo customers and sent as `customer_id`. In production it
   would come from the authenticated user (Sanctum).
2. **"Loyal customer"** means an `is_loyal` flag on the customer. An alternative would be deriving it from order history, which is a one-class change.
3. **Stock is unlimited.** Inventory isn't mentioned.
4. **Single currency** (USD, cents). No tax or shipping.
5. **Duplicate product lines** in one cart are rejected by validation. The frontend merges them anyway.
6. **Discount config** lives in `config/discounts.php`. Moving it to a DB table with an admin UI would be a natural next step.
7. **"Notify vendor"** is a log line, as the spec says. No mail or webhook.

#### Decisions

- **Git:** the project has its own repository, initialized in `multivendor-pricing/`.
- **Tests:** PHPUnit.
- **Database:** MySQL for the app, in-memory SQLite for tests.
- **Discounts:** best single discount wins per line.
- **Duplicate orders:** prevented on the server with an `Idempotency-Key`.
- **Styling:** Tailwind CSS v4.
- **Docker and Composer scripts:** deferred For now the project runs with plain artisan commands.
