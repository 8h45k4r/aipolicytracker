// The edge Worker (cloudflare/worker.js): proxies to the origin, rewrites redirects back to
// the public host, and answers a plain 502 when the origin cannot be reached.
import { test } from "node:test";
import assert from "node:assert/strict";
import worker from "../../cloudflare/worker.js";

const env = { ORIGIN_HOST: "origin.example.internal" };

test("an unreachable origin gives a 502, not an exception", async () => {
  const realFetch = globalThis.fetch;
  globalThis.fetch = async () => { throw new TypeError("connect ECONNREFUSED"); };
  try {
    const res = await worker.fetch(new Request("https://aipolicytracker.org/policies"), env);
    assert.equal(res.status, 502);
    assert.equal(res.headers.get("cache-control"), "no-store");
  } finally {
    globalThis.fetch = realFetch;
  }
});

test("requests go to the origin host and redirects come back on the public host", async () => {
  const realFetch = globalThis.fetch;
  let seen;
  globalThis.fetch = async (req) => {
    seen = req;
    return new Response(null, { status: 301, headers: { location: "https://origin.example.internal/policies/eu-ai-act" } });
  };
  try {
    const res = await worker.fetch(new Request("https://aipolicytracker.org/policies/eu-ai-act/"), env);
    assert.equal(new URL(seen.url).hostname, "origin.example.internal");
    assert.equal(seen.headers.get("x-forwarded-host"), "aipolicytracker.org");
    assert.equal(res.status, 301);
    assert.equal(res.headers.get("location"), "https://aipolicytracker.org/policies/eu-ai-act");
  } finally {
    globalThis.fetch = realFetch;
  }
});

test("a missing origin setting is a 503", async () => {
  const res = await worker.fetch(new Request("https://aipolicytracker.org/"), {});
  assert.equal(res.status, 503);
});
