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
                    DEFAULT: '#623f1f', // Logo brown
                    hover: '#7a532c',
                    dark: '#432910',
                    light: '#f3eadf',
                },
                secondary: {
                    DEFAULT: '#d6c4b0',
                },
                accent: {
                    DEFAULT: '#a67c52', // Bronze
                    light: '#f4ead9',
                },
                background: '#faf6f1',
                surface: '#ffffff',
                text: {
                    DEFAULT: '#1f140c',
                    muted: '#6b5748',
                },
                muted: {
                    DEFAULT: '#f3ebe3',
                    foreground: '#6b5748',
                },
                border: '#e4d5c6',
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
            },
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
