# Legal Content — Draft for Review

> Companion to [implementation_plan.md](./implementation_plan.md).
> Technical requirements for acceptance, versioning and storage live in §7.7 of that document.
> **This file holds the customer-facing text itself.**

---

## ⚠️ Read this before publishing

**This is a working draft, not legal advice.** It is modelled on the conventions used by established
digital-asset and printable-art stores. Three parts genuinely need a professional review before they
go live:

1. **The no-refund clause.** Enforceability depends on the consumer-protection law of the *buyer's*
   country, not the seller's. §7.7.3 of the implementation plan explains why the checkout wording
   matters more than the policy text does.
2. **The AI-generation and licence sections.** The copyright status of AI-generated images is
   unsettled and differs by jurisdiction. The approach here — selling a **licence to use files**,
   never a transfer of copyright — is deliberate, and §2 below explains why.
3. **The identity and jurisdiction placeholders.** A terms document that does not name the seller or
   the governing law is substantially weaker in a dispute.

Budget a one-hour consultation with a lawyer in the country of establishment. At this revenue it is
not worth more than that, and it is worth that.

**Placeholders to fill before publishing:**

```
[STORE_NAME]          Monera Art
[LEGAL_ENTITY]        registered name, or the owner's name if a sole trader
[COUNTRY]             country of establishment — still undecided, see plan §24.2
[JURISDICTION]        governing law and courts
[SUPPORT_EMAIL]       support@...
[WEBSITE]             https://...
[EFFECTIVE_DATE]      publication date
[RESPONSE_WINDOW]     a support response time you can actually honour — 48 hours is realistic
[CURRENCY]            USD
[EXPIRY_HOURS]        download link validity — plan default is 72
[MAX_DOWNLOADS]       downloads per file — plan default is 5
```

Filled per order at render time, not before publishing:

```
[ORDER_NUMBER]  [ORDER_DATE]  [PRODUCT_TITLE]
```

---

## 1. Voice guide

Everything in this file follows four rules. They matter more than the specific wording, because
they are what keeps future copy consistent once someone else is writing it.

### Rule 1 — Say everything. Lead with what you do, not what you aren't.

A negation makes the reader hunt for the hidden problem. A positive statement of the same fact
lets them draw the conclusion themselves, and they trust a conclusion they reached on their own.

| Instead of | Write |
|---|---|
| "We are not traditional illustrators and do not claim to be" | "Anyone can type a prompt. The work is in everything after: taste, editing, preparation, curation." |
| "We cannot guarantee exclusivity" | "Your licence covers personal use. For exclusive or commercial rights, write to us." |
| "If this isn't what you want, don't buy" | "Curious whether it suits your space? Send us a note." |

The reader learns the same facts in both columns. Only one of them sounds confident.

### Rule 2 — Never apologise for your own product.

"We'd rather tell you than take your money" is meant as integrity and reads as doubt. If the
product is worth selling, present it as worth selling and let the disclosure sit alongside it as
information, not as a warning.

### Rule 3 — Describe situations, not the customer's mistakes.

"You did not read the description" assigns blame at the exact moment a customer is already
frustrated. "A size that turned out not to be the one you wanted" covers the identical case and
leaves their dignity intact. The policy outcome is the same; the relationship is not.

### Rule 4 — Close limits with an opening.

Every restriction should end with a route forward. "Not covered by this licence" becomes "not
covered by this licence — write to us about commercial use." A dead end invites a dispute. A door
invites an email.

### A note on the AI wording

The owner's framing — *we are not the digital creators of the art, but we are the ones who generate
the images* — contains a real distinction, and the drafts preserve both halves:

- You did **not** draw or paint these by hand.
- You **did** generate them: concepts, prompts, selection from many outputs, editing, upscaling,
  print preparation, collection assembly.

The copy expresses this by **describing the work you actually do** rather than by denying work you
don't. "Generated with AI tools, then shaped by us" states it completely, in seven words, without a
single apology. The disclosure is total; the tone is confident.

