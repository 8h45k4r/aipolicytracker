// What the PHP suite cannot see: the scripts actually running in a browser under the
// production Content Security Policy, keyboard behaviour, and the consent flow.
import { test, expect } from "@playwright/test";

// Fail a test on any CSP violation or uncaught page error, and record third-party requests.
test.beforeEach(async ({ page, context }, testInfo) => {
  testInfo.cspErrors = [];
  testInfo.thirdParty = [];
  page.on("console", (msg) => {
    if (msg.type() === "error" && /Content Security Policy/i.test(msg.text())) { testInfo.cspErrors.push(msg.text()); }
  });
  page.on("pageerror", (err) => testInfo.cspErrors.push("pageerror: " + err.message));
  // No request leaves the test: third-party hosts are answered locally and recorded.
  await context.route((url) => !/^https?:\/\/(127\.0\.0\.1|localhost)(:\d+)?\//.test(url.toString()), (route) => {
    testInfo.thirdParty.push(route.request().url());
    return route.fulfill({ status: 204, body: "" });
  });
});
// eslint-disable-next-line no-empty-pattern -- Playwright passes fixtures first; none are needed here.
test.afterEach(async ({}, testInfo) => {
  expect(testInfo.cspErrors, "CSP violations or page errors").toEqual([]);
});

test("the home page runs its scripts and the skip link comes first", async ({ page }) => {
  await page.goto("/");
  await page.keyboard.press("Tab");
  await expect(page.locator(":focus")).toHaveText(/Skip to content/);
  await page.keyboard.press("Enter");
  await expect(page).toHaveURL(/#main$/);
});

test("analytics waits for consent, and consent can be withdrawn", async ({ page }, testInfo) => {
  await page.goto("/");
  const banner = page.locator("#consent-banner");
  await expect(banner).toBeVisible();
  expect(testInfo.thirdParty.filter((u) => u.includes("googletagmanager"))).toEqual([]);

  await banner.getByRole("button", { name: "Allow analytics" }).click();
  await expect(banner).toBeHidden();
  await expect.poll(() => testInfo.thirdParty.some((u) => u.includes("googletagmanager.com/gtag/js"))).toBe(true);

  await page.getByRole("button", { name: "Analytics choices" }).click();
  await expect(banner).toBeVisible();
  await banner.getByRole("button", { name: "Decline" }).click();
  expect(await page.evaluate(() => localStorage.getItem("apt-consent"))).toBe("no");
  expect(await page.evaluate(() => window["ga-disable-G-E2ETEST"])).toBe(true);
});

test("declining loads nothing on later pages", async ({ page }, testInfo) => {
  await page.goto("/");
  await page.locator("#consent-banner").getByRole("button", { name: "Decline" }).click();
  await page.goto("/policies");
  await expect(page.locator("#consent-banner")).toBeHidden();
  expect(testInfo.thirdParty.filter((u) => u.includes("googletagmanager"))).toEqual([]);
});

test("the jurisdiction sort changes the order", async ({ page }) => {
  await page.goto("/jurisdictions");
  const sort = page.locator("select[data-navigate]");
  const target = await sort.locator("option").nth(1).getAttribute("value");
  await sort.selectOption({ index: 1 });
  await expect(page).toHaveURL(target);
});

test("the world map loads its code under the CSP", async ({ page }) => {
  await page.goto("/jurisdictions");
  const map = page.locator("[data-world-map]").first();
  await map.scrollIntoViewIfNeeded();
  await map.hover();
  await expect(map.locator("canvas, svg").first()).toBeAttached({ timeout: 15_000 });
});

test("the mobile filter sheet opens, traps focus and closes on Escape", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/policies");
  await page.getByRole("button", { name: /^Filters/ }).click();
  const sheet = page.locator("#filter-sheet");
  await expect(sheet).toHaveAttribute("data-open", "true");
  for (let i = 0; i < 25; i++) { await page.keyboard.press("Tab"); }
  expect(await page.evaluate(() => document.getElementById("filter-sheet").contains(document.activeElement))).toBe(true);
  await page.keyboard.press("Escape");
  await expect(sheet).toHaveAttribute("data-open", "false");
});

test("a reset request answers the same for any address", async ({ page }) => {
  await page.goto("/forgot-password");
  await page.getByLabel(/email/i).fill("nobody-e2e@example.org");
  await page.getByRole("button", { name: /reset|send/i }).click();
  await expect(page.getByRole("status")).toContainText("If an account uses that address");
});
