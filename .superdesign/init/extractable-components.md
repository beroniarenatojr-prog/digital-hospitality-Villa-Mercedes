# Extractable Superdesign components

## CustomerHeader
- Source: `resources/views/components/customer-header.blade.php`
- Category: layout
- Description: Fixed top nav over hero photos; logo mark + Villa Mercedes word, Reserve a Space dropdown, Gallery/About/FAQ/Contact, guest Log in
- Extractable props: activeItem (string, default: "home"), overlay (boolean, default: true)
- Hardcoded: logo mark image, nav labels, Stay dropdown items, Log in

## CustomerFooter
- Source: `resources/views/components/customer-footer.blade.php`
- Category: layout
- Description: Dark brown four-column footer with logo wordmark, quick links, reserve links, contact
- Extractable props: none required
- Hardcoded: logo wordmark, link labels, location/phone/email

## AvailabilityBar
- Source: `resources/views/components/availability-bar.blade.php`
- Category: layout
- Description: Glass booking desk — space type, check-in, check-out, guests, Check availability
- Extractable props: variant (string, default: "hero")
- Hardcoded: field labels, space options, helper copy

## FacilityCard
- Source: `resources/views/components/facility-card.blade.php`
- Category: basic
- Description: Listing card with photo, type badge, name, capacity, from-price, Check dates
- Extractable props: none (page-level data)
- Hardcoded: card chrome, Check dates label

## FeaturedFacilityCard
- Source: `resources/views/components/featured-facility-card.blade.php`
- Category: basic
- Description: Home featured stay card with hover lift/zoom
- Extractable props: none
- Hardcoded: Open to reserve badge, Check dates

## SectionHeading
- Source: `resources/views/components/section-heading.blade.php`
- Category: basic
- Description: Centered Cormorant heading with bronze rule
- Extractable props: title (string), tone (string, default: "dark")
- Hardcoded: rule size, type scale

## PrimaryButton
- Source: `resources/views/components/primary-button.blade.php`
- Category: basic
- Description: Brown filled CTA, 44px min height
- Extractable props: none — skip extraction (too primitive)
