# TODO — SEO Audit & Optimization Roadmap

> **Audit date:** 2026-09-15  
> **Auditor scope:** Full public marketing + booking surface of Villa Mercedes Digital Hospitality (Laravel Blade app)  
> **Evidence basis:** Codebase review + local crawl of `http://127.0.0.1:8000` (`php artisan serve`) + public SERP research (PH)  
> **Production caveat:** No public production hostname was confirmed in-repo (`APP_URL` example is `http://digital-hospitality-villa-mercedes.test`). Field CrUX, GSC, and live HTTPS checks must be re-run against the deployed origin once live.

---

## Context

| Item | Detail |
|------|--------|
| **Site URL (audit target)** | Local: `http://127.0.0.1:8000` / intended: `APP_URL` (Laragon vhost). Public SERP currently points brand demand to **Facebook** and directory listings (Glotels, City of Ilagan tourism), not this app. |
| **Scope** | Full public site: home, services listings (`/rooms`, `/halls`, `/packages`, `/cottages`, `/pools`, `/resort`), facility detail (`/facilities/{id}`), gallery, FAQ, reviews, about, contact, terms, privacy. Auth/admin portals assessed for crawl/noindex hygiene only. |
| **Target markets** | Philippines — primary **City of Ilagan / Isabela / Cagayan Valley**; secondary Metro Manila & Northern Luzon leisure travelers |
| **Languages** | English only (`APP_LOCALE=en`). No hreflang / multi-locale URLs. |
| **Primary business goals** | Organic discovery → availability check → authenticated reservation → deposit/payment for rooms, cottages, function halls, packages, and pools |
| **Target keyword themes** | Brand: `Villa Mercedes Nature Farm & Resort`, `Villa Mercedes Ilagan`; Local: `resort Ilagan Isabela`, `nature farm resort Ilagan`, `swimming pool Ilagan`, `function hall Ilagan`, `overnight package Ilagan`, `cottages Ilagan`; Intent: `book resort Ilagan`, `night swimming Ilagan` |

### Competitors observed (SERP / local)

| Competitor | Web presence signal | Implication |
|------------|---------------------|-------------|
| **Mt. ClaRamuel Resort & Events Place** | Dedicated site `mtclaramuelhotelresort.com` with location, rooms, events copy | Outranks thin/Facebook-only listings for “Ilagan resort” style queries once this app is live |
| **Garden of Beracah** | Strong Facebook + Glotels; pools + function hall messaging | Competes on day-use / events keywords |
| **Clary’s Resort & Events** | Listed on City of Ilagan tourism portal | Citation/tourism-directory gap |
| **City of Ilagan Hotel & Convention Center / hotels** | Tourism portal listings | Different product (hotel vs nature farm) but share event/stay SERPs |

### Crawl sample (lab, 2026-09-15)

| Path | Status | Approx TTFB (lab) | Notes |
|------|--------|-------------------|--------|
| `/` … `/privacy`, `/faq`, `/resort`, listings | **200** | ~160–310 ms | Healthy locally |
| `/sitemap.xml` | **404** | — | Missing |
| `/robots.txt` | **200** | ~8 ms | Present, permissive |
| `/facilities`, `/reserve-space` | **302 → /rooms** | — | Temporary redirect (should be 301) |
| `/nonexistent-page-seo-test` | **404** | — | Correct status (no custom branded view found) |
| `/login`, `/register`, `/admin/login` | **200** | 0.8–2.8 s | Indexable (no `noindex`) |
| `/home/featured` | **200** | — | HTML fragment (no full layout/title) |

---

## Audit Findings

### 1. Crawlability and Indexing

- [ ] **SEO-FIND-1.1 [Missing XML sitemap]**:
  - **Location**: Expected `/sitemap.xml` (no generator, route, or static file in repo)
  - **Description**: Local crawl returns **404** for `/sitemap.xml`. Without a sitemap, Google must discover URLs only via links; facility detail pages and deeper content risk delayed or incomplete indexing ([Google: Manage your sitemaps](https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview)).
  - **Impact**: Critical
  - **Recommendation**: Add a dynamic sitemap (Laravel route or package) listing all indexable public URLs; split by type if needed; submit in Search Console after go-live.

- [ ] **SEO-FIND-1.2 [robots.txt allows everything, including thin/auth surfaces]**:
  - **Location**: `public/robots.txt` — `User-agent: *` / `Disallow:` (empty)
  - **Description**: Syntax is valid and does not block marketing pages, but it also does not disallow `/admin`, `/customer`, `/login`, `/register`, `/dashboard`, `/home/featured`, or arrival-pass patterns. Combined with missing `noindex` on auth pages, bots may waste crawl budget on low-value URLs.
  - **Impact**: High
  - **Recommendation**: Keep marketing paths allowed; Disallow private app paths; add `Sitemap:` directive once sitemap exists.