---

## 2. Terms of Sale

> **Terms of Sale**
> Last updated: [EFFECTIVE_DATE] · Version 1.0

### 1. Who we are

[WEBSITE] ("[STORE_NAME]", "we", "us") is run by [LEGAL_ENTITY], based in [COUNTRY].

You can reach us at [SUPPORT_EMAIL]. We answer every message within [RESPONSE_WINDOW].

### 2. What we sell

We sell **digital files** — printable artwork, delivered as instant downloads.

Everything here is digital. You print it yourself, or send it to a print service of your choosing.
Nothing is shipped.

### 3. How our artwork is made

Our artwork is created with AI image-generation tools, then shaped by us.

Here's what goes into a piece:

- We start with an idea — a mood, a palette, a room it belongs in.
- We generate, review, and generate again, refining until something clicks.
- We keep only what we'd hang on our own wall. Most of what we make doesn't survive this step.
- We edit, upscale and colour-correct the pieces that do.
- We prepare every file for printing at 300 DPI, in sizes that fit frames people actually own.
- We group pieces that belong together into sets and collections.

So what you're buying is a curated, print-ready file: the selection, the preparation, the formats,
and instant delivery.

We tell you this openly because it's part of how we work. The longer version is here:
**[How we make our art →]**

> 📋 **For you, not the customer.** This clause does real work: a buyer who was clearly told the
> artwork is AI-generated cannot later claim they were misled. Keep it visible and keep it plain.
> The badge on the product page (§7) reinforces it at the point of decision, which is where it
> counts.

### 4. What you can do with your files

Your purchase includes a **personal-use licence**.

**Yours to:**

- Print for your own home, office or space
- Print as many copies as you like, for yourself
- Print at home or through any print service
- Resize or crop to fit your frame
- Give a printed copy to someone as a gift

**Please don't:**

- Share, resell or redistribute the digital files
- Upload them to websites, marketplaces, stock libraries or print-on-demand platforms
- Sell prints, products or merchandise made from them
- Use them commercially or in advertising
- Present the artwork as your own creation
- Include them in anything you distribute — templates, courses, bundles

**Planning something commercial?** Extended licences are available for many pieces. Write to
[SUPPORT_EMAIL] and we'll sort it out.

### 5. About ownership

When you buy, you receive a licence to use the files. The artwork itself stays with us.

We'll be straightforward about one thing: the copyright status of AI-generated images is still
developing, and the rules differ from country to country. So what we offer is a **licence to use the
files we've prepared for you** — not a transfer of copyright, and not a claim to exclusivity.

What we stand behind: the files we deliver, prepared by us, and your right to use them exactly as
described above. We don't work from or copy existing artwork.

If your project needs exclusive rights or verified ownership, write to us at [SUPPORT_EMAIL] and
we'll tell you honestly whether we can help.

> 📋 **For you, not the customer.** This is the most important clause in the document. Selling a
> *licence to use files* rather than *the copyright in an image* means you are never promising
> something you may not legally hold. It is also why the final line matters — it routes an unusual
> request to a conversation instead of to a refund request.

### 6. Prices and payment

- Prices are shown in [CURRENCY], and that's what you pay.
- Payment is handled by **PayPal**. Your card details never touch our systems.
- Pay with a PayPal account or a card — no account with us needed.
- Prices can change, but the price you see at checkout is the price you pay.

### 7. Delivery

Your files arrive **immediately** after payment.

- Download links appear on your confirmation page right away
- The same links go to your email
- Links stay active for **[EXPIRY_HOURS] hours**, with up to **[MAX_DOWNLOADS] downloads** per file
- Need them again? Request fresh links from your order page or email us — free, any time we still
  have your order on record

One small thing worth getting right: **please use an email address you can access.** It's how your
files find you.

Nothing arrived? Check your spam folder, then email us. We'll get it to you.

### 8. Refunds

