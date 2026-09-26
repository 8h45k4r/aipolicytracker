import js from "@eslint/js";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import globals from "globals";

export default [
    // "aipt-rewrite/**" is a local scratch copy of the whole tree (git-ignored). Without
    // it here, ESLint lints a second copy of every file under a config whose globs, being
    // relative to the root, no longer match — so each one fails on undefined browser globals.
    { ignores: ["node_modules/**", "public/**", "vendor/**", "storage/**", "bootstrap/cache/**", "aipt-rewrite/**"] },
    js.configs.recommended,
    {
        files: ["cloudflare/**/*.js"],
        languageOptions: { ecmaVersion: 2022, sourceType: "module", globals: { ...globals.serviceworker } },
    },
    {
        // The agent server and its test run under Node, not in a browser.
        files: ["agent/**/*.mjs"],
        languageOptions: { ecmaVersion: 2022, sourceType: "module", globals: { ...globals.node } },
        rules: { "no-console": ["error", { allow: ["warn", "error"] }] },
    },
    {
        files: ["resources/js/**/*.{js,jsx}", "*.config.js", "eslint.config.js"],
        plugins: { react, "react-hooks": reactHooks },
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "module",
            parserOptions: { ecmaFeatures: { jsx: true } },
            globals: { ...globals.browser, ...globals.node, route: "readonly", Ziggy: "readonly" },
        },
        settings: { react: { version: "detect" } },
        rules: {
            ...react.configs.recommended.rules,
            ...react.configs["jsx-runtime"].rules,
            ...reactHooks.configs.recommended.rules,
            "react/prop-types": "off",
            "react/no-unescaped-entities": "off",
            "react/no-unknown-property": "off",
            "react/display-name": "off",
            "react-hooks/exhaustive-deps": "off",
            "no-unused-vars": "off",
            "no-empty": ["error", { allowEmptyCatch: true }],
            "no-console": ["error", { allow: ["warn", "error"] }],
        },
    },
];
