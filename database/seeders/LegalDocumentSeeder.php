<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

/**
 * Seeds version 1.0 of each legal page.
 *
 * Shortened from docs/legal_content.en.md — the drafts there are the source of
 * truth and still need the placeholders filled and a lawyer's eye on three
 * clauses before launch. This exists so the checkout stops asking people to
 * accept documents that do not exist.
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->documents() as $position => [$key, $slug, $title, $body]) {
            $document = LegalDocument::updateOrCreate(
                ['key' => $key],
                ['slug' => $slug, 'position' => $position],
            );

            $version = $document->versions()->updateOrCreate(
                ['version' => '1.0'],
                ['effective_at' => now(), 'published_at' => now()],
            );

            $version->bodies()->updateOrCreate(
                ['locale' => 'en'],
                ['title' => $title, 'body' => trim($body)],
            );
        }
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: string}> */
    private function documents(): array
    {
        return [
            ['terms', 'terms', 'Terms of Sale', <<<'MD'
## What we sell

We sell **digital files** — printable artwork, delivered as instant downloads. Everything here is digital. You print it yourself, or send it to a print service of your choosing. Nothing is shipped.

## How our artwork is made

Our artwork is created with AI image-generation tools, then shaped by us: we start with an idea, generate and refine until something clicks, keep only what we would hang on our own wall, then edit, upscale and prepare every file for printing, in sizes that fit frames people actually own.

So what you are buying is a curated, print-ready file: the selection, the preparation, the formats, and instant delivery.

## What you can do with your files

Your purchase includes a **personal-use licence**.

**Yours to:** print for your own home, office or space; print as many copies as you like, for yourself; print at home or through any print service; resize or crop to fit your frame; give a printed copy as a gift.

**Please don't:** share, resell or redistribute the digital files; upload them to websites, marketplaces, stock libraries or print-on-demand platforms; sell prints or products made from them; use them commercially or in advertising; present the artwork as your own creation.

Planning something commercial? Extended licences are available for many pieces — get in touch.

## About ownership

When you buy, you receive a licence to use the files. The artwork itself stays with us.

We will be straightforward about one thing: the copyright status of AI-generated images is still developing, and the rules differ from country to country. So what we offer is a **licence to use the files we have prepared for you** — not a transfer of copyright, and not a claim to exclusivity.

What we stand behind: the files we deliver, prepared by us, and your right to use them exactly as described above. We do not work from or copy existing artwork.

## Prices and payment

Prices are shown in USD, and that is what you pay. Payment is handled by **PayPal** — your card details never touch our systems. Pay with a PayPal account or a card, with no account needed here.

## Delivery

Your files arrive **immediately** after payment. Download links appear on your confirmation page right away, and the same links go to your email. Links stay active for a limited time, with a set number of downloads per file.

Need them again? Open any link and press **Email me a new link** — free, any time we still have your order on record.

One small thing worth getting right: **please use an email address you can access.** It is how your files find you.

## Refunds

Because everything we sell is digital, **all sales are final**.

The moment your payment goes through, the complete files are yours — downloaded, permanent, and impossible to return. By completing your purchase, you are confirming you would like your files straight away, and that you understand they cannot be returned or cancelled once delivered.

**We will always make it right when something is genuinely wrong.** Get in touch if you were charged more than once, your files will not open or arrived incomplete, what you received does not match what the product page showed, your links stopped working and we cannot repair them, or you paid and nothing arrived.

**What we cannot refund** are the things that sit outside our hands once the files are yours: a change of heart, a size or format that turned out not to be the one you wanted, or how a print came out at a home printer or print shop.

## About printing

**Every product page lists the resolution, sizes and formats included with that piece.** Those are the figures that apply to what you buy — please check them before ordering, as they vary between pieces. How a print turns out depends on your printer, your paper and your print service. Screens and paper also render colour differently, so there is always some shift. Print one small test copy before committing to a large one.

## Our liability

Our files are provided as they are. To the fullest extent the law allows, our total liability for any claim relating to a purchase is limited to **the amount you paid for it**. Nothing here limits liability that cannot legally be limited.

## Changes to these terms

We may update these terms from time to time. The version that applies to your order is the one you accepted when you bought it, and we keep a record of it — changes never apply backwards.
MD],

            ['privacy', 'privacy', 'Privacy Policy', <<<'MD'
## The short version

We collect your email address so we can send you what you bought. We encrypt it. We never sell it or share it for marketing.

That is genuinely the whole policy. The rest of this page is the detail, for anyone who wants it.

## What we collect

When you buy: your **email address**, to send your files and receipt — encrypted. What you bought and when, to provide access and keep our records. A payment confirmation from PayPal, as a transaction ID only. Your **IP address, hashed** — we cannot recover the original — for fraud prevention.

When you visit: standard server logs, kept briefly, used to keep the site running and secure.

## What we never collect

- **Card numbers, security codes or bank details.** Payment happens entirely inside PayPal. Those details never reach us — not encrypted, not briefly, not at all.
- **Your address or phone number.** We deliver to an inbox, so we do not need them.
- **Passwords**, since we do not ask you to create an account.

## Why we keep it minimal

Every piece of personal data we hold is something we have to protect. The most reliable way to keep your information safe is not to collect it in the first place.

## What we use it for

Delivering your purchase and your download links; sending your receipt; answering you when you get in touch; preventing fraud and abuse; meeting our accounting obligations; and sending you new releases **only if you asked us to**.

No profiling. No advertising trackers. Nothing else.

## Who else sees it

**PayPal** receives the payment details you give them directly. **Our email provider** receives your address, to deliver your files. **Our hosting provider** holds data stored on our servers. Tax authorities or law enforcement receive only what the law requires.

**We never sell, rent or trade your personal information.**

## How we protect it

Your email address and name are **encrypted** in our database — if it were ever stolen, those fields would not be readable. Encryption keys are stored separately from the data, and never alongside our backups. The whole site runs over HTTPS. Administrator access requires two-factor authentication, and any access to customer information is logged. Your purchased files are stored privately, reachable only through your own download links.

## How long we keep it

Order and payment records: 7 years for accounting, then anonymised. Your email address: until you ask us to remove it. Download activity: 1 year. Marketing list: until you unsubscribe. Server logs: 30 days.

## Your rights

Wherever you are, these apply with us: see what we hold about you; correct anything wrong; delete your data; take a copy in a portable format; unsubscribe from marketing with one click; object to how we use your data.

We respond within 30 days, usually much sooner, and never charge for it.

**One practical note:** because your email address is encrypted, we look up your records by matching the exact address. Writing it the way you did when you ordered helps us find you quickly.

## Cookies

As few as we can manage: a session cookie to keep your cart working, a cart counter, and a security token. **No advertising cookies. No third-party trackers. Nothing following you around the web.**

PayPal sets its own cookies when you pay, covered by PayPal's privacy policy.
MD],

            ['refunds', 'refunds', 'Refunds', <<<'MD'
## All sales are final

Everything we sell is digital, so all sales are final.

When you buy, the complete files are yours immediately and permanently. There is nothing to return, and no way to undo a delivery that has already arrived. By completing your purchase you are confirming you would like your files right away, and that you understand they cannot be cancelled or returned afterwards.

## When we will make it right

We fix real problems. Get in touch if:

- You were charged more than once
- Your files will not open, or arrived incomplete
- What you received does not match what the page showed
- Your links stopped working and we cannot repair them
- You paid and nothing arrived

Replacement files, fresh links, or a refund — whichever actually solves it.

## What we cannot refund

These sit outside our hands once the files are yours:

- A change of heart
- A size, ratio or format that turned out not to be the one you wanted
- How a print came out at a home printer or print shop
- Finding something similar elsewhere

## Before you buy

Every product page shows preview images, the full list of included files, the sizes and ratios, and how the artwork is made.

If anything is unclear, ask us. We would much rather answer a question than have you end up with something that was not right for you.

## If something goes wrong with a payment

Please get in touch before opening a dispute with PayPal. We can almost always sort it out faster directly — and we would like the chance to.
MD],

            ['how-we-work', 'how-we-work', 'How We Make Our Art', <<<'MD'
## We work with AI. Here is what that means.

Every piece in our store begins with AI image-generation tools. We say so openly, because it is part of how we work rather than something to tuck away.

A prompt, though, is where a piece starts — not where it ends.

## Our process

**Idea** — A mood, a palette, a room. We decide what we are trying to make before we make anything.

**Generation** — We generate, look, adjust, and generate again. A single piece can take dozens of attempts before the composition holds together.

**Selection** — We review everything and keep very little. Most of what we generate never leaves our drive.

**Refinement** — The survivors get edited, corrected, upscaled and colour-adjusted.

**Preparation** — Every file is prepared for print in standard sizes and ratios, so it works with whatever frame you already own. The resolution and formats for each piece are listed on its product page.

**Curation** — Pieces that belong together become sets and collections that work on a wall as a group.

## What we bring to it

Anyone can type a prompt. The work is in everything that comes after:

**Taste** — knowing which one of fifty results is worth keeping.

**Craft** — the editing, upscaling and colour work that makes a file print beautifully.

**Preparation** — print-ready resolution, real frame sizes, multiple ratios ready to go.

**Curation** — pieces chosen to live together.

That part is genuinely ours, and it is what you are paying for.

## Questions people ask

**Is my artwork original?** Each file is generated uniquely for our collection, and we never work from or copy existing artwork. One honest note: AI tools can produce similar results from similar ideas, so we cannot promise nothing resembling it exists anywhere.

**Do I own the copyright?** You receive a licence to use the files personally. The copyright status of AI-generated images is still developing and varies by country, which is why we sell a licence to use the files rather than claiming to transfer ownership.

**Can I sell prints of these?** Not under the standard personal-use licence — but extended licences exist for many pieces. Write to us.

**Will it look good printed?** Each product page lists the resolution and sizes for that piece, so you can check before buying. The rest comes down to your printer, paper and print service. A small test print first is always worth it.

**Why is it so affordable?** Our process lets us work at volume without cutting corners on preparation. We would rather a piece you love ends up on your wall than priced out of reach.

## Still deciding?

Some people prefer work made entirely by hand, and that is a lovely thing to want.

If you are wondering whether our pieces suit your space, send us a note. We are happy to help you decide, either way.
MD],
        ];
    }
}
