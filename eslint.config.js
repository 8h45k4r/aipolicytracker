import js from "@eslint/js";
import globals from "globals";

export default [
    // "aipt-rewrite/**" is a local scratch copy of the whole tree (git-ignored). Without
    // it here, ESLint lints a second copy of every file under a config whose globs, being
    // relative to the root, no longer match — so each one fails on undefined browser globals.
    { ignores: ["node_modules/**", "public/**", "vendor/**", "storage/**", "bootstrap/cache/**", "aipt-rewrite/**", ".claude/**"] },
    js.configs.recommended,
    {
        files: ["cloudflare/**/*.js"],
        languageOptions: { ecmaVersion: 2022, sourceType: "module", globals: { ...globals.serviceworker } },
    },
    {
        // The agent server and the Node tests run under Node, not in a browser.
        files: ["agent/**/*.mjs", "tests/js/**/*.mjs", "scripts/**/*.mjs"],
        languageOptions: { ecmaVersion: 2022, sourceType: "module", globals: { ...globals.node } },
        rules: { "no-console": ["error", { allow: ["warn", "error"] }] },
    },
    {
        files: ["resources/js/**/*.js", "*.config.js", "eslint.config.js"],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "module",
            globals: { ...globals.browser, ...globals.node },
        },
        rules: {
            "no-unused-vars": "off",
            "no-empty": ["error", { allowEmptyCatch: true }],
            "no-console": ["error", { allow: ["warn", "error"] }],
        },
    },
];