- [ ] **SEO-FIND-1.3 [No canonical tags on any public page]**:
  - **Location**: `resources/views/layouts/customer.blade.php` `<head>` (and all pages using `<x-customer-layout>`)
  - **Description**: Homepage HTML has **no** `rel="canonical"`. Query variants (`check_in`, `guests`, capacity filters on listings) can create near-duplicate URLs without a preferred canonical ([Google: Canonicalization](https://developers.google.com/search/docs/crawling-indexing/canonicalization)).
  - **Impact**: Critical
  - **Recommendation**: Emit self-referencing canonicals on every indexable page; for filtered listings, canonicalize to the clean category URL unless filters are intentionally indexable.

- [ ] **SEO-FIND-1.4 [Auth and account URLs lack noindex]**:
  - **Location**: `/login`, `/register`, `/admin/login`, customer portal under `/customer/*`, `/dashboard`, `/profile`
  - **Description**: Only `resources/views/pages/arrival-pass.blade.php` sets `meta name="robots" content="noindex, nofollow"`. Login HTML has **no robots meta**. These pages compete for crawl attention and can appear in brand SERPs.
  - **Impact**: High
  - **Recommendation**: Add `noindex, nofollow` (or robots.txt Disallow + noindex) for auth, admin, and signed-in account shells.

- [ ] **SEO-FIND-1.5 [HTML fragment endpoint is publicly crawlable]**:
  - **Location**: `GET /home/featured` → `PageController::featured()` returns partial Blade without layout
  - **Description**: Returns a card grid fragment (no `<title>`, no full document). Indexed fragments create thin/duplicate content risk.
  - **Impact**: Medium
  - **Recommendation**: `X-Robots-Tag: noindex` header and/or Disallow in robots.txt; optionally require `Sec-Fetch-Dest` / AJAX header.

- [ ] **SEO-FIND-1.6 [Legacy redirects use 302 instead of 301]**:
  - **Location**: `routes/web.php` — `Route::redirect('/reserve-space', '/rooms')`, `Route::redirect('/facilities', '/rooms')`
  - **Description**: Lab crawl confirmed **HTTP 302** to `/rooms`. Temporary redirects do not consolidate ranking signals as strongly as permanent 301s ([Google: Redirects and Search](https://developers.google.com/search/docs/crawling-indexing/301-redirects)).
  - **Impact**: High
  - **Recommendation**: `Route::redirect(..., 301)` (or `permanentRedirect`).

- [ ] **SEO-FIND-1.7 [Hreflang N/A but locale not declared for PH targeting]**:
  - **Location**: Sitewide `lang="{{ app locale }}"` → `en` only
  - **Description**: International hreflang is not required (single language). Geographic targeting should rely on NAP, LocalBusiness schema, GSC property, and local content — currently under-specified in markup.
  - **Impact**: Low (informational)
  - **Recommendation**: Keep single-locale; strengthen local signals (schema + GBP) rather than adding fake hreflang.

### 2. Site Architecture and URL Structure

- [ ] **SEO-FIND-2.1 [Readable URL hierarchy is strong; equity concentration is uneven]**:
  - **Location**: Public routes in `routes/web.php`; nav in `App\Support\CustomerNav`
  - **Description**: Clean paths (`/rooms`, `/halls`, `/packages`, `/resort`, `/facilities/{facility}`) are SEO-friendly. Main nav omits **Contact** (footer-only), which increases click depth to a high-intent local page.
  - **Impact**: Medium
  - **Recommendation**: Add Contact to primary or utility nav; ensure every facility detail is linked from its category listing (verify no orphan facilities).

- [ ] **SEO-FIND-2.2 [Breadcrumbs present without BreadcrumbList schema]**:
  - **Location**: `resources/views/pages/facilities.blade.php` (visual breadcrumb); `resources/views/facilities/show.blade.php` (visual breadcrumb)
  - **Description**: Accessible breadcrumb `<nav aria-label="Breadcrumb">` exists, but no JSON-LD `BreadcrumbList` — missing rich-result eligibility ([Google: Breadcrumb](https://developers.google.com/search/docs/appearance/structured-data/breadcrumb)).
  - **Impact**: Medium
  - **Recommendation**: Add JSON-LD BreadcrumbList matching visible crumbs.

- [ ] **SEO-FIND-2.3 [Services listing keyword cannibalization risk]**:
  - **Location**: `/rooms` hero treats Services tabs (rooms/halls/packages) under shared “Services” breadcrumb; `/cottages` and `/pools` also exist alongside `/resort`
  - **Description**: Multiple URLs cover overlapping intents (cottages via `/cottages` and rooms listing; pools via `/pools` and `/resort`). Without unique titles/meta and clear primary keywords, Google may conflate pages.
  - **Impact**: High
  - **Recommendation**: Assign one primary keyword per URL; differentiate titles/H1s; use internal links to clarify preferred landing pages.

- [ ] **SEO-FIND-2.4 [Pagination / rel=prev-next]**:
  - **Location**: Public listings appear fully rendered (no classic paginated SERP archives found)
  - **Description**: No `rel=prev/next` needed if catalogs stay short; monitor if admin grows inventory into paginated public lists.
  - **Impact**: Low
  - **Recommendation**: If pagination is added, prefer `?page=` with self-canonical to page 1 or indexable page series consistently.

### 3. Site Performance and Core Web Vitals

> Lab proxies only (local Windows `artisan serve`). **No CrUX field data** until a public HTTPS origin is measured in PSI/GSC. Treat thresholds as targets, not certified scores.

- [ ] **SEO-FIND-3.1 [LCP risk: full-bleed JPG hero without dimensions]**:
  - **Location**: `resources/views/pages/home.blade.php` — `images/hero-aerial.jpg` (~205 KB), `fetchpriority="high"`, `min-h-screen`, **no `width`/`height`**
  - **Description**: Hero is the likely LCP element. Positive: `fetchpriority="high"` + `decoding="async"`. Gaps: JPEG (not WebP/AVIF), no explicit dimensions (CLS risk), spaces in other marketing image filenames (`rooms & cottage.jpg`, `resort pakage.jpg` up to **~477 KB**), CSS background heroes on listings (not `<img>`, harder for LCP optimization).
  - **Impact**: Critical (mobile LCP likely > 2.5s on 4G)
  - **Recommendation**: Convert heroes to responsive WebP/AVIF `<picture>`; set width/height or `aspect-ratio`; compress to ~80–120 KB mobile; preload LCP image in `<head>`.

- [ ] **SEO-FIND-3.2 [INP/FID risk: sitewide Alpine + 106 KB JS]**:
  - **Location**: Vite build `public/build/assets/app-CoydSQDS.js` (~106 KB); `resources/js/app.js` boots Alpine on every customer page; support-chat lazy-loaded (good)
  - **Description**: Main bundle is moderate but global. Interaction delay risk on low-end Android common in PH market. FID deprecated; monitor **INP < 200 ms** ([web.dev CWV](https://web.dev/articles/vitals)).
  - **Impact**: High
  - **Recommendation**: Code-split booking/flatpickr paths; defer non-critical Alpine components; keep support chat lazy (already patterned).

- [ ] **SEO-FIND-3.3 [CLS risk: images/fonts without reserved space]**:
  - **Location**: Home/marketing `<img>` tags largely omit width/height; multiple webfonts (Figtree, Outfit, Cormorant, Great Vibes) via Vite
  - **Description**: Empty-alt logos and late font swap can shift header/hero. Listing heroes use background-image (stable if aspect locked via CSS — verify).
  - **Impact**: High
  - **Recommendation**: Always set width/height or CSS aspect-ratio; `font-display: optional/swap` audit; reserve header height (already `h-20` — good).

- [ ] **SEO-FIND-3.4 [TTFB acceptable in lab; production caching weak for HTML]**:
  - **Location**: Response headers on `/`: `Cache-Control: no-cache, private`; `X-Powered-By: PHP/8.2.12`
  - **Description**: Lab TTFB ~160–310 ms for marketing pages (under 800 ms target). HTML is uncached (expected for sessionized Laravel). Featured partial correctly uses short public cache. Static assets via Vite hashes are cacheable by browsers when served correctly.
  - **Impact**: Medium (rises to High without OPcache/CDN in production)
  - **Recommendation**: Prefer Laragon/Apache or PHP-FPM with OPcache in prod (noted in `.env.example`); CDN for `/build` and `/storage`; edge cache anonymous marketing HTML if sessions allow.

- [ ] **SEO-FIND-3.5 [Uploaded facility images already WebP-optimized — marketing statics are not]**:
  - **Location**: `App\Services\PublicImageOptimizer` (WebP q82, max 1600px); `public/images/*.jpg` static marketing set
  - **Description**: Strong pipeline for admin uploads; static homepage/listing images remain large JPEGs with awkward filenames (spaces, typo `pakage`).
  - **Impact**: High
  - **Recommendation**: Run marketing assets through same optimizer; rename to kebab-case SEO filenames.

### 4. Mobile-Friendliness

- [ ] **SEO-FIND-4.1 [Viewport and responsive layout present]**:
  - **Location**: `layouts/customer.blade.php` — `meta name="viewport" content="width=device-width, initial-scale=1"`
  - **Description**: Tailwind responsive grids, `min-h-[44px]` CTAs (good touch targets), mobile nav in header. Mobile-first indexing readiness is structurally OK.
  - **Impact**: Low (positive finding / maintain)
  - **Recommendation**: Run Google Mobile-Friendly / Lighthouse mobile after production deploy; fix any overflow from fixed heroes.

- [ ] **SEO-FIND-4.2 [AMP not implemented]**:
  - **Location**: N/A
  - **Description**: AMP not present. Not required for hospitality sites in 2026; prioritize CWV instead ([Google AMP guidance evolution](https://developers.google.com/search/docs/crawling-indexing/amp)).
  - **Impact**: Low
  - **Recommendation**: Do not adopt AMP; invest in responsive performance.

### 5. HTTPS and Security

- [ ] **SEO-FIND-5.1 [HTTPS not enforced in application layer]**:
  - **Location**: No `URL::forceScheme('https')`, no HSTS middleware; `.env.example` documents `SESSION_SECURE_COOKIE` only as production comment; local `APP_URL` is `http://`
  - **Description**: Lab responses over HTTP. Production must terminate TLS, redirect HTTP→HTTPS (301), and set HSTS. Mixed content risk if any absolute `http://` asset URLs leak after go-live.
  - **Impact**: Critical (for production launch)
  - **Recommendation**: Force HTTPS in prod middleware/hosting; enable HSTS; secure cookies; audit asset URLs.

- [ ] **SEO-FIND-5.2 [Missing security headers]**:
  - **Location**: Homepage response headers (lab): no `Strict-Transport-Security`, `Content-Security-Policy`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`
  - **Description**: Security headers are ranking-adjacent trust/quality signals and protect users ([MDN / Google security practices](https://web.dev/articles/security-headers)).
  - **Impact**: Medium
  - **Recommendation**: Add middleware or reverse-proxy headers in production.

- [ ] **SEO-FIND-5.3 [Information disclosure header]**:
  - **Location**: `X-Powered-By: PHP/8.2.12`
  - **Description**: Exposes stack version; minor SEO/security hygiene issue.
  - **Impact**: Low
  - **Recommendation**: Disable `expose_php` / strip header at web server.

### 6. Structured Data and Schema Markup

- [ ] **SEO-FIND-6.1 [No JSON-LD structured data sitewide]**:
  - **Location**: Customer layout `<head>` — no `application/ld+json` (confirmed on homepage HTML)
  - **Description**: Missing `Organization` / `LodgingBusiness` or `Resort`, `WebSite` (+ `SearchAction` if site search added), `BreadcrumbList`, `FAQPage`, `Product`/`Offer` for bookable facilities, `AggregateRating` for reviews. Blocks rich results ([Google structured data gallery](https://developers.google.com/search/docs/appearance/structured-data/search-gallery)).
  - **Impact**: Critical
  - **Recommendation**: Implement JSON-LD in layout + page-specific schemas matching visible content only.

- [ ] **SEO-FIND-6.2 [FAQ page eligible for FAQPage schema but unmarked]**:
  - **Location**: `/faq` — `resources/views/pages/faq.blade.php` + accordion items
  - **Description**: Real Q&A content; high rich-result opportunity for local resort queries.
  - **Impact**: High
  - **Recommendation**: Emit `FAQPage` JSON-LD from the same FAQ models rendered on the page; validate in Rich Results Test.

- [ ] **SEO-FIND-6.3 [Reviews page eligible for AggregateRating only if tied to LocalBusiness]**:
  - **Location**: `/reviews`, home review section, `GuestReview` model
  - **Description**: Schema must reflect real guest reviews shown on-page; demo content flag `resort.demo_content` must stay **false** in production or risk guideline violations.
  - **Impact**: High
  - **Recommendation**: Attach ratings to `LodgingBusiness` only for verified published reviews; never invent ratings.

### 7. On-Page SEO Elements

- [ ] **SEO-FIND-7.1 [Duplicate identical title tags on all marketing pages]**:
  - **Location**: `resources/views/layouts/customer.blade.php` line 8: `<title>{{ config('resort.name') }}</title>`
  - **Description**: Lab confirmed **every** audited public page title is exactly `Villa Mercedes Nature Farm & Resort` (including `/rooms`, `/faq`, `/contact`, facility detail). Violates unique title requirement (50–60 chars, keyword differentiation) and crushes CTR in SERPs ([Google title links](https://developers.google.com/search/docs/appearance/title-link)).
  - **Impact**: Critical
  - **Recommendation**: Per-page titles, e.g. `Rooms & Cottages | Villa Mercedes Ilagan`, `Function Halls for Events | Villa Mercedes`.

- [ ] **SEO-FIND-7.2 [Missing meta descriptions sitewide]**:
  - **Location**: Customer layout `<head>` — no `meta name="description"`
  - **Description**: Google will auto-generate snippets; loses CTA control for booking intent.
  - **Impact**: Critical
  - **Recommendation**: Unique 150–160 character descriptions with location + CTA (“Check availability”, “Book stay”).

- [ ] **SEO-FIND-7.3 [Missing Open Graph / Twitter cards]**:
  - **Location**: Customer layout
  - **Description**: No `og:*` / `twitter:*` tags — weak social/link preview CTR when staff share URLs (off-page amplification).
  - **Impact**: Medium
  - **Recommendation**: Add OG title/description/image (use optimized hero WebP/JPG absolute HTTPS URL).

- [ ] **SEO-FIND-7.4 [H1 quality mixed; titles do not reinforce keywords]**:
  - **Location**: Home H1 = resort name (good brand); FAQ H1 = “Frequently Asked Questions” (generic); About H1 = resort name (duplicates home intent)
  - **Description**: Single H1 per page observed (good). About should differentiate (e.g., “Our story in Namnama, Ilagan”).
  - **Impact**: Medium
  - **Recommendation**: Align H1 with primary keyword while remaining natural.

- [ ] **SEO-FIND-7.5 [E-E-A-T and content depth gaps vs competitors]**:
  - **Location**: About, resort, packages pages; competitor Mt ClaRamuel long-form amenity/event copy
  - **Description**: Brand story and mission exist; thin spots for “how to get here”, seasonal tips, event planning guides, rate transparency landing pages. Owner expertise (family nature farm) is an E-E-A-T asset underused in crawlable text.
  - **Impact**: High
  - **Recommendation**: Publish location guide, event planning FAQ expansion, package comparison content; cite real policies from `config/resort.php`.

- [ ] **SEO-FIND-7.6 [Home copy still mentions TESDA training]**:
  - **Location**: `resources/views/pages/home.blade.php` hero supporting sentence references TESDA housekeeping/barista training
  - **Description**: Training modules appear removed from the codebase (deleted training controllers/models). Misleading copy harms trust/E-E-A-T and may create irrelevant keyword targeting.
  - **Impact**: High
  - **Recommendation**: Remove or relocate accurate offerings only; refresh hero copy to rooms/halls/pools/packages.

### 8. Image Optimization

- [ ] **SEO-FIND-8.1 [Marketing image filenames and weight]**:
  - **Location**: `public/images/resort pakage.jpg` (~477 KB), `rooms & cottage.jpg`, `hero-aerial.jpg` (~205 KB), etc.
  - **Description**: Spaces/typos in filenames hurt crawl clarity and caching; large LCP/below-fold assets.
  - **Impact**: High
  - **Recommendation**: Rename to `resort-package.webp`, etc.; compress; keep descriptive names with location keywords sparingly.

- [ ] **SEO-FIND-8.2 [Decorative logos use empty alt; meaningful images mostly OK]**:
  - **Location**: Header/footer logos `alt=""`; home hero/service cards have descriptive alts (good)
  - **Description**: Empty alt on decorative logos is acceptable if brand text is adjacent; ensure facility images always have non-empty alts (generally true).
  - **Impact**: Low–Medium
  - **Recommendation**: Spot-check gallery captions; avoid keyword stuffing in alt.

- [ ] **SEO-FIND-8.3 [Lazy loading partially implemented]**:
  - **Location**: Facility cards / gallery use `loading="lazy"`; home LCP correctly eager with `fetchpriority="high"`
  - **Description**: Pattern is mostly correct; listing CSS background heroes cannot lazy-load the same way.
  - **Impact**: Medium
  - **Recommendation**: Prefer `<img>` or `<picture>` for listing heroes to enable lazy/fetchpriority control.

- [ ] **SEO-FIND-8.4 [Zero-byte favicon]**:
  - **Location**: `public/favicon.ico` — **0 bytes**
  - **Description**: Broken favicon harms brand SERP appearance and browser trust.
  - **Impact**: Medium
  - **Recommendation**: Replace with real ICO/PNG set + `apple-touch-icon`.

### 9. Internal Linking and Anchor Text

- [ ] **SEO-FIND-9.1 [Footer Services anchors are repetitive]**:
  - **Location**: `resources/views/components/customer-footer.blade.php` — “Services” and “Rooms” both → `route('rooms')`
  - **Description**: Duplicate equity to `/rooms`; under-links unique intents if labels confuse users.
  - **Impact**: Low–Medium
  - **Recommendation**: Diversify anchors (“Book hotel rooms”, “Function halls in Ilagan”) pointing to correct URLs.

- [ ] **SEO-FIND-9.2 [Contact page orphaned from primary nav]**:
  - **Location**: `CustomerNav::marketingLinks()` — no Contact; contact only in footer
  - **Description**: Increases click depth for NAP/conversion page important for local SEO.
  - **Impact**: Medium
  - **Recommendation**: Add Contact to header or prominent home CTA row.

### 10. User Experience Signals

- [ ] **SEO-FIND-10.1 [No GA4 / analytics — bounce & dwell unknown]**:
  - **Location**: No gtag/GTM/GA4 snippets found in layouts
  - **Description**: Cannot measure organic engagement, scroll, or booking funnel until analytics ships.
  - **Impact**: High
  - **Recommendation**: Install GA4 + key events (`generate_lead`, `begin_checkout`, reservation submit).

- [ ] **SEO-FIND-10.2 [Custom 404 view not found]**:
  - **Location**: No `resources/views/errors/404.blade.php`; unknown URLs return framework 404
  - **Description**: Soft recovery links (home, rooms, contact) missing — hurts UX and internal link opportunities from error traffic.
  - **Impact**: Medium
  - **Recommendation**: Branded 404 with search/availability CTA.

- [ ] **SEO-FIND-10.3 [On-site search limited to FAQ category filters]**:
  - **Location**: FAQ filters; availability bar is the primary “search”
  - **Description**: Availability search is excellent for conversion UX; not a classic site search. Fine for SEO if crawlable landing pages remain strong.
  - **Impact**: Low
  - **Recommendation**: Keep availability UX; ensure result pages don’t create infinite indexable URL variants without canonicals.

### 11. Backlink Profile and Domain Trust

- [ ] **SEO-FIND-11.1 [Official site not yet the primary brand destination]**:
  - **Location**: SERP evidence — Waze, Glotels (lists **Website: facebook.com**), City of Ilagan tourism listing, Facebook page
  - **Description**: Backlink/citation equity currently accrues to Facebook and directories. When this app goes live, claim website field everywhere or remain invisible in local pack/website slot.
  - **Impact**: Critical (go-live dependency)
  - **Recommendation**: Update GBP, Facebook About, Glotels, tourism portal, Waze/Google Maps website URL to production domain; request citation updates.

- [ ] **SEO-FIND-11.2 [Backlink quantity/quality vs competitors cannot be fully scored pre-launch]**:
  - **Location**: N/A (no production domain in Ahrefs/Moz yet)
  - **Description**: Expect low DA until citations + PR. Competitor Mt ClaRamuel already has a crawlable site collecting links.
  - **Impact**: High
  - **Recommendation**: Post-launch: Ahrefs/Moz audit; pursue tourism.cityofilagan.com deep link, local news, Isabela travel blogs; avoid PBN/spam.

- [ ] **SEO-FIND-11.3 [Toxic link exposure unknown]**:
  - **Location**: Future GSC / disavow workflow
  - **Description**: No evidence of spam campaigns yet; monitor after domain indexes.
  - **Impact**: Low (preemptive)
  - **Recommendation**: Quarterly backlink audit; disavow only clear spam.

### 12. Local SEO

- [ ] **SEO-FIND-12.1 [NAP consistency is good in config; ensure all citations match]**:
  - **Location**: `config/resort.php` — Name, Barangay Namnama / City of Ilagan / Isabela, phone `0917 326 8271`, email `villamercedesresort@yahoo.com`
  - **Description**: Matches public directory mentions. Hours string is vague (“Open daily — visit our Facebook…”), weaker than competitor explicit hours (e.g., Mt ClaRamuel 8 AM–10 PM).
  - **Impact**: High
  - **Recommendation**: Publish exact hours on-site + GBP; keep NAP identical across maps/Facebook/directories.

- [ ] **SEO-FIND-12.2 [Google Business Profile / Maps website slot]**:
  - **Location**: Off-site GBP (not in codebase)
  - **Description**: Maps/Waze presence exists; website field likely Facebook. Primary local pack lever.
  - **Impact**: Critical
  - **Recommendation**: Verify GBP, set website to production booking URL, categories (Resort hotel / Farm stay / Event venue), products for packages, weekly posts, review responses.

- [ ] **SEO-FIND-12.3 [LocalBusiness schema missing]**:
  - **Location**: Contact + layout
  - **Description**: Address/phone visible but not machine-readable via schema.
  - **Impact**: High
  - **Recommendation**: `LodgingBusiness` or `Resort` JSON-LD with `geo`, `telephone`, `address`, `image`, `url`, `priceRange` if accurate.

- [ ] **SEO-FIND-12.4 [Review acquisition pipeline exists on-site]**:
  - **Location**: Guest reviews + invitation notifications in app
  - **Description**: Strong for E-E-A-T if reviews are genuine and also solicited on Google.
  - **Impact**: Medium (opportunity)
  - **Recommendation**: Dual CTA: on-site review + Google review link after checkout.

### 13. Content Marketing and Promotion

- [ ] **SEO-FIND-13.1 [Social channels configured; no share markup]**:
  - **Location**: `config/resort.php` Facebook + Instagram URLs; `x-social-links`
  - **Description**: Distribution exists off-site; on-page OG tags missing (FIND-7.3). No blog/news content type for ongoing organic topics.
  - **Impact**: Medium
  - **Recommendation**: Monthly local content (events calendar, night swimming advisories already posted on Glotels — bring first-party).

- [ ] **SEO-FIND-13.2 [Influencer/PR opportunity]**:
  - **Location**: Off-page
  - **Description**: Isabela tourism + student case studies (studylib) already mention the brand — reclaim narrative on owned domain.
  - **Impact**: Medium
  - **Recommendation**: Pitch City tourism updates; host media kit page with approved photos.

### 14. International SEO

- [ ] **SEO-FIND-14.1 [Single-language PH site — no hreflang needed]**:
  - **Location**: Sitewide
  - **Description**: No multi-region URL structure. Automatic language detection not present (good — avoids wrong-locale redirects).
  - **Impact**: Low
  - **Recommendation**: If Tagalog pages added later, use `/tl/` subdirectory + hreflang pairs; do not auto-redirect by IP.

### 15. Analytics and Monitoring

- [ ] **SEO-FIND-15.1 [No Google Analytics 4]**:
  - **Location**: Layouts / config
  - **Description**: Zero GA4/GTM references in application views.
  - **Impact**: Critical
  - **Recommendation**: Deploy GA4 with enhanced measurement; mark reservation conversion events.

- [ ] **SEO-FIND-15.2 [No Google Search Console verification hook]**:
  - **Location**: No `google-site-verification` meta or DNS instructions in repo
  - **Description**: Cannot monitor index coverage, CWV field data, or query CTR until property exists on production domain.
  - **Impact**: Critical
  - **Recommendation**: Verify domain property; submit sitemap; monitor Coverage + Experience.

- [ ] **SEO-FIND-15.3 [No rank tracking configured]**:
  - **Location**: Process gap
  - **Description**: Need baseline for brand + local head terms vs Mt ClaRamuel / Garden of Beracah.
  - **Impact**: Medium
  - **Recommendation**: Track 15–30 keywords weekly (GSC + third-party tool).

---

## Remediation Recommendations

### Immediate (0–7 days) — launch blockers & quick wins

- [ ] **SEO-REC-1.1 [Per-page title + meta description + canonical system]**:
  - **Priority**: Critical
  - **Effort**: 4–8 hours
  - **Expected Outcome**: Unique SERP snippets; +CTR on brand and category queries; duplicate consolidation
  - **Validation**: View-source on `/`, `/rooms`, `/faq`, `/facilities/{id}`; Rich results / Lighthouse SEO ≥ pass unique titles

- [ ] **SEO-REC-1.2 [XML sitemap + robots Sitemap directive + auth Disallow/noindex]**:
  - **Priority**: Critical
  - **Effort**: 4–6 hours
  - **Expected Outcome**: Faster discovery of facility URLs; less crawl waste
  - **Validation**: `/sitemap.xml` 200; GSC sitemap “Success”; auth URLs `noindex`

- [ ] **SEO-REC-1.3 [301 legacy redirects]**:
  - **Priority**: High
  - **Effort**: 15 minutes
  - **Expected Outcome**: Equity consolidation from `/facilities` and `/reserve-space`
  - **Validation**: `curl -I` shows `301` Location `/rooms`

- [ ] **SEO-REC-1.4 [JSON-LD LodgingBusiness + WebSite + BreadcrumbList + FAQPage]**:
  - **Priority**: Critical
  - **Effort**: 1–2 days
  - **Expected Outcome**: Eligibility for local/FAQ rich results
  - **Validation**: [Google Rich Results Test](https://search.google.com/test/rich-results) zero errors

- [ ] **SEO-REC-1.5 [Replace favicon; fix hero copy TESDA reference]**:
  - **Priority**: High
  - **Effort**: 1–2 hours
  - **Expected Outcome**: Brand trust + accurate E-E-A-T
  - **Validation**: Favicon loads; hero text matches live offerings

- [ ] **SEO-REC-1.6 [Production HTTPS + HSTS + secure cookies]**:
  - **Priority**: Critical
  - **Effort**: 2–4 hours (hosting-dependent)
  - **Expected Outcome**: Ranking eligibility / trust; no mixed content
  - **Validation**: SSL Labs A; HTTP→HTTPS 301; PSI security checks

### Short-term (1–4 weeks)

- [ ] **SEO-REC-2.1 [LCP image pipeline for marketing heroes]**:
  - **Priority**: Critical
  - **Effort**: 1–3 days
  - **Expected Outcome**: Mobile LCP toward &lt; 2.5s
  - **Validation**: PageSpeed Insights mobile field/lab; CrUX once available

- [ ] **SEO-REC-2.2 [GA4 + GSC + conversion events]**:
  - **Priority**: Critical
  - **Effort**: 4–8 hours
  - **Expected Outcome**: Measurable organic funnel
  - **Validation**: Realtime hits; GSC property verified; debugView events

- [ ] **SEO-REC-2.3 [GBP + citation website updates]**:
  - **Priority**: Critical
  - **Effort**: 1–2 days (outreach lag)
  - **Expected Outcome**: Local pack website points to booking site; brand SERP upgrade
  - **Validation**: Maps place website URL; tourism portal link check

- [ ] **SEO-REC-2.4 [OG tags + branded 404 + Contact in nav]**:
  - **Priority**: Medium
  - **Effort**: 3–5 hours
  - **Expected Outcome**: Better shares, recovery UX, local conversions
  - **Validation**: Facebook Sharing Debugger; manual 404 click-path

- [ ] **SEO-REC-2.5 [Facility Offer schema on detail pages]**:
  - **Priority**: Medium
  - **Effort**: 1 day
  - **Expected Outcome**: Clearer product understanding in Search (where eligible)
  - **Validation**: Rich Results Test per facility template

### Long-term (1–3 months)

- [ ] **SEO-REC-3.1 [Content hub: Ilagan travel / events / packages guides]**:
  - **Priority**: High
  - **Effort**: 2–4 weeks ongoing
  - **Expected Outcome**: Non-brand keyword rankings vs Mt ClaRamuel / Beracah
  - **Validation**: GSC queries for “Ilagan resort”, “function hall Ilagan”, impressions↑

- [ ] **SEO-REC-3.2 [Link & citation building program]**:
  - **Priority**: High
  - **Effort**: Ongoing (weeks)
  - **Expected Outcome**: Domain trust / referral traffic
  - **Validation**: Referring domains↑; organic sessions↑

- [ ] **SEO-REC-3.3 [CWV monitoring in CI + CrUX dashboard]**:
  - **Priority**: Medium
  - **Effort**: 2–3 days setup
  - **Expected Outcome**: Prevent regressions
  - **Validation**: Lighthouse CI budgets; GSC CWV “Good” URL groups

- [ ] **SEO-REC-3.4 [Review velocity on Google + on-site AggregateRating]**:
  - **Priority**: High
  - **Effort**: Ongoing
  - **Expected Outcome**: Local pack competitiveness
  - **Validation**: GBP rating count/response rate; schema remains accurate

---

## Proposed Code Changes

### A. Customer layout SEO stack (titles, description, canonical, robots, OG)

```diff
--- a/resources/views/layouts/customer.blade.php
+++ b/resources/views/layouts/customer.blade.php
@@
     <head>
         <meta charset="utf-8">
         <meta name="viewport" content="width=device-width, initial-scale=1">
         <meta name="csrf-token" content="{{ csrf_token() }}">
-
-        <title>{{ config('resort.name', config('app.name')) }}</title>
+
+        @php
+            $seoTitle = trim($seo['title'] ?? '') ?: config('resort.name');
+            $seoDescription = trim($seo['description'] ?? '') ?: config('resort.tagline').' '.config('resort.location').'.';
+            $seoCanonical = $seo['canonical'] ?? url()->current();
+            $seoRobots = $seo['robots'] ?? 'index,follow';
+            $seoImage = $seo['image'] ?? asset('images/hero-aerial.jpg');
+        @endphp
+        <title>{{ $seoTitle }}</title>
+        <meta name="description" content="{{ \Illuminate\Support\Str::limit($seoDescription, 160, '') }}">
+        <link rel="canonical" href="{{ $seoCanonical }}">
+        <meta name="robots" content="{{ $seoRobots }}">
+        <meta property="og:type" content="website">
+        <meta property="og:title" content="{{ $seoTitle }}">
+        <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($seoDescription, 200, '') }}">
+        <meta property="og:url" content="{{ $seoCanonical }}">
+        <meta property="og:image" content="{{ $seoImage }}">
+        <meta property="og:locale" content="en_PH">
+        <meta name="twitter:card" content="summary_large_image">
+        @stack('jsonld')
 
         <!-- Scripts -->
         @vite(['resources/css/app.css', 'resources/js/app.js'])
```

Example page usage:

```blade
{{-- resources/views/pages/faq.blade.php --}}
<x-customer-layout>
    @php
        $seo = [
            'title' => 'FAQs — Booking, Pools & Events | Villa Mercedes Ilagan',
            'description' => 'Answers about reservations, deposits, pools, and function halls at Villa Mercedes Nature Farm & Resort in Namnama, Ilagan, Isabela.',
        ];
    @endphp
    {{-- existing content --}}
</x-customer-layout>
```

> Prefer a dedicated `<x-seo>` Blade component or View Composer that reads route name → defaults map for maintainability.

### B. Permanent redirects

```diff
--- a/routes/web.php
+++ b/routes/web.php
-Route::redirect('/reserve-space', '/rooms')->name('reserve-space');
+Route::redirect('/reserve-space', '/rooms', 301)->name('reserve-space');
-Route::redirect('/facilities', '/rooms')->name('facilities.index');
+Route::redirect('/facilities', '/rooms', 301)->name('facilities.index');
```

### C. robots.txt

```txt
User-agent: *
Allow: /

Disallow: /admin
Disallow: /admin/
Disallow: /customer/
Disallow: /dashboard
Disallow: /profile
Disallow: /login
Disallow: /register
Disallow: /password
Disallow: /email/
Disallow: /home/featured
Disallow: /arrival/

Sitemap: https://YOUR-PRODUCTION-DOMAIN/sitemap.xml
```

### D. Minimal LodgingBusiness + WebSite JSON-LD (layout stack)

```blade
@push('jsonld')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'LodgingBusiness',
            '@id' => url('/').'#business',
            'name' => config('resort.name'),
            'url' => url('/'),
            'telephone' => config('resort.phone_href'),
            'email' => config('resort.email'),
            'image' => asset('images/hero-aerial.jpg'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Barangay Namnama',
                'addressLocality' => 'City of Ilagan',
                'addressRegion' => 'Isabela',
                'postalCode' => '3300',
                'addressCountry' => 'PH',
            ],
            'sameAs' => array_values(array_filter([
                config('resort.facebook_url'),
                config('resort.instagram_url'),
            ])),
        ],
        [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'url' => url('/'),
            'name' => config('resort.name'),
            'publisher' => ['@id' => url('/').'#business'],
            'inLanguage' => 'en-PH',
        ],
    ],
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) !!}
</script>
@endpush
```

### E. Sitemap route sketch

```php
// routes/web.php
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])
    ->name('sitemap');

// App\Http\Controllers\SitemapController — emit urlset for:
// home, about, reviews, gallery, contact, terms, privacy,
// rooms, halls, packages, cottages, pools, resort, faq,
// each public Facility::query()->availableForPublic()->get()
```

### F. Featured fragment noindex header

```diff
--- a/app/Http/Controllers/PageController.php
+++ b/app/Http/Controllers/PageController.php
         return response()
             ->view('pages.partials.featured-facilities', compact('featuredRooms'))
-            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
+            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300')
+            ->header('X-Robots-Tag', 'noindex, nofollow');
```

### G. Hero LCP hardening (illustrative)

```diff
 <img
-    src="{{ $hero }}"
+    src="{{ asset('images/hero-aerial.webp') }}"
     alt="Aerial view of Villa Mercedes Nature Farm &amp; Resort, with the pool, cottages, and villa among the Ilagan hills"
     fetchpriority="high"
     decoding="async"
+    width="1920"
+    height="1080"
     class="hero-photo hero-photo-aerial"
 >
```

Plus in `<head>`: `<link rel="preload" as="image" href="..." imagesrcset="..." imagesizes="100vw">`.

### H. Auth layout robots

```blade
{{-- layouts/guest.blade.php head --}}
<meta name="robots" content="noindex, nofollow">
```

---

## Commands

### Local validation

```bash
# Status + redirect class
curl -sI http://127.0.0.1:8000/sitemap.xml
curl -sI http://127.0.0.1:8000/robots.txt
curl -sI http://127.0.0.1:8000/facilities
curl -sI http://127.0.0.1:8000/reserve-space

# Unique titles smoke test (PowerShell)
$pages = '/','/rooms','/halls','/packages','/faq','/contact','/about'
$pages | ForEach-Object {
  $t = (Invoke-WebRequest "http://127.0.0.1:8000$_").Content
  if ($t -match '<title>(.*?)</title>') { "$_`t$($matches[1])" }
}

# Image optimization batch (existing artisan command if seeded)
php artisan optimize:public-images
# (confirm command name in app/Console/Commands/OptimizePublicImages.php)

# Build front-end
npm ci
npm run build
```

### Production / CI suggestions

```bash
# Lighthouse CI (add after deploy URL exists)
npx lighthouse https://YOUR-DOMAIN/ --only-categories=performance,seo,accessibility --form-factor=mobile --quiet

# Optional CI gate
npx lhci autorun
```

### Post-deploy monitoring checklist

```text
1. Google Search Console → Domain property → Submit sitemap.xml
2. PageSpeed Insights → Home, /rooms, /facilities/{popular} (mobile)
3. Rich Results Test → Home, /faq, /contact
4. GA4 DebugView → reservation funnel events
5. GBP insights → website clicks vs calls (weekly)
```

---

## Prioritized Roadmap Summary

| Phase | Focus | Impact | Effort |
|-------|--------|--------|--------|
| **Immediate** | Titles/meta/canonical, sitemap/robots, 301s, schema baseline, HTTPS, favicon, accurate hero copy | Critical | Days |
| **Short-term** | LCP images, GA4/GSC, GBP/citations, OG/404/nav, Offer schema | High | 1–4 weeks |
| **Long-term** | Content hub, links, CWV CI, review velocity | High (compounding) | 1–3 months |

### Impact × effort scoring (selected)

| ID | Impact | Effort | ROI projection |
|----|--------|--------|----------------|
| SEO-REC-1.1 Titles/meta | Critical | Hours | Highest CTR lift on existing brand demand |
| SEO-REC-1.2 Sitemap/robots | Critical | Hours | Indexation of facility inventory |
| SEO-REC-1.4 Schema | Critical | Days | Rich result eligibility + local clarity |
| SEO-REC-2.1 LCP | Critical | Days | CWV “Good” → ranking stability |
| SEO-REC-2.3 GBP | Critical | Days | Local pack website clicks → bookings |
| SEO-REC-3.1 Content | High | Weeks | Non-brand organic growth vs Mt ClaRamuel |

---

## Keyword Opportunities & Content Strategy

### Keyword research (seed set for PH)

| Primary keyword | Suggested landing URL | Intent | Notes |
|-----------------|----------------------|--------|-------|
| Villa Mercedes Nature Farm & Resort | `/` | Brand | Defend with title + GBP |
| Villa Mercedes Ilagan | `/` or `/about` | Brand+local | |
| resort Ilagan Isabela | `/resort` or `/` | Commercial | Compete with Mt ClaRamuel |
| nature farm resort Ilagan | `/about` | Commercial | Brand differentiator |
| function hall Ilagan | `/halls` | Commercial | High event value |
| swimming pool Ilagan / night swimming Ilagan | `/pools` or `/resort` | Commercial | Align with known FB/Glotels posts |
| overnight package Ilagan | `/packages` | Transactional | |
| cottages Ilagan Isabela | `/cottages` | Transactional | Avoid cannibalizing `/rooms` |
| book resort Ilagan | `/` availability + `/rooms` | Transactional | |

### Content calendar (90 days)

- [ ] **SEO-CONTENT-1.1**: Refresh About with owner story, exact hours, directions from Villa Jesusa / Ilagan proper (E-E-A-T)
- [ ] **SEO-CONTENT-1.2**: “Plan an event at Villa Mercedes” long-form on `/halls` (capacities, inclusions)
- [ ] **SEO-CONTENT-1.3**: “Day vs night vs overnight packages” comparison on `/packages`
- [ ] **SEO-CONTENT-1.4**: Seasonal post template (night swimming schedule) on owned domain, not only Facebook
- [ ] **SEO-CONTENT-1.5**: Prune/redirect any future thin facility pages; keep one URL per inventory item

### Content update / pruning

- [ ] **SEO-CONTENT-2.1**: Remove obsolete TESDA training claims from home hero
- [ ] **SEO-CONTENT-2.2**: Ensure `RESORT_DEMO_CONTENT=false` in production so demo reviews never publish
- [ ] **SEO-CONTENT-2.3**: Quarterly refresh of FAQ answers tied to `config/resort.php` policy hours

---

## Core Web Vitals Optimization Notes

### LCP
- [ ] **SEO-CWV-1.1**: WebP/AVIF heroes + preload
- [ ] **SEO-CWV-1.2**: Avoid CSS-only LCP backgrounds on `/rooms` et al.; use prioritized `<img>`
- [ ] **SEO-CWV-1.3**: Serve from CDN close to PH users

### FID / INP
- [ ] **SEO-CWV-2.1**: Split flatpickr / booking calculators from global `app.js`
- [ ] **SEO-CWV-2.2**: Keep support-chat dynamic import (already good)
- [ ] **SEO-CWV-2.3**: Reduce main-thread work during Alpine start

### CLS
- [ ] **SEO-CWV-3.1**: width/height on all content images
- [ ] **SEO-CWV-3.2**: Reserve iframe map space on contact/home (already min-heights — verify)
- [ ] **SEO-CWV-3.3**: Audit `x-reveal` animations for layout shifts

### Monitoring
- [ ] **SEO-CWV-4.1**: GSC Core Web Vitals report by template
- [ ] **SEO-CWV-4.2**: CrUX History API or PSI field data monthly
- [ ] **SEO-CWV-4.3**: Lighthouse CI on staging URLs

---

## Local SEO Focus

- [ ] **SEO-LOCAL-1.1 [Local pack]**: Complete GBP (categories, services, products, photos, hours, booking link)
- [ ] **SEO-LOCAL-1.2 [Review strategy]**: Respond to all Google reviews &lt;48h; invite post-stay via existing `ReviewInvitation` mail + Google link
- [ ] **SEO-LOCAL-1.3 [Local content]**: Directions, landmarks (Namnama), event seasonality pages
- [ ] **SEO-LOCAL-1.4 [Citations]**: tourism.cityofilagan.com, Glotels, Facebook, Instagram, Waze — identical NAP + website URL

---

## SEO Verification Checklists

### Technical SEO Verification

- [ ] Robots.txt is syntactically correct and allows crawling of key pages (**partial** — allows all; needs Disallow + Sitemap)
- [ ] XML sitemap is complete, valid, and submitted to Search Console (**fail** — missing)
- [ ] No unintentional noindex or canonical errors exist (**fail** — no canonicals; auth lacks intentional noindex)
- [ ] All pages return proper HTTP status codes (no soft 404s) (**pass** lab sample)
- [ ] Redirect chains are resolved to single-hop **301** redirects (**fail** — 302s)
- [ ] HTTPS is enforced site-wide with no mixed content (**fail** on lab HTTP; pending prod)
- [ ] Structured data validates without errors in Rich Results Test (**fail** — none present)

### Performance Verification

- [ ] LCP under 2.5s mobile/desktop (**at risk** — large JPG heroes)
- [ ] FID/INP under 200 ms (**unmeasured field**; JS budget watch)
- [ ] CLS under 0.1 (**at risk** — missing image dimensions)
- [ ] TTFB under 800 ms (**pass** lab; recheck prod)
- [ ] Images next-gen + sized (**partial** — uploads WebP; static marketing JPG)
- [ ] JS/CSS minified/deferred (**partial** — Vite build hashed; deferral incomplete)

### On-Page SEO Verification

- [ ] Unique keyword titles 50–60 chars (**fail**)
- [ ] Unique meta descriptions with CTA (**fail**)
- [ ] Exactly one H1 + logical hierarchy (**mostly pass**)
- [ ] No duplicate/thin issues (**fail** — duplicate titles; `/home/featured` thin)
- [ ] Alt text on meaningful images (**mostly pass**)
- [ ] Varied internal anchors (**partial**)

### Off-Page and Authority Verification

- [ ] Toxic backlinks monitored (**N/A pre-domain**)
- [ ] Natural anchor distribution (**N/A**)
- [ ] GBP claimed/optimized (**action required**)
- [ ] NAP consistent (**config strong; citations need website URL**)
- [ ] Brand SERP optimized (**currently Facebook-led**)

### Analytics and Tracking Verification

- [ ] GA4 installed (**fail**)
- [ ] Conversion events configured (**fail**)
- [ ] GSC connected (**fail**)
- [ ] Rank tracking configured (**fail**)
- [ ] Competitor dashboards (**fail**)

---

## Quality Assurance Task Checklist (deliverable)

- [x] All crawlability and indexing issues catalogued with specific URLs
- [x] Core Web Vitals assessed against thresholds (lab proxies; field pending production)
- [x] Title tags and meta descriptions audited for indexable templates (all duplicate / missing)
- [x] Content quality assessment includes E-E-A-T and competitor comparison
- [x] Backlink/citation posture analyzed; toxic links flagged as monitor-after-launch
- [x] Structured data gaps and rich-snippet opportunities identified
- [x] Every finding has Impact rating; recommendations include effort estimates
- [x] Remediation roadmap organized into Immediate / Short-term / Long-term

### Additional QA gates before coding starts

- [ ] All findings reference specific URLs, code lines, or measurable metrics
- [ ] Tool results included as evidence for critical findings (local crawl tables above; SERP citations named)
- [ ] Competitor benchmark data supports priorities (Mt ClaRamuel site vs Facebook-default directories)
- [ ] Recommendations cite Google/documentation practices
- [ ] Code examples provided for technical fixes
- [ ] Validation steps included for every recommendation
- [ ] ROI projections grounded in observed brand demand + local competition (not fabricated traffic numbers)

---

## Tracking Board (LLM-friendly)

Copy progress here as work completes:

- [ ] SEO-FIND-1.1 … SEO-FIND-15.3 reviewed
- [ ] SEO-REC-1.1 … SEO-REC-3.4 scheduled
- [ ] SEO-CONTENT-* drafted
- [ ] SEO-CWV-* implemented
- [ ] SEO-LOCAL-* completed
- [ ] Production re-audit with GSC + PSI + Rich Results Test scheduled for go-live week
