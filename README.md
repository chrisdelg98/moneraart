<div align="center">

# Monera Art

**A digital art gallery with a lightweight ecommerce engine.**

Printable wall art, sold as instant downloads. Built as a focused Laravel application
instead of on WordPress/WooCommerce.

`Laravel` · `Filament` · `MySQL` · `Redis` · `Meilisearch` · `PayPal`

*In active development — see [the build plan](docs/implementation_plan.md) for current scope.*

</div>

---

## The idea

Most small art stores run on WooCommerce, which means carrying a catalog, tax, shipping and variant
model designed for physical multi-vendor retail in order to sell a PDF. Monera Art sells files, so
it is built to sell files.

One principle drives every decision in it:

> **Simple for the customer. Simple for the administrator. Secure by design. Fast by default.**

**For the customer**

```
Discover art  →  Choose  →  Pay with PayPal  →  Download immediately
```

No account. No shipping address. Three fields at checkout: email, coupon, consent.

**For the owner**

```
Create product  →  Publish  →  Order arrives  →  Payment verified  →  Files delivered
```

All of it automatic. When automatic verification can't complete, the order lands in a manual review
queue showing exactly what didn't match — never failing silently.

---

## What's inside

### Storefront

Server-rendered HTML, no SPA, no hydration. Browsing is fast because there is very little to send.

- **Museum wall-label presentation** — every piece gets a gallery placard: title, medium, formats,
  file count. Recognisable as an art store at a glance, and it doubles as the accessible text.
- **Asymmetric editorial grid** with varied card sizes, instead of a uniform product matrix
- **Typo-tolerant bilingual search** — `coffe bar`, `minimalsta cocina` and `mid centry modern` all
  find the right artwork
- **English and Spanish**, with translated URLs, per-locale SEO, and reciprocal `hreflang`
- **No carousels, popups, infinite scroll, chat widgets — or cookie banner**, because nothing here
  tracks anyone

### Admin

Built on Filament. WordPress simplicity, WooCommerce capability, none of the plugin sprawl.

- Dashboard with revenue, orders, downloads, and manual reviews surfaced first
- Product editor where one tab is enough to publish
- Order detail with payment trail, download grants and a full timeline
- Manual review queue with a live "Verify with PayPal" check
- Coupons, customers, downloads, email events, audit log
- **Every settings page has a test button beside the fields it controls**

### Payments

PayPal, configured by pasting two keys into a form.

Where WooCommerce then asks the owner to register a webhook by hand in PayPal's developer dashboard,
this **registers it automatically** — which removes the single most common cause of orders stuck in
`pending`.

- Server-side amount and currency verification against PayPal's capture
- Idempotent webhooks: a replayed event cannot double-fulfil an order, guaranteed by unique database
  constraints rather than by application logic alone
- Capture response as the fast path, webhook as the reliable one, converging safely
- Free products skip the payment step entirely — no card, no PayPal, no friction

### Delivery

Product files are never web-reachable. Downloads are token-gated, counted, expiring and revocable.

- Local disk or **Cloudflare R2** / any S3-compatible storage, swappable from the admin
- Download counters enforced server-side, so a shared link cannot exceed its limit
- Self-service link renewal when a customer's links expire
- Every attempt logged, which is also the evidence that wins payment disputes

### Built in, not bolted on

| | |
|---|---|
| **SEO** | Automatic metadata, JSON-LD, per-locale sitemaps, image sitemap, managed robots, 301s on slug change — all editable, none required to be understood |
| **Accessibility** | WCAG 2.2 AA, release-blocking. A keyboard-and-screen-reader purchase is an acceptance criterion |
| **Security** | Encrypted customer data with blind-index lookup, 2FA, CSP, rate limits, audit log, no card data ever |
| **Performance** | Budgets enforced in CI — a pull request that breaches one fails |

---

## Performance budget

| Metric | Budget |
|---|---|
| LCP (mobile, 4G) | < 2.0 s |
| CLS | < 0.05 |
| INP | < 200 ms |
| JS shipped (gzipped) | < 40 KB |
| CSS shipped (gzipped) | < 25 KB |
| TTFB (cache miss) | < 200 ms |
| Lighthouse Performance | ≥ 95 |
| Lighthouse Accessibility | **100** |

