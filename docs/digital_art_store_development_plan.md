# Digital Art Store — Development Plan

## 1. Objective

Build a lightweight, fast, secure and visually distinctive digital-art ecommerce platform without WordPress/WooCommerce.

Initial focus: **Printable Digital Art**.

Architecture should later support other downloadable products such as Educational Printables.

Core flow:

```text
Product → Checkout → PayPal → Payment Verification → Order → Secure Download
```

The system must include:

- Public storefront.
- Product catalog.
- Product detail pages.
- Cart and checkout.
- PayPal payments.
- Automatic payment verification.
- Manual order approval when automatic processing fails.
- Customer/order management.
- Product management.
- Collections and bundles.
- Coupons/promotions.
- Secure private file storage.
- Lightweight WordPress/WooCommerce-like admin.
- Strong security and data protection.
- Responsive, fast frontend.
- Distinctive art-gallery visual identity.

---

# 2. Recommended Stack

```text
Backend: Laravel 12+
Database: MySQL
Admin: Filament
Frontend: Blade + Livewire/Alpine.js
Web Server: Nginx
Cache/Queue: Redis
Payments: PayPal REST API + Webhooks
Storage: Private storage / S3-compatible storage
Email: SMTP / transactional email provider
CDN/WAF: Cloudflare
Protocol: HTTPS/TLS
```

Keep dependencies minimal. Business logic belongs in Services/Actions, not controllers or Filament resources.

Suggested structure:

```text
app/
├── Models/
├── Services/
├── Actions/
├── Jobs/
├── Events/
├── Listeners/
├── Policies/
├── Notifications/
├── Filament/
└── Http/
```

Core services:

```text
PayPalService
OrderService
PaymentService
DownloadService
ProductService
EncryptionService
MailService
AuditLogService
```

---

# 3. Storefront

The site must NOT look like a default WooCommerce store.

Visual direction:

> **Digital Art Gallery + Editorial Design + Modern Boutique**

Characteristics:

- Large artwork previews.
- Strong typography.
- Generous whitespace.
- Editorial layouts.
- Minimal UI.
- Subtle animations.
- High-quality mockups.
- Fast navigation.
- Responsive design.

Avoid generic ecommerce grids, excessive sidebars, heavy filters and visually noisy interfaces.

Main pages:

```text
/
 /shop
 /collections
 /product/{slug}
 /cart
 /checkout
 /order/success
 /order/failed
 /about
 /contact
 /terms
 /privacy
 /refund-policy
```

Artwork must be the primary visual element.

---

# 4. SEO + Discoverability by Default

SEO is a **core architectural requirement**, not an optional marketing feature. Every public product, collection and relevant category must be designed to be crawlable, indexable, accessible and understandable by search engines.

The objective is that a product does not only exist inside the store. Each published product becomes an independent, useful web page that can be discovered through normal web search and image search.

## SEO-first principles

- Public content must be available as real HTML, not only through client-side JavaScript.
- Important information must never exist only inside an image.
- Every artwork must have a permanent, clean and indexable URL.
- Product text and images must both be optimized for discovery.
- Avoid keyword stuffing and automatically generated low-value pages.
- Avoid duplicate content caused by filters, sorting and URL parameters.
- SEO generation must be automatic but editable by an administrator.

## Automatic metadata

For every published product generate and maintain:

```text
SEO Title
Meta Description
Canonical URL
Open Graph Title
Open Graph Description
Open Graph Image
Twitter/X Card data
Slug
Breadcrumb data
```

Example:

```text
/art/mid-century-modern-coffee-bar-wall-art
```

The product editor should allow manual overrides without requiring code changes.

## Product SEO content

Each product should contain indexable and useful text such as:

```text
Product name
Description
Style
Theme
Recommended room
Subject
Print formats
Included files
Relevant tags
Related products
```

Example: instead of a generic title such as:

```text
Modern Art #27
```

