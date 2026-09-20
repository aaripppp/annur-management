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
            colors: {
                "primary-container": "#1e293b",
                "on-primary-fixed-variant": "#3c475a",
                "surface-container-low": "#f2f4f6",
                "error": "#ba1a1a",
                "secondary-fixed": "#d8e2ff",
                "surface-container-lowest": "#ffffff",
                "on-error-container": "#93000a",
                "surface-variant": "#e0e3e5",
                "tertiary": "#00190e",
                "surface-tint": "#545f73",
                "on-tertiary-container": "#00a472",
                "surface-container-highest": "#e0e3e5",
                "on-tertiary-fixed-variant": "#005236",
                "on-secondary-fixed-variant": "#004395",
                "on-primary-fixed": "#111c2d",
                "surface-container": "#eceef0",
                "error-container": "#ffdad6",
                "inverse-surface": "#2d3133",
                "on-surface-variant": "#45474c",
                "on-primary": "#ffffff",
                "on-primary-container": "#8590a6",
                "secondary": "#0058be",
                "surface-bright": "#f7f9fb",
                "on-surface": "#191c1e",
                "surface": "#f7f9fb",
                "on-background": "#191c1e",
                "primary": "#091426",
                "on-error": "#ffffff",
                "secondary-container": "#2170e4",
                "surface-dim": "#d8dadc",
                "outline-variant": "#c5c6cd",
                "secondary-fixed-dim": "#adc6ff",
                "on-secondary": "#ffffff",
                "inverse-on-surface": "#eff1f3",
                "tertiary-fixed": "#6ffbbe",
                "primary-fixed": "#d8e3fb",
                "on-secondary-fixed": "#001a42",
                "primary-fixed-dim": "#bcc7de",
                "tertiary-container": "#00301e",
                "inverse-primary": "#bcc7de",
                "on-tertiary": "#ffffff",
                "on-tertiary-fixed": "#002113",
                "background": "#f7f9fb",
                "outline": "#75777d",
                "tertiary-fixed-dim": "#4edea3",
                "on-secondary-container": "#fefcff",
                "surface-container-high": "#e6e8ea"
            },
            spacing: {
                "stack-lg": "24px",
                "container-max-width": "1440px",
                "stack-sm": "8px",
                "margin-mobile": "16px",
                "stack-md": "16px",
                "sidebar-width": "260px",
                "gutter": "24px",
                "margin-desktop": "32px"
            },
            fontFamily: {
                "body-lg": ["Inter", ...defaultTheme.fontFamily.sans],
                "body-sm": ["Inter", ...defaultTheme.fontFamily.sans],
                "headline-sm": ["Inter", ...defaultTheme.fontFamily.sans],
                "headline-md": ["Inter", ...defaultTheme.fontFamily.sans],
                "label-md": ["Inter", ...defaultTheme.fontFamily.sans],
                "body-md": ["Inter", ...defaultTheme.fontFamily.sans],
                "display-lg": ["Inter", ...defaultTheme.fontFamily.sans],
                "numeric-data": ["Inter", ...defaultTheme.fontFamily.sans]
            },
            fontSize: {
                "body-lg": ["16px", { "lineHeight": "24px", "fontWeight": "400" }],
                "body-sm": ["12px", { "lineHeight": "18px", "fontWeight": "400" }],
                "headline-sm": ["20px", { "lineHeight": "28px", "fontWeight": "600" }],
                "headline-md": ["24px", { "lineHeight": "32px", "letterSpacing": "-0.01em", "fontWeight": "600" }],
                "label-md": ["14px", { "lineHeight": "20px", "fontWeight": "500" }],
                "body-md": ["14px", { "lineHeight": "20px", "fontWeight": "400" }],
                "display-lg": ["36px", { "lineHeight": "44px", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                "numeric-data": ["16px", { "lineHeight": "24px", "letterSpacing": "0.01em", "fontWeight": "600" }]
            }
        },
    },

    plugins: [forms],
};