Because everything we sell is digital, **all sales are final**.

The moment your payment goes through, the complete files are yours — downloaded, permanent, and
impossible to return. By completing your purchase, you're confirming you'd like your files straight
away, and that you understand they can't be returned or cancelled once delivered.

**We'll always make it right when something's genuinely wrong.** Email [SUPPORT_EMAIL] if:

- You were charged more than once
- Your files won't open, or arrived incomplete
- What you received doesn't match what the product page showed
- Your download links stopped working and we can't repair them
- You paid and nothing arrived

We'll send replacement files, fresh links, or a refund — whichever actually solves it.

**What we can't refund** are the things that sit outside our hands once the files are yours: a
change of heart, a size or format that turned out not to be the one you wanted, or how a print
came out at a home printer or print shop.

A little help before you buy: every product page has preview images, the full list of included
files, and the available sizes. And if anything's unclear, just ask — we'd much rather answer a
question than have you end up with something that wasn't right.

> 📋 **For you, not the customer.** The second paragraph is doing specific legal work. EU and UK
> buyers have a statutory 14-day withdrawal right on digital content that can only be waived by
> **express consent to immediate delivery plus acknowledgement of losing the right** — which is
> exactly what that sentence and the checkout checkbox establish together. Remove either one and
> the policy stops holding in those markets. Also note: PayPal's buyer protection operates
> independently of this policy regardless, which is why the delivery logs in plan §8.4 matter.

### 9. About printing

Our files are supplied at **300 DPI** at the sizes listed on each product page — standard print
resolution.

How a print turns out depends on your printer, your paper and your print service. Screens and paper
also render colour differently: a screen emits light, paper reflects it, so there's always some
shift.

Two things that help: use a professional print service when you can, and print one small test copy
before committing to a large one.

### 10. Using our site

No account needed. We ask for your email address so we can deliver your purchase, and that's it.

We do ask that you don't:

- Share, resell or publish your download links
- Try to reach files you haven't purchased
- Use automated tools to scrape or bulk-download from our site
- Interfere with how the site runs
- Create multiple orders to get around limits on free products

We may revoke download access where these are broken.

### 11. Availability

We work to keep the site running smoothly, though we can't promise it will never be unavailable.
Products may be added, changed or retired over time. Retiring a product never affects files you've
already bought — those stay yours.

### 12. Our liability

Our files are provided as they are. To the fullest extent the law allows, our total liability for
any claim relating to a purchase is limited to **the amount you paid for it**.

We aren't liable for printing costs, materials, lost profits, or indirect or consequential losses.

Nothing here limits liability that can't legally be limited.

### 13. Changes to these terms

We may update these terms from time to time. The version that applies to your order is the one you
accepted when you bought it, and we keep a record of it — changes never apply backwards.

The current version and date are always at the top of this page.

### 14. Governing law

These terms are governed by the laws of [JURISDICTION], and any dispute will be handled by the
courts of [JURISDICTION].

### 15. Get in touch

[SUPPORT_EMAIL] · We reply within [RESPONSE_WINDOW].

---

## 3. Privacy Policy

> **Privacy Policy**
> Last updated: [EFFECTIVE_DATE] · Version 1.0

### The short version

We collect your email address so we can send you what you bought. We encrypt it. We never sell it
or share it for marketing.

That's genuinely the whole policy. The rest of this page is the detail, for anyone who wants it.

### 1. What we collect

**When you buy:**

| What | Why | How we keep it |
|---|---|---|
| Email address | To send your files and receipt | Encrypted |
| Your name | To personalise your receipt | Encrypted — and only if you give it |
| What you bought, and when | To provide access and keep our records | Standard |
| Payment confirmation from PayPal | To confirm the order is paid | Transaction ID only |
| IP address | Fraud prevention | **Hashed — we can't recover the original** |

**When you visit:** standard server logs (page, time, approximate region), kept briefly, used to
keep the site running and secure.

### 2. What we never collect