prefer a descriptive product such as:

```text
Mid-Century Modern Coffee Bar Wall Art
```

The system may assist with generating SEO copy, but the generated content must remain natural, specific and useful to a human visitor.

## Image SEO

Images are a primary discovery channel for this store. Artwork must be optimized for search engines and accessibility.

For every public artwork:

- Use descriptive filenames.
- Generate meaningful `alt` text automatically.
- Allow manual editing of `alt` text.
- Generate responsive image sizes.
- Use WebP/AVIF where appropriate.
- Use `srcset` and responsive image markup.
- Lazy-load below-the-fold images.
- Provide appropriate image dimensions to prevent layout shift.
- Include important public images in an image sitemap.
- Do not hide product images behind JavaScript-only interfaces.

Example filename:

```text
mid-century-modern-coffee-bar-wall-art.webp
```

Example alt text:

```text
Mid-century modern coffee bar wall art printable
```

Do not use alt text as a keyword stuffing mechanism. It must describe the actual image.

## Structured data / Schema.org

Generate JSON-LD automatically where applicable:

```text
Product
Offer
BreadcrumbList
ImageObject
CollectionPage
WebSite
```

Product structured data should include, where available:

```text
name
image
description
sku
offers
price
priceCurrency
availability
url
```

Structured data must always reflect the actual visible product information and current price.

## XML sitemaps

Generate automatically:

```text
/sitemap.xml
/sitemaps/products.xml
/sitemaps/collections.xml
/sitemaps/pages.xml
/sitemaps/images.xml
```

The sitemap system must update when products or collections are published, modified or removed.

Only canonical public URLs should be included.

The system should also be prepared for registration with:

```text
Google Search Console
Bing Webmaster Tools
```

## Robots and indexing controls

Provide a managed `robots.txt` and automatic indexing rules.

Do NOT index:

```text
/admin
/cart
/checkout
/account
/internal-search
/private-downloads
``

Filter/sort combinations should not create thousands of indexable duplicate URLs.

Use canonical URLs and `noindex` rules where appropriate.

## Internal linking

Automatically connect relevant content:

```text
Product
 ├── Collection
 ├── Style
 ├── Room
 ├── Related Products
 └── Recommended Bundles
