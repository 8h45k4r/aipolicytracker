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
        "./node_modules/flowbite/**/*.js",
        flowbite.content(),
    ],

    theme: {
        extend: {
            fontFamily: {
                // sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    light: "#002147",
                    DEFAULT: "#0f4698",
                    dark: "#4c1d95",
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
