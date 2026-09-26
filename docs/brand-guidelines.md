# Brand Guidelines — Villa Mercedes Nature Farm & Resort

> Last updated: 2026-09-25  
> Status: Active Source of Truth  
> Product: Villa Mercedes Nature Farm & Resort (Digital Hospitality System)

## Quick Reference

| Element | Value |
|---------|-------|
| Primary Color | #623F1F |
| Secondary Color | #6B7F5B |
| Accent Color | #C96F4F |
| Primary Font | Figtree Variable |
| Heading Font | Cormorant Garamond |
| Voice | Heartful, Grounded, Welcoming, Calm |

---

## 1. Color Palette

The resort adheres to a single unified earthy palette inspired by the natural grounds, timber architecture, and landscape in Ilagan, Isabela. No default cool-gray Tailwind scales are permitted.

### Primary Colors

| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Primary Earth Brown | #623F1F | rgb(98,63,31) | Main brand fill, links, primary buttons, focus rings |
| Primary Hover | #472A16 | rgb(71,42,22) | Interactive hover states |
| Primary Dark | #3F2614 | rgb(63,38,20) | Active/pressed states, deep surfaces |
| Primary Light | #F1E4D4 | rgb(241,228,212) | Soft brand tints, selection highlights |

### Secondary Colors

| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Secondary Sage | #6B7F5B | rgb(107,127,91) | Nature accents, supporting badges, positive emphasis |
| Secondary Light | #E6ECDF | rgb(230,236,223) | Subtle sage surface tints |

### Accent Colors

| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Accent Terracotta | #C96F4F | rgb(201,111,79) | Call-to-action highlights, attention items |
| Accent Light | #F4DDD4 | rgb(244,221,212) | Soft terracotta tints, warm badges |

### Neutral Palette

| Name | Hex | RGB | Usage |
|------|-----|-----|-------|
| Canvas Background | #F8F6EE | rgb(248,246,238) | Warm cream page canvas |
| Surface Panel | #FFFDF8 | rgb(255,253,248) | Cards, panels, modal containers |
| Forest Ink Text | #2E3A2F | rgb(46,58,47) | Primary typography, high-contrast readability |
| Muted Text | #596356 | rgb(89,99,86) | Secondary labels, captions, metadata |
| Muted Fill | #EEE9DE | rgb(238,233,222) | Neutral fills, recessed wells |
| Sand Border | #D9C9B2 | rgb(217,201,178) | Borders, hairlines, divider rules |

### Semantic Colors

| State | Hex | Usage |
|-------|-----|-------|
| Success | #059669 | Positive status, verified payments, confirmed bookings |
| Success Light | #D1FAE5 | Soft background for success badges |
| Warning | #D97706 | Pending confirmations, deposit deadlines |
| Warning Light | #FEF3C7 | Soft background for warning alerts |
| Danger | #E11D48 | Errors, rejected proofs, system alerts |
| Danger Light | #FFE4E6 | Soft background for error banners |
| Info | #0284C7 | Informational notes, check-in instructions |
| Info Light | #E0F2FE | Soft background for info cards |

### Preflight and Form Plugin Alignments

Tailwind forms defaults are re-pointed in `tailwind.config.js`:
- `gray.400` → `#596356` (Placeholder text)
- `gray.500` → `#596356` (Control borders & select chevrons)
- `blue.600` → `#623F1F` (Checkboxes, radio dots, focus rings)

---

## 2. Typography

Self-hosted via Fontsource. Standardized across all customer and back-office interfaces.

### Font Stack

```css
--font-heading: 'Cormorant Garamond', Georgia, serif;
--font-body: 'Figtree Variable', -apple-system, BlinkMacSystemFont, sans-serif;
--font-display: 'Outfit Variable', -apple-system, BlinkMacSystemFont, sans-serif;
--font-script: 'Great Vibes', cursive;
```

### Type Usage Hierarchy

| Role | Family | Token | Application |
|------|--------|-------|-------------|
| Editorial Headings | Cormorant Garamond | `font-heading` | Page titles, hero H1, section lead titles |
| Body & UI | Figtree Variable | `font-sans` | Body copy, forms, tables, buttons, navigation |
| Brand Lockup | Outfit Variable | `font-display` | Header resort title lockup, compact marks |
| Decorative Accent | Great Vibes | `font-script` | Max one decorative quote or welcome flourish per surface |

---

## 3. Brand Voice & Messaging Framework

### Brand Core Identity

| Element | Specification |
|---------|---------------|
| Full Name | Villa Mercedes Nature Farm & Resort |
| Short Name | Villa Mercedes |
| Tagline | Experience nature, comfort, and heartful hospitality. |
| Location | Barangay Namnama, City of Ilagan, Isabela, Philippines |
| Mission | To provide a heartful hospitality service that will engrave a unique experience in the heart and mind of every guest. |
| Vision | To be the guests’ preferred destination of choice — a place they return to and remember. |

### Core Attributes

| Attribute | Description |
|-----------|-------------|
| **Heartful Hospitality** | Warm, genuine Filipino hospitality rooted in care and personalized service. |
| **Natural Sanctuary** | Lush nature-farm grounds, open air, timber architecture, and peaceful retreat. |
| **Quiet Clarity** | Transparent pricing, straightforward booking rules, and zero hidden surprises. |
| **Trustworthy Stewardship** | Dignified CARE values governing every transaction and guest interaction. |