```

Collection and category pages should link to their products and related collections.

This creates a crawlable internal content network without manually maintaining every link.

## SEO-friendly collections

Collections should be capable of becoming useful landing pages for specific searches.

Example:

```text
/mid-century-modern-wall-art
/coffee-bar-wall-art
/minimalist-kitchen-wall-art
/retro-cocktail-bar-art
```

Do not automatically create a page for every possible tag combination. A collection should only become indexable when it contains enough useful content to justify an independent page.

## URL and redirect management

Use permanent, readable URLs:

```text
/art/{slug}
/collections/{slug}
```

When a published slug changes, maintain a redirect from the previous URL when appropriate.

Deleted or discontinued products should not immediately create broken links. Support:

```text
301 Redirect
410 Gone
404
```

according to the situation.

## Performance as SEO infrastructure

The storefront must be optimized for Core Web Vitals.

Prioritize:

- Fast server response.
- Optimized artwork delivery.
- WebP/AVIF.
- Responsive images.
- Lazy loading.
- Minimal JavaScript.
- CSS optimization.
- CDN caching.
- Database indexes.
- Server-side rendering where practical.
- Prevention of cumulative layout shift.

SEO, accessibility and performance should be treated as interconnected requirements rather than separate features.

## SEO database fields

Products and collections should support dedicated SEO overrides:

```text
seo_title
seo_description
seo_keywords_optional
seo_canonical_url
seo_robots
og_title
og_description
og_image
alt_text
```

Do not depend on a `keywords` meta tag for search ranking. The optional keyword field, if retained, is for internal organization or generation assistance only.

## Accessibility + discoverability

The public storefront should follow accessibility fundamentals:

- Semantic HTML.
- Proper heading hierarchy.
- Descriptive link text.
- Keyboard navigation.
- Visible focus states.
- Form labels.
- Accessible buttons.
- Meaningful image alt text.
- Sufficient color contrast.

Accessibility improves the usability of the store and ensures important product information is available in text rather than being trapped inside visual elements.

## SEO automation flow

When creating a product:

```text
Create Product
→ Upload Artwork
→ Generate Image Variants
→ Generate Suggested Filename
→ Generate Suggested Alt Text
→ Generate Suggested SEO Title
→ Generate Suggested Meta Description
→ Generate Slug
→ Generate Schema Data
→ Add to Sitemap
→ Publish
```

When a product changes:

```text
Update Product
→ Refresh SEO Metadata
→ Refresh Schema
→ Refresh Sitemap
→ Preserve Previous URL with Redirect if Slug Changed
```

This should happen automatically without requiring the administrator to understand SEO.

# 6. Products

Product types:

```text
Single Artwork
Mini Set
Collection
Bundle
```

Product fields:

```text
Title
Slug
Description
Price
Sale Price
Status
Category
Style
Room
Tags
Cover Image
Gallery
Included Files
```

Printable-art metadata:

```text
2:3
3:4
4:5
A-Series
11x14
16x20
```

Example pricing:

```text
Individual       $1–5
Mini Set         $5–10
Collection      $10–20
Promotion       $0.99
```

Product creation should be simple:

```text
Create Product
→ Upload Artwork
→ Upload Product Files
→ Enter Price
→ Enter Description
→ Preview
→ Publish
```

---

# 7. Collections and Bundles

Collections must reuse existing product assets rather than duplicating files unnecessarily.

Examples:

```text
Individual Artwork
3-Piece Set
6-Piece Collection
Complete Bundle
```

A product may belong to multiple collections.

---

# 8. Database

Initial entities:

```text
users
customers

products
product_files
product_images
collections
collection_products
seo_metadata
seo_redirects

orders
order_items
payments
downloads

coupons
coupon_usages

email_events
audit_logs
```

Optional later:

```text
refunds
reviews
wishlists
analytics
```

Order statuses:

```text
pending
paid
processing
completed
failed
cancelled
manual_review
refunded
```

---

# 9. PayPal

Use official PayPal APIs.

Flow:

```text
Customer
→ Create PayPal Order
→ PayPal Checkout
→ Customer Payment
→ PayPal Webhook
→ Server Verification
→ Payment Record
→ Order Status
```

Never trust the browser as proof of payment.

The server must verify the payment with PayPal.

Never store:

- Card numbers.
- CVV.
- Bank credentials.

PayPal handles payment-sensitive information.

Use transaction/event IDs and idempotency controls so duplicate webhooks cannot create duplicate orders.

---

# 10. Manual Order Processing

If automatic processing fails:

```text
Payment
→ Manual Review
```

Admin sees:

```text
ORDER #1048

Status: MANUAL REVIEW

[ Verify Payment ]
[ Approve Order ]
[ Reject ]
[ Refund ]
```

When approved:

```text
Manual Approval
→ Order = Completed
→ Generate Download Access
→ Send Customer Email
```

Every manual action must be logged.

---

# 11. Digital Downloads

Product files must never be publicly accessible.

Do NOT use:

```text
/public/products/product.zip
```

Use private storage:

```text
/storage/private/products/...
```

Downloads must use secure random tokens or temporary signed URLs.

Each download should have:

```text
Order association
Product association
Expiration
Maximum download count
Revocation capability
```

Example:

```text
Order #1048
Expires: 72 hours
Maximum downloads: 5
```

Never expose physical storage paths.

---

# 12. Customer Data Security

Only store information that is actually necessary.

Sensitive recoverable information should be encrypted at application level.

Potential encrypted fields:

```text
customer_name
email
phone
address
```

Encryption keys must be outside the database, preferably environment secrets or a secure secret-management system.

Never store encryption keys together with database backups.

For searchable encrypted fields such as email:

```text
email_encrypted
email_hash
```

Use the encrypted value for recovery/display and a secure HMAC/hash for lookup.

Passwords must NEVER be encrypted. Use:

```text
Argon2id
```

---

# 13. Admin Panel

The admin should feel like:

> **WordPress simplicity + WooCommerce functionality − unnecessary complexity**

Navigation:

```text
Dashboard