- **Card numbers, security codes or bank details.** Payment happens entirely inside PayPal. Those
  details never reach us — not encrypted, not briefly, not at all.
- **Your address or phone number.** We deliver to an inbox, so we don't need them.
- **Passwords**, since we don't ask you to create an account.

### 3. Why we keep it minimal

Every piece of personal data we hold is something we have to protect. The most reliable way to keep
your information safe is not to collect it in the first place. So we take what's needed to deliver
your purchase, and leave the rest.

### 4. What we use it for

Only this:

- Delivering your purchase and your download links
- Sending your receipt
- Answering you when you get in touch
- Preventing fraud and abuse
- Meeting our accounting obligations
- Sending you new releases — **only if you asked us to**

No profiling. No advertising trackers. Nothing else.

### 5. Who else sees it

A short list:

| Who | What they receive | Why |
|---|---|---|
| **PayPal** | The payment details you give them directly | To process your payment |
| **Our email provider** | Your email address | To deliver your files |
| **Our hosting provider** | Data stored on our servers | To run the site |
| Tax authorities or law enforcement | Only what the law requires | Legal obligation |

**We never sell, rent or trade your personal information.** Not to advertisers, not to data
brokers, not to anyone.

### 6. How we protect it

- Your email address and name are **encrypted** in our database — if it were ever stolen, those
  fields wouldn't be readable
- Encryption keys are stored separately from the data, and never alongside our backups
- The whole site runs over HTTPS
- Administrator access requires two-factor authentication
- Any access to customer information is logged
- Your purchased files are stored privately, reachable only through your own download links

### 7. How long we keep it

| What | How long |
|---|---|
| Order and payment records | 7 years for accounting, then anonymised |
| Your email address | Until you ask us to remove it, or until anonymisation |
| Download activity | 1 year |
| Marketing list | Until you unsubscribe |
| Server logs | 30 days |

### 8. Your rights

Wherever you are, these apply with us:

- **See** what we hold about you
- **Correct** anything that's wrong
- **Delete** your data — we keep only the minimum accounting records require, with identifying
  details removed
- **Take a copy** in a portable format
- **Unsubscribe** from marketing with one click in any email
- **Object** to how we use your data

Email [SUPPORT_EMAIL]. We respond within 30 days, usually much sooner, and never charge for it.

**One practical note:** because your email address is encrypted, we look up your records by matching
the exact address. Writing it the way you did when you ordered helps us find you quickly.

### 9. Cookies

As few as we can manage:

| Cookie | What it does | How long |
|---|---|---|
| Session | Keeps your cart working | Until you close your browser |
| `cart_count` | Shows the number on the cart icon | 30 days |
| Security token | Protects against forged requests | Session |

**No advertising cookies. No third-party trackers. Nothing following you around the web.**

PayPal sets its own cookies when you pay, covered by PayPal's privacy policy.

### 10. Children

Our store is intended for people 16 and over, and we don't knowingly collect information from
children. If you think a child has given us information, email us and we'll remove it.

### 11. Where your data lives

Our servers and service providers may sit in a different country from yours. Where that happens, we
use providers that apply appropriate safeguards.

### 12. Changes

If we update this policy, we'll change the date above and announce anything significant on the site.

### 13. Contact

[SUPPORT_EMAIL] · [LEGAL_ENTITY], [COUNTRY]

---

## 4. Refund Policy (standalone page)

> **Refunds**
> Last updated: [EFFECTIVE_DATE] · Version 1.0

### All sales are final

Everything we sell is digital, so all sales are final.

When you buy, the complete files are yours immediately and permanently. There's nothing to return,
and no way to undo a delivery that's already arrived. By completing your purchase, you're
confirming you'd like your files right away, and that you understand they can't be cancelled or
returned afterwards.

### When we'll make it right

We fix real problems. Email [SUPPORT_EMAIL] if:

✓ You were charged more than once
✓ Your files won't open, or arrived incomplete
✓ What you received doesn't match what the page showed
✓ Your links stopped working and we can't repair them
✓ You paid and nothing arrived

Replacement files, fresh links, or a refund — whichever actually solves it.

### What we can't refund

These sit outside our hands once the files are yours:

- A change of heart
- A size, ratio or format that turned out not to be the one you wanted
- How a print came out at a home printer or print shop
- Finding something similar elsewhere

### Before you buy

Every product page shows preview images, the full list of included files, the sizes and ratios, and
how the artwork is made.

If anything's unclear, ask us. We'd much rather answer a question than have you end up with
something that wasn't right for you.

### If something goes wrong with a payment

Please email us before opening a dispute with PayPal. We can almost always sort it out faster
directly — and we'd like the chance to.

### Contact

[SUPPORT_EMAIL] · We reply within [RESPONSE_WINDOW].

---

## 5. How We Make Our Art (standalone page, linked from every product)

> **How We Make Our Art**

### We work with AI. Here's what that means.

Every piece in our store begins with AI image-generation tools. We say so openly, because it's part
of how we work rather than something to tuck away.

A prompt, though, is where a piece starts — not where it ends.

### Our process

**Idea** — A mood, a palette, a room. We decide what we're trying to make before we make anything.

**Generation** — We generate, look, adjust, and generate again. A single piece can take dozens of
attempts before the composition holds together.

**Selection** — We review everything and keep very little. Most of what we generate never leaves
our drive.

**Refinement** — The survivors get edited, corrected, upscaled and colour-adjusted.

**Preparation** — Every file is prepared for print at 300 DPI, in multiple standard sizes and
ratios, so it works with whatever frame you already own.

**Curation** — Pieces that belong together become sets and collections that work on a wall as a
group.

### What we bring to it

Anyone can type a prompt. The work is in everything that comes after:

**Taste** — knowing which one of fifty results is worth keeping

**Craft** — the editing, upscaling and colour work that makes a file print beautifully

**Preparation** — proper resolution, real frame sizes, multiple ratios ready to go

**Curation** — pieces chosen to live together

That part is genuinely ours, and it's what you're paying for.

### Questions people ask

**Is my artwork original?**
Each file is generated uniquely for our collection, and we never work from or copy existing
artwork. One honest note: AI tools can produce similar results from similar ideas, so we can't
promise nothing resembling it exists anywhere.

**Do I own the copyright?**
You receive a licence to use the files personally — see our [Terms of Sale]. The copyright status
of AI-generated images is still developing and varies by country, which is why we sell a licence to
use the files rather than claiming to transfer ownership.

**Can I sell prints of these?**
Not under the standard personal-use licence — but extended licences exist for many pieces. Write to
us.

**Will it look good printed?**
Our files are 300 DPI at the listed sizes, which is standard print resolution. The rest comes down
to your printer, paper and print service. A small test print first is always worth it.

**Why is it so affordable?**
Our process lets us work at volume without cutting corners on preparation. We'd rather a piece you
love ends up on your wall than priced out of reach.

### Still deciding?

Some people prefer work made entirely by hand, and that's a lovely thing to want.

If you're wondering whether our pieces suit your space, send us a note at [SUPPORT_EMAIL]. We're
happy to help you decide, either way.

---

## 6. Licence file (shipped inside every download)

Save as `LICENSE.txt` and include it in every delivered package.

