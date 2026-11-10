// Browser smoke tests (tests/e2e). They run against a server that is already up:
//   E2E_BASE_URL (default http://127.0.0.1:8124), started with GOOGLE_ANALYTICS_ID=G-E2ETEST so the
//   consent flow is on the page. CI starts it in the "Browser smoke tests" job.
// PW_CHROMIUM may point at a preinstalled Chromium (local sandboxes); CI installs its own.
import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "tests/e2e",
  timeout: 30_000,
  retries: 0,
  reporter: [["list"]],
  use: {
    baseURL: process.env.E2E_BASE_URL || "http://127.0.0.1:8124",
    trace: "off",
    launchOptions: process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {},
  },
});