Products
Collections

Orders
Customers
Payments

Coupons

Downloads

Emails

Settings

Audit Log
```

## Dashboard

Show:

```text
Revenue Today
Revenue This Month
Orders
Products
Downloads
Customers
Pending Payments
Manual Reviews
```

Also:

```text
Recent Orders
Top Products
Recent Downloads
Failed Payments
```

Keep it compact and actionable.

---

# 14. Product Admin

The product editor should allow:

```text
Title
Price
Sale Price
Description
Category
Style
Room
Tags
Artwork
Product Files
Print Ratios
Collection
Status
```

Actions:

```text
Save Draft
Preview
Publish
Unpublish
Duplicate
Delete
```

---

# 15. Order Admin

Order detail:

```text
Order #
Customer
Products
Subtotal
Discount
Total

Payment Provider
Payment ID
Payment Status

Order Status

Downloads

Timeline
```

Actions:

```text
Approve
Complete
Cancel
Refund
Resend Email
Regenerate Download
Revoke Download
```

Sensitive customer information must only be decrypted for authorized administrators.

---

# 16. Customer Admin

Allow:

```text
Search Customers
View Customer
View Orders
View Downloads
View Purchase History
```

Do not expose sensitive information unnecessarily.

Access must be authorized and logged.

---

# 17. Coupons

Support:

```text
Percentage Discount
Fixed Discount
Product-Specific Discount
Collection-Specific Discount
Expiration Date
Usage Limit
Minimum Order
```

Examples:

```text
WELCOME
SAVE50
ART99
```

---

# 18. Email System

Emails:

```text
Order Confirmation
Payment Confirmation
Download Available
Payment Failed
Manual Approval
Refund
Account/Password emails if accounts are added
```

Do not permanently store full email contents unless required.

Store email events:

```text
recipient_hash
template
order_id
status
sent_at
```

Customer email addresses remain encrypted.

---

# 19. Admin Authentication

Require:

- Strong passwords.
- 2FA.
- Session expiration.
- Rate limiting.
- Secure cookies.
- CSRF protection.
- Login attempt protection.

Use:

```text
HttpOnly
Secure
SameSite
```

cookies where applicable.

---

# 20. Audit Log

Record sensitive and administrative operations:

```text
Admin Login
Product Price Changed
Sensitive Customer Data Viewed
Order Approved
Order Refunded
Download Regenerated
PayPal Configuration Changed
Security Configuration Changed
```

Record:

```text
user
action
target
timestamp
IP
metadata
```

Audit logs should be append-only from the application perspective.

---

# 21. General Security

Implement:

- HTTPS only.
- HSTS.
- CSRF protection.
- XSS protection.
- SQL injection protection.
- Authorization policies.
- Rate limiting.
- Secure cookies.
- Input validation.
- File upload validation.
- MIME verification.
- File size limits.
- Security headers.
- Content Security Policy where practical.
- Dependency/security updates.

Never trust:

```text
Client-side price
Client-side payment status
Client-side product ID
Client-side download authorization
```

All critical values must be validated server-side.

---

# 22. Backups

Backups must be:

- Automated.
- Encrypted.
- Stored outside the application server.
- Access-controlled.
- Rotated.
- Periodically tested.

Do not store encryption keys with encrypted backups.

---

# 23. Performance

Public storefront must be extremely lightweight.

Priorities:

- Server-side rendering where practical.
- WebP/AVIF.
- Lazy loading.
- Minimal JavaScript.
- CDN.
- Catalog caching.
- Database indexes.
- Redis where useful.
- Queue background jobs.
- Optimized queries.

Admin can be heavier; storefront should remain fast.

---

# 24. Background Jobs

Use queues for operations that should not block the customer:

```text
SendOrderConfirmation
SendDownloadEmail
GenerateDownloadPackage
GenerateThumbnail
GenerateSeoMetadata
UpdateSitemaps
ProcessWebhook
CleanupExpiredDownloads
```

Payment/webhook processing must be reliable and idempotent.

---

# 25. Development Phases

## Phase 1 — Foundation

- Laravel project.
- Database.
- Authentication.
- Filament.
- Environment configuration.
- Encryption.
- Private storage.
- Basic security.

## Phase 2 — Product System

- Products.
- Product files.
- Collections.
- Bundles.
- Categories.
- Product images.
- Admin CRUD.

## Phase 3 — Storefront

- Home.
- Catalog.
- Collections.
- Product pages.
- Cart.
- Responsive design.
- Art-gallery visual identity.

## Phase 4 — Payments

- PayPal integration.
- Checkout.
- Webhooks.
- Server-side verification.
- Payment records.
- Failed payment handling.

## Phase 5 — Digital Delivery

- Private storage.
- Download tokens.
- Expiration.
- Download limits.
- Download page.
- Automatic emails.

## Phase 6 — Admin Operations

- Dashboard.
- Orders.
- Customers.
- Payments.
- Manual approval.
- Coupons.
- Downloads.
- Email events.
- Audit logs.

## Phase 7 — SEO + Discoverability

- SEO metadata generation.
- Editable SEO fields.
- Canonical URLs.
- Schema.org / JSON-LD.
- Product sitemap.
- Collection sitemap.
- Page sitemap.
- Image sitemap.
- Managed robots.txt.
- Automatic redirects when slugs change.
- Internal linking rules.
- Image filenames and alt text.
- Responsive image delivery.
- Google Search Console/Bing Webmaster readiness.
- Index/noindex rules for private and duplicate URLs.
- Core Web Vitals optimization.

## Phase 8 — Security Hardening

- 2FA.
- Rate limiting.
- Security headers.
- CSP.
- File-upload security.
- Authorization audit.
- Encryption verification.
- Backup encryption.
- Dependency audit.
- Security testing.

## Phase 9 — Optimization

- Image optimization.
- Caching.
- CDN.
- Database indexes.
- Queue optimization.
- Performance testing.

---

# 26. MVP Scope

The first production version only needs:

```text
Storefront
Products
Collections
Cart
Checkout
PayPal
Orders
Customers
Protected Downloads
Automatic Email
Manual Order Approval
Admin Dashboard
Product Management
Basic Coupons
SEO Automation
XML Sitemaps
Schema.org
Image SEO
Audit Log
Security
```

Do NOT build initially:

```text
Reviews
Wishlist
Affiliate System
Subscriptions
Vendor Marketplace
Complex Tax Engine
Advanced CRM
Complex Analytics
Mobile App
Multi-Vendor System
```

Only add these if there is a real business need.

---

# 27. Final Vision

The customer experience:

```text
Discover Art
→ Choose Artwork
→ Buy
→ Pay with PayPal
→ Instant Download
```

The owner experience:

```text
Create Product
→ Publish
→ Receive Order
→ Payment Automatically Verified
→ Digital Product Automatically Delivered
```

If something fails:

```text
Manual Review
→ Approve
→ Customer Receives Product
```

## Core principle

> **Simple for the customer. Simple for the administrator. Secure by design. Fast by default. No unnecessary WordPress/WooCommerce complexity.**

The platform should be a **modern digital art gallery with a lightweight ecommerce engine**, not a generic ecommerce framework.
