# Theme tokens — Villa Mercedes public site

Framework: Laravel 11 Blade + Vite + Tailwind CSS 3.4 + Alpine.js 3. No React/Vue component library.

## Compact token summary

### Color palette
- primary DEFAULT `#623f1f` (logo brown)
- primary hover `#7a532c`
- primary dark `#432910`
- primary light `#f3eadf`
- secondary `#d6c4b0`
- accent `#a67c52` (bronze), accent light `#f4ead9`
- background `#faf6f1`
- surface `#ffffff`
- text `#1f140c`
- text muted `#6b5748`
- muted `#f3ebe3` / muted foreground `#6b5748`
- border `#e4d5c6`
- success `#059669` / light `#d1fae5`
- warning `#d97706` / light `#fef3c7`
- danger `#e11d48` / light `#ffe4e6`
- info `#0284c7` / light `#e0f2fe`

### Typography
- sans: Figtree (body, forms, nav)
- display: Outfit (short brand name in header)
- heading: Cormorant Garamond (public headlines)
- script: Great Vibes (about teaser quote)
- Typical scale: section titles `text-4xl md:text-5xl font-heading font-semibold text-primary`; hero `text-5xl sm:text-6xl md:text-7xl`

### Spacing, radius, shadow
- Page gutters: `px-4 sm:px-6 lg:px-8`, max width `max-w-7xl` / `max-w-6xl` (hero)
- Cards: `rounded-3xl` (spaces), `rounded-2xl` (availability bar), `rounded-xl` (FAQ)
- Buttons: `rounded-lg`, min height `44px`
- Shadow: `shadow-sm`, `shadow-md`, `shadow-lg`, `shadow-2xl` (hero bar), `shadow-glass` `0 8px 32px 0 rgba(0,0,0,0.05)`
- Breakpoints: Tailwind defaults (sm 640, md 768, lg 1024)

### Motion (current)
- Header hide on scroll; gallery/room cards `group-hover:scale-105` 500ms; card lift `hover:-translate-y-1`
- No Ken Burns, no scroll-reveal, no reduced-motion policy yet

### Brand
- Name: Villa Mercedes Nature Farm & Resort, Ilagan City, Isabela
- Tagline: Experience nature, comfort, and heartful hospitality.
- Logos: `public/images/logo-villa-mercedes-mark.png` (header), `public/images/logo-villa-mercedes.png` (footer)

## Raw source — tailwind.config.js

```js
import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Outfit', ...defaultTheme.fontFamily.sans],
                heading: ['"Cormorant Garamond"', ...defaultTheme.fontFamily.serif],
                script: ['Great Vibes', 'cursive'],
            },
            colors: {
                primary: { DEFAULT: '#623f1f', hover: '#7a532c', dark: '#432910', light: '#f3eadf' },
                secondary: { DEFAULT: '#d6c4b0' },
                accent: { DEFAULT: '#a67c52', light: '#f4ead9' },
                background: '#faf6f1',
                surface: '#ffffff',
                text: { DEFAULT: '#1f140c', muted: '#6b5748' },
                muted: { DEFAULT: '#f3ebe3', foreground: '#6b5748' },
                border: '#e4d5c6',
                success: { DEFAULT: '#059669', light: '#d1fae5' },
                warning: { DEFAULT: '#d97706', light: '#fef3c7' },
                danger: { DEFAULT: '#e11d48', light: '#ffe4e6' },
                info: { DEFAULT: '#0284c7', light: '#e0f2fe' },
            },
            boxShadow: {
                glass: '0 8px 32px 0 rgba(0, 0, 0, 0.05)',
            },
        },
    },
    plugins: [forms],
};
```

## Raw source — resources/css/app.css

```css
@import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Figtree:ital,wght@0,300..900;1,300..900&family=Great+Vibes&family=Outfit:wght@100..900&display=swap');

@tailwind base;
@tailwind components;
@tailwind utilities;

[x-cloak] { display: none !important; }
[hidden] { display: none !important; }

@layer utilities {
    .glass { @apply bg-surface/70 backdrop-blur-md border border-white/20 shadow-glass; }
}

@layer components {
    .listing-tab {
        @apply inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-semibold text-text-muted transition hover:bg-primary-light hover:text-primary;
    }
    .listing-tab[aria-current='page'] {
        @apply bg-primary text-white hover:bg-primary hover:text-white;
    }
}

:focus-visible {
    outline: 2px solid #623f1f;
    outline-offset: 2px;
}
```
