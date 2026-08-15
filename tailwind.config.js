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
            },
            colors: {
                primary: {
                    DEFAULT: '#14532d',
                    hover: '#166534',
                },
                secondary: {
                    DEFAULT: '#d6d3d1',
                },
                accent: {
                    DEFAULT: '#115e59',
                },
                background: '#fafaf9',
                surface: '#ffffff',
                text: {
                    DEFAULT: '#1c1917',
                    muted: '#78716c',
                },
                border: '#e7e5e4',
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
        },
    },

    plugins: [forms],
};
