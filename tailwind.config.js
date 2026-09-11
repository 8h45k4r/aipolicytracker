import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";
// const flowbite = require("flowbite/plugin");
const flowbite = require("flowbite-react/tailwind");

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: "class",
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
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Source Serif 4"', "Georgia", "Cambria", "serif"],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Brand tokens (from the logo). Use these semantic names in templates,
                // never raw hex values.
                brand: {
                    navy: "#002147", // primary
                    ink: "#00142B", // deepest navy: footer, emphasis
                    blue: "#006AAC", // links, secondary emphasis
                    cyan: "#009CE0", // accent, focus rings
                    sky: "#0099DC",
                    paper: "#F5F7FA", // tinted surfaces
                    line: "#D8DEE8", // hairlines
                    muted: "#5D6B7E", // secondary text
                    body: "#1E2A3B", // body text
                },
                // Semantic state colours for status, impact and verification badges.
                state: {
                    good: "#0B6B4F", goodbg: "#E7F4EF",
                    info: "#006AAC", infobg: "#E6F2FA",
                    warn: "#7A4B00", warnbg: "#FFF3DC",
                    bad: "#9B1C2E", badbg: "#FCE9EC",
                    neutral: "#5D6B7E", neutralbg: "#F5F7FA",
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
