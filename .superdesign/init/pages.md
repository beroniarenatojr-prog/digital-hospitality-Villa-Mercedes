# Page dependency trees (public living-resort targets)

Blade does not import JS modules; dependencies are `<x-*>` components and `@include` partials.

## / (Home)
Entry: `resources/views/pages/home.blade.php`
Dependencies:
- `resources/views/layouts/customer.blade.php`
  - `resources/views/components/customer-header.blade.php`
    - `resources/views/components/nav-link.blade.php`
    - `resources/views/components/responsive-nav-link.blade.php`
    - `resources/views/components/dropdown.blade.php`
    - `resources/views/components/dropdown-link.blade.php`
    - `resources/views/components/secondary-button.blade.php`
  - `resources/views/components/customer-footer.blade.php`
    - `resources/views/components/social-links.blade.php`
  - `resources/views/components/back-to-top.blade.php`
  - `resources/views/components/alert.blade.php`
- `resources/views/components/availability-bar.blade.php`
  - `resources/views/components/primary-button.blade.php`
- `resources/views/components/section-heading.blade.php`
- `resources/views/components/loading-card.blade.php`
- `resources/views/components/featured-facility-card.blade.php` (injected via `/home/featured`)
- `resources/views/pages/partials/featured-facilities.blade.php`
- `tailwind.config.js`
- `resources/css/app.css`

## /rooms, /halls, /packages
Entry: `resources/views/pages/facilities.blade.php`
Dependencies:
- `resources/views/layouts/customer.blade.php` (same shell as home)
- `resources/views/components/availability-bar.blade.php`
- `resources/views/components/facility-card.blade.php`
  - `resources/views/components/card.blade.php`
  - `resources/views/components/price-display.blade.php`
- `resources/views/components/empty-state.blade.php`
- `resources/css/app.css` (`.listing-tab`)

## /gallery
Entry: `resources/views/pages/gallery.blade.php`
Dependencies:
- `resources/views/layouts/customer.blade.php`
- `resources/views/pages/partials/page-intro.blade.php`
