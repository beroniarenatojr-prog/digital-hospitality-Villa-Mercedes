import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
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
                sans: ['"Figtree Variable"', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['"Outfit Variable"', 'Outfit', ...defaultTheme.fontFamily.sans],
                // Serif is reserved for public headlines. Forms and nav stay on Figtree.
                heading: ['"Cormorant Garamond"', ...defaultTheme.fontFamily.serif],
                script: ['Great Vibes', 'cursive'],
            },
            colors: {
                primary: {
                    DEFAULT: '#623F1F', // Earthy brand brown; the existing logo remains the mark
                    hover: '#472A16',
                    dark: '#3F2614',
                    light: '#f1e4d4',
                },
                secondary: {
                    DEFAULT: '#6B7F5B', // Sage
                    light: '#e6ecdf',
                },
                accent: {
                    DEFAULT: '#C96F4F', // Terracotta
                    light: '#f4ddd4',
                },
                background: '#F8F6EE', // Warm cream
                surface: '#fffdf8',
                text: {
                    DEFAULT: '#2E3A2F', // Forest
                    muted: '#596356',
                },
                muted: {
                    DEFAULT: '#eee9de',
                    foreground: '#596356',
                },
                border: '#D9C9B2', // Sand
                success: {
                    DEFAULT: '#059669',
                    light: '#d1fae5',
                },
                warning: {
                    DEFAULT: '#d97706',
                    light: '#fef3c7',
                },
                danger: {
                    DEFAULT: '#e11d48',
                    light: '#ffe4e6',
                },
                info: {
                    DEFAULT: '#0284c7',
                    light: '#e0f2fe',
                },
                // Two steps of Tailwind's stock palette are load-bearing even
                // though no view names them: `@tailwindcss/forms` reads them to
                // paint every form control it styles. `gray.500` is the resting
                // border, the placeholder colour and the chevron baked into
                // `select` as a data-URI SVG; `blue.600` is the check mark, the
                // dot and the focus ring. Left alone, the guest portal renders a
                // cool grey and a blue tick against a warm brown palette, in
                // places no scan over class names can reach. Point both at the
                // tokens at the source instead of overriding the plugin's output.
                // A view that writes `text-gray-500` is already a palette
                // violation the guard fails on, so nothing legitimate reads these.
                gray: {
                    400: '#596356', // text.muted - the placeholder preflight reads
                    500: '#596356', // text.muted
                },
                blue: {
                    600: '#623F1F', // primary
                },
            },
            // Tailwind's own default hairline is gray-200, and a bare `border`
            // utility inherits it. In the guest views alone that is 97 cool-gray
            // hairlines against a warm palette — and no scan over class names can
            // see a default, because no class names it. `divide-*` follows
            // borderColor, so this covers those three uses as well.
            borderColor: ({ theme }) => ({
                DEFAULT: theme('colors.border'),
            }),
            boxShadow: {
                'glass': '0 8px 32px 0 rgba(0, 0, 0, 0.05)',
            },
            keyframes: {
                kenburns: {
                    '0%': { transform: 'scale(1) translate(0, 0)' },
                    '100%': { transform: 'scale(1.08) translate(-1.5%, -1%)' },
                },
                'hero-enter': {
                    '0%': { opacity: '0', transform: 'translateY(1rem)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                kenburns: 'kenburns 22s ease-in-out infinite alternate',
                'hero-copy': 'hero-enter 0.8s ease-out both',
                'hero-desk': 'hero-enter 0.9s ease-out 0.35s both',
            },
        },
    },

    plugins: [forms],
};