---

## Design intent

Not a generic storefront. The guiding idea:

> **Character is free. Complexity is not.**

A site's personality comes from typography, colour, scale and layout rhythm — all plain CSS, costing
nothing in payload or accessibility. What people usually reach for when a site "needs personality"
(carousels, scroll animations, parallax, hover effects) adds weight, breaks screen readers, and makes
a site feel busy rather than distinctive.

This store takes all of the first list and none of the second.

Full rationale: [§12.2](docs/implementation_plan.md).

---

## Getting started

**Requirements** — PHP 8.3+ (`intl`, `zip`, `bcmath`, `vips` or `imagick`), Composer 2, Node 20+,
MySQL 8 or PostgreSQL 16, Redis 7, Meilisearch 1.x

```bash
git clone <repo> && cd moneraart
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Then open `/setup`. The first-run wizard handles system checks, store identity, the admin account
with 2FA, PayPal keys and SMTP. **Nothing requires editing a config file** — settings live in the
database so the owner never needs a terminal.

<details>
<summary><b>PayPal in local development</b></summary>

PayPal cannot deliver webhooks to `localhost`. Expose the site first:

```bash
ngrok http 8000      # then set APP_URL to the public URL
```

The setup wizard detects a non-public host and prints this reminder itself, so nobody has to
discover it by watching orders hang.
</details>

<details>
<summary><b>Switching storage to Cloudflare R2</b></summary>

Admin → Settings → Storage. Paste the R2 credentials, test the connection, then run the migration —
files are copied, checksums verified, and `product_files.disk` updated per file. Resumable, and the
store keeps serving throughout.

R2 charges no egress fees, which for a store delivering large print files is the difference between
storage being a rounding error and being a real cost. See [§8.6](docs/implementation_plan.md).
</details>

---

## Documentation

| Document | Covers |
|---|---|
| **[Vision](docs/digital_art_store_development_plan.md)** | What the platform is: goals, scope, MVP boundary |
| **[Implementation plan](docs/implementation_plan.md)** | How it's built: schema, domain layer, PayPal, delivery, storage, SEO, search, accessibility, phases |
| **[Legal content — EN](docs/legal_content.en.md)** | Terms, privacy, refunds, AI disclosure, licence |
| **[Legal content — ES](docs/legal_content.es.md)** | The same, written for Spanish readers — not a literal translation |

**Start here if you're picking this up:** [§3 Configuration](docs/implementation_plan.md) ·
[§6 PayPal](docs/implementation_plan.md) · [§8 Delivery](docs/implementation_plan.md) ·
[§9 Data protection](docs/implementation_plan.md) ·
[§4.7 Internationalisation](docs/implementation_plan.md)

---

## Repository layout

```
app/
  Actions/           single-purpose, transactional
  Services/          PayPal, downloads, media, settings, SEO
  Filament/          admin panel
  Http/              controllers, middleware, form requests
resources/views/     Blade — the storefront is server-rendered
storage/app/private/ sellable product files, never web-reachable
docs/                planning and legal documents
tests/               Pest — money and delivery paths at 100% coverage
```

Controllers and Filament resources hold no business logic. They validate, call an Action, and return
a response — enforced by architecture tests.

---

## Security

Three rules that shape the codebase:

1. **Never trust the client** for price, discount, total, payment status, product identity or
   download authorisation. Every one is resolved server-side, every time.
2. **Product files are never web-reachable.** Delivery is token-gated and streamed via
   `X-Accel-Redirect` or a 60-second pre-signed URL — never a path the customer can construct.
3. **Card data never touches this application.** PayPal handles it end to end.

Customer emails are encrypted at rest, with an HMAC blind index for lookup. `APP_KEY` and
`BLIND_INDEX_KEY` are never stored alongside backups.

Found a security issue? Email the address in the store footer rather than opening an issue.

---

## Licence

Proprietary. All rights reserved.

Artwork sold through this store is created with AI image-generation tools, then selected, refined and
prepared for print by us. We say so on every product page — see
[how we make our art](docs/legal_content.en.md).