### Values: CARE Framework

- **C — Compassion:** Being kind and caring to everyone we encounter.
- **A — Accountability:** Being responsible for our words, actions, and results.
- **R — Respect:** Valuing everyone and treating people with dignity and professionalism.
- **E — Ethics:** Doing the right thing in an honest, fair, and responsible way.

### Brand Personality

| Trait | Description |
|-------|-------------|
| **Welcoming** | Gracious and hospitable; invites guests into a relaxing countryside home. |
| **Grounded** | Authentic and calm; avoids artificial hype, flashiness, or corporate coldness. |
| **Clear** | Unambiguous terms, concise policies, and clean operational instructions. |
| **Respectful** | Attentive and professional across both guest and administrative desks. |

### Voice Chart

| Trait | We Are | We Are Not |
|-------|--------|------------|
| Welcoming | Heartful, warm, hospitable | Gushing, insincere, intrusive |
| Grounded | Earthy, authentic, calm | SaaS-styled, neon, flashy |
| Clear | Direct, precise, informative | Bureaucratic, confusing, vague |
| Dignified | Courteous, dependable, ethical | Flippant, defensive, robotic |

### Tone by Context

| Context | Tone | Example |
|---------|------|---------|
| Guest Discovery | Atmospheric, inviting, serene | "Walk beneath mango groves and unwind by the water." |
| Booking Wizard | Focused, calm, step-by-step | "Space → Plan → Guests → Confirm." |
| Payment & Deposit | Direct, reassuring, explicit | "Scan the official QR Ph code and submit your transaction slip for desk review." |
| Admin Desk | Utilitarian, dense, calm | "Review pending deposits and verify ledger balance." |

### Prohibited Terms & Shared Vocabulary Rules

| Avoid | Use Instead | Reason |
|-------|-------------|--------|
| Cancel (guest side) | Reschedule or Withdraw | Guests reschedule or request withdrawal; only the resort cancels. |
| Seamless | Effortless, direct | Overused marketing cliché. |
| Synergy | Partnership, care | Empty corporate jargon. |
| Cheap / Budget | Accessible, value, package rate | Diminishes resort quality. |
| Luxury resort | Nature farm & resort | The brand is authentic, earthy countryside hospitality. |

---

## 4. Brand Marks & Asset Rules

### Approved Brand Marks

| Asset Path | Usage |
|------------|-------|
| `public/images/logo-villa-mercedes-mark.png` | Header lockup, auth marks, compact favicon |
| `public/images/logo-villa-mercedes.png` | Footer lockup, admin sidebar header, arrival pass header |
| Real Grounds Photography | Hero backgrounds, facility listings, photo gallery |

### Rules for Assets

- Never replace the logo mark with emoji, initials, or generic leaf vectors.
- Photography-first: UI elements are calm overlays on real grounds photography.
- No artificial gradients, no neon glow, and no saturated purple or blue tech accents.
- All monetary amounts must format via `<x-amount>` / `js/money.js` with `₱` and two decimal places.
- Badges must consume backend state enums (`label()` and `tone()`), never ad-hoc strings.

---

## 5. AI Image Generation & Photography Direction

### Base Prompt Template

```
Cinematic documentary photograph of Villa Mercedes Nature Farm & Resort in Ilagan Isabela Philippines, warm natural sunlight, lush tropical greenery, rustic timber architecture, earthy brown wood tones (#623F1F), sage foliage (#6B7F5B), peaceful resort swimming pool, warm cream highlights (#F8F6EE), authentic rural hospitality, 35mm lens, realistic depth of field, no artificial neon, no CGI gloss.
```

### Style Keywords

| Category | Keywords |
|----------|----------|
| **Lighting** | Golden hour, soft daylight, warm evening lodge lamps, nature-dappled shade |
| **Mood** | Peaceful, serene, welcoming, relaxing, authentic, restorative |
| **Composition** | Wide architectural landscapes, grounds paths, poolside reflections, editorial framing |
| **Treatment** | Warm earthy grading, rich timber textures, organic greens, zero digital haze |
| **Aesthetic** | Nature farm hospitality, Philippine countryside, timber and stone craft |

### Visual Mood Descriptors

- Warm tropical sanctuary with open-air gazebos and wooden lodges
- Sparkling clean pools framed by native landscaping and tropical trees
- Welcoming family celebrations and quiet weekend getaways
- Restrained, elegant countryside atmosphere without commercial clutter

### Visual Don'ts

| Avoid | Reason |
|-------|--------|
| Neon lighting, cyberpunk glows | Violates earthy resort identity |
| Glass skyscraper modernism | Incompatible with nature farm setting |
| Over-saturated cyan swimming pools | Unrealistic; conflicts with warm palette |
| Corporate office aesthetics | Contradicts hospitality relaxation |
| Generic stock models without Filipino context | Misrepresents genuine local character |

---

## Changelog

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-09-25 | Formalized brand guidelines from DESIGN.md, config/resort.php, and tailwind.config.js for skill automation. |
