# Villa Mercedes — Living grounds design system

## Product
Villa Mercedes Nature Farm & Resort in Barangay Namnama, City of Ilagan, Isabela. Guests check dates, browse rooms/cottages, function halls, and packages, then reserve and pay. This system is for the **public guest site only** (home, rooms, halls, packages, gallery). Admin and booking forms stay calm and utilitarian.

## Brand identity
- Name: Villa Mercedes Nature Farm & Resort (short: Villa Mercedes)
- Tagline: Experience nature, comfort, and heartful hospitality.
- Logo: the real Villa Mercedes mark (leaf/farm emblem) plus wordmark. Never replace with initials, emoji, or a generic icon.
- Photography: real grounds — entrance, cottages, banquet hall, pool and slide. Photos lead. UI is a quiet overlay.

## Visual direction (target, not the current still brochure)
Cinematic nature-farm hospitality. Guests should feel they are already walking the grounds.

- Full-bleed photography with slow Ken Burns (scale ~1.0 → 1.08 over ~20s, alternate).
- Editorial headlines in Cormorant Garamond; body and forms in Figtree; short brand lockup in Outfit; occasional Great Vibes for a single quote.
- Glass “front desk” booking bar at the bottom of the hero — the handshake, not a marketing widget.
- Overlapping photo layers, rounded-3xl cards that lift and open (image scale + caption rise) on hover.
- Sections fade/slide up as the guest scrolls (opacity + 24px translate, 600–800ms, stagger 100ms). Honor `prefers-reduced-motion: reduce` (no Ken Burns, no travel).
- Do not invent a SaaS dashboard, neon gradients, or Inter/system-only type.

## Color (hard constraint)
Use ONLY these tokens:
- primary `#623f1f` / hover `#7a532c` / dark `#432910` / light `#f3eadf`
- secondary `#d6c4b0`
- accent bronze `#a67c52` / light `#f4ead9`
- background `#faf6f1` / surface `#ffffff`
- text `#1f140c` / muted `#6b5748`
- border `#e4d5c6`
- success `#059669`, warning `#d97706`, danger `#e11d48`

Hero overlays: black 15% → 65% vertical gradient, or primary-dark at ~80% on closing CTAs.

## Type
- Headlines: Cormorant Garamond, semibold, tracking tight, primary or white on photos
- UI/nav/forms: Figtree
- Script quote: Great Vibes, primary, one instance
- Buttons: Figtree semibold, 14–16px, min-height 44px, rounded-lg, primary fill white text

## Layout
- Max content `80rem` (`max-w-7xl`), hero inner `max-w-6xl`
- Hero: min-h-screen, header floats transparent then solid primary/95 on scroll
- Cards: white surface, border-border, rounded-3xl, shadow-sm → shadow-xl on hover
- Listing tabs: pill `.listing-tab`, current page primary fill
- Availability bar: `bg-surface/95 backdrop-blur-md rounded-2xl shadow-2xl`

## Motion
Slow. One ambient motion at a time (hero Ken Burns OR card hover). Duration 400–800ms for UI; Ken Burns 18–24s. No bounce, no autoplay carousels.

## Content rules
Keep real copy: Villa Mercedes, Ilagan, rooms & cottages, event hosting, resort packages, Check availability, Check dates. Do not invent fake occupancy numbers beyond the real stats (3 swimming pools, hotel & cottages, 4 function halls, day & overnight packages).
