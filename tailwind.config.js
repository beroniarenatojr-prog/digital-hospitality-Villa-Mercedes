import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    DEFAULT: '#064e3b', // Deep Emerald
                    hover: '#047857',
                },
                secondary: {
                    DEFAULT: '#cbd5e1', // Slate 300
                },
                accent: {
                    DEFAULT: '#d97706', // Champagne/Gold
                },
                background: '#f8fafc',
                surface: '#ffffff',
                text: {
                    DEFAULT: '#0f172a',
                    muted: '#64748b',
                },
                border: '#e2e8f0',
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
            }
        },
    },

    plugins: [forms],
};
