import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
// const flowbite = require("flowbite/plugin");
const flowbite = require("flowbite-react/tailwind");

/** A colour read from a CSS variable of RGB channels, falling back to the light value. */
const tone = (name, rgb) => `rgb(var(--${name}, ${rgb}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
    // hover: styles apply only where a pointer can hover, so a tap on a phone does not
    // leave a button stuck in its hover state.
    future: { hoverOnlyWhenSupported: true },
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
        "./resources/js/**/*.jsx",
        "./resources/js/public.js",
        "./app/Enums/*.php",
        "./node_modules/flowbite/**/*.js",
        flowbite.content(),
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
                mono: ['"Space Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Brand tokens (from the logo). Use these semantic names in templates,
                // never raw hex values.
                // Each public colour is a CSS variable with the light value as its fallback, so
                // the public stylesheet can swap the set for dark mode (prefers-color-scheme)
                // while the admin, which defines no variables, keeps the light values.
                white: tone("c-white", "255 255 255"),
                // Always white: for text on the footer, which stays dark in both schemes.
                snow: "#ffffff",
                brand: {
                    navy: tone("c-navy", "0 33 71"), // primary (#002147)
                    ink: tone("c-ink", "0 20 43"), // deepest navy: emphasis, hover
                    blue: tone("c-blue", "0 106 172"), // links, secondary emphasis
                    cyan: tone("c-cyan", "0 156 224"), // accent, focus rings
                    sky: tone("c-sky", "0 153 220"),
                    paper: tone("c-paper", "245 247 250"), // tinted surfaces
                    line: tone("c-line", "216 222 232"), // hairlines
                    muted: tone("c-muted", "93 107 126"), // secondary text
                    body: tone("c-body", "30 42 59"), // body text
                    footer: tone("c-footer", "0 20 43"), // the footer band, dark in both schemes
                },
                // Semantic state colours for status, impact and verification badges.
                state: {
                    good: tone("c-good", "11 107 79"), goodbg: tone("c-goodbg", "231 244 239"),
                    info: tone("c-info", "0 106 172"), infobg: tone("c-infobg", "230 242 250"),
                    warn: tone("c-warn", "122 75 0"), warnbg: tone("c-warnbg", "255 243 220"),
                    bad: tone("c-bad", "155 28 46"), badbg: tone("c-badbg", "252 233 236"),
                    neutral: tone("c-neutral", "93 107 126"), neutralbg: tone("c-neutralbg", "245 247 250"),
                },
                primary: {
                    light: "#006AAC",
                    DEFAULT: "#002147",
                    dark: "#00142B",
                },
                secondary: {
                    light: "#3b82f6",
                    DEFAULT: "#2563eb",
                    dark: "#1d4ed8",
                },
                light: {
                    blue: "#7997c4",
                },
                "light-color": "rgba(205, 224, 241, 0.5)",
            },
            borderColor: (theme) => ({
                "light-border": theme("colors.light-color"),
            }),
            // One corner scale for the whole portal, read from the --radius-* tokens in
            // public.css (docs/reference/design-system.md). The names keep their Tailwind
            // meaning (sm is the smallest step), so every existing class lands on the scale.
            borderRadius: {
                none: "0",
                sm: "var(--radius-xs, 4px)", // badges, checkboxes, swatches, inline marks
                DEFAULT: "var(--radius-sm, 6px)", // menu items, small controls inside a panel
                md: "var(--radius-md, 8px)", // buttons, inputs, chips, callouts, toasts
                lg: "var(--radius-lg, 12px)", // cards, panels, tables, popovers
                xl: "var(--radius-xl, 16px)", // dialogs, sheets
                "2xl": "20px",
                full: "9999px", // pills, avatars, status dots
            },
            aspectRatio: {
                "1/1": "1 / 1",
            },
            objectFit: {
                contain: "contain",
            },
            blendMode: {
                "color-burn": "color-burn",
            },
        },
    },

    plugins: [forms, flowbite.plugin()],
};

// 002147