```
═══════════════════════════════════════════════════════════
  [STORE_NAME] — PERSONAL USE LICENCE
═══════════════════════════════════════════════════════════

  Order:    [ORDER_NUMBER]
  Date:     [ORDER_DATE]
  Product:  [PRODUCT_TITLE]
  Licence:  Personal use

───────────────────────────────────────────────────────────
  Thank you — we hope it looks wonderful on your wall.
───────────────────────────────────────────────────────────

ABOUT THIS ARTWORK

  Created with AI image-generation tools, then selected,
  refined and prepared for print by [STORE_NAME].

  The full story: [WEBSITE]/how-we-make-our-art

───────────────────────────────────────────────────────────

YOURS TO

  ✓ Print for your own space
  ✓ Print as many copies as you like, for yourself
  ✓ Print at home or through any print service
  ✓ Resize or crop to fit your frame
  ✓ Give a printed copy as a gift

PLEASE DON'T

  ✗ Share or resell the digital files
  ✗ Upload them anywhere online
  ✗ Sell prints or products made from them
  ✗ Use them commercially or in advertising
  ✗ Present the artwork as your own creation

───────────────────────────────────────────────────────────

PRINTING NOTES

  Resolution:  300 DPI at the listed sizes
  Colour:      sRGB
  Tip:         A small test print first is always worth it.
               Screens and paper render colour differently.

───────────────────────────────────────────────────────────

  Commercial licence?        [SUPPORT_EMAIL]
  Trouble with your files?   [SUPPORT_EMAIL]
  Lost your links?           [WEBSITE]/orders

  Full terms: [WEBSITE]/terms

═══════════════════════════════════════════════════════════
```

---

## 7. Short-form copy used across the site

### Checkout checkbox

```
☐  I agree to the Terms of Sale and Privacy Policy, and I understand I'm
   buying files that arrive immediately and can't be returned.
```

> 📋 **For you, not the customer.** Short as this is, it carries the EU/UK withdrawal waiver
> described in §2.8. Reword it freely for tone, but the two elements — immediate delivery, no
> return — have to survive any rewrite.

### Checkout checkbox — free products

```
☐  I agree to the Terms of Sale and Privacy Policy.
```

### Marketing consent (separate, optional, never pre-ticked)

```
☐  Email me when new artwork lands. Twice a month at most, and one click to stop.
```

### Product page — AI badge

```
✦ Made with AI, curated and prepared by us · How we work →
```

### Product page — delivery note

```
⬇  Instant download · 300 DPI · Multiple sizes included
   Your files arrive right after payment. Digital products are final sale.
```

### Footer

```
Artwork made with AI, selected and prepared by [STORE_NAME].
Terms · Privacy · Refunds · How we work
```

### Order confirmation email — footer line

```
Your files are licensed for personal use. Please keep them to yourself —
it's what lets us keep prices where they are. Full terms: [WEBSITE]/terms
```

---

## 8. Build checklist

### Pages to create

- [ ] `/terms` — Terms of Sale (versioned)
- [ ] `/privacy` — Privacy Policy (versioned)
- [ ] `/refund-policy` — Refunds (versioned)
- [ ] `/how-we-make-our-art` — process page
- [ ] `/licence` — licence terms in full

All are `index, follow`. They're genuine content, and they build trust with buyers and search
engines alike.

### Technical requirements

- [ ] Legal documents stored as **versioned records**, never overwritten on edit (plan §7.7.2)
- [ ] Each page shows its version number and effective date
- [ ] `terms_version` recorded on every order
- [ ] Admin order view links to the exact version that customer accepted
- [ ] Required checkbox at checkout, unticked by default, **validated server-side**
- [ ] Links open in a new tab so the cart is never lost
- [ ] Marketing consent is a separate optional checkbox
- [ ] `LICENSE.txt` generated per order and included in every download package
- [ ] AI badge rendered on product cards and product pages where `is_ai_generated`
- [ ] Footer links present on every page
- [ ] The 📋 owner-only notes in this file are **stripped** before any of this text is published

### Before launch

- [ ] Every `[PLACEHOLDER]` replaced
- [ ] Country of establishment decided (plan §24.2) — it sets jurisdiction
- [ ] Lawyer review of the three items flagged at the top of this file
- [ ] Support email live and monitored
- [ ] Response-time promise set to something you can actually keep
- [ ] Printing claims (300 DPI, sizes) verified true for every product
- [ ] Spanish translation, if selling to Spanish-speaking markets — translate the *voice* in §1, not
      just the words
