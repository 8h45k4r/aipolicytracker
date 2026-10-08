import js from "@eslint/js";
import eslintReact from "@eslint-react/eslint-plugin";
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
        // The agent server and the Node tests run under Node, not in a browser.
        files: ["agent/**/*.mjs", "tests/js/**/*.mjs"],
        languageOptions: { ecmaVersion: 2022, sourceType: "module", globals: { ...globals.node } },
        rules: { "no-console": ["error", { allow: ["warn", "error"] }] },
    },
    {
        files: ["resources/js/**/*.{js,jsx}", "*.config.js", "eslint.config.js"],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "module",
            parserOptions: { ecmaFeatures: { jsx: true } },
            globals: { ...globals.browser, ...globals.node, route: "readonly", Ziggy: "readonly" },
        },
        rules: {
            "no-unused-vars": "off",
            "no-empty": ["error", { allowEmptyCatch: true }],
            "no-console": ["error", { allow: ["warn", "error"] }],
        },
    },
    {
        // React rules for the account pages. eslint-plugin-react has no ESLint 10
        // release, so the React rules come from @eslint-react, which does.
        files: ["resources/js/**/*.jsx"],
        ...eslintReact.configs.recommended,
        plugins: { ...eslintReact.configs.recommended.plugins, "react-hooks": reactHooks },
        rules: {
            ...eslintReact.configs.recommended.rules,
            ...reactHooks.configs["recommended-latest"].rules,
            // @eslint-react runs the same two checks; one report per problem is enough.
            "react-hooks/exhaustive-deps": "off",
            "react-hooks/set-state-in-effect": "off",
        },
    },
];
