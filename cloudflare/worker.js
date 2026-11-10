// Edge proxy for the public domain. The origin hostname is not in the repository: set the
// ORIGIN_HOST variable on the Worker (Settings → Variables) to the host that serves the app.
export default {
  async fetch(request, env) {
    const origin = env.ORIGIN_HOST;
    if (!origin) {
      return new Response("Worker misconfigured: ORIGIN_HOST is not set.", { status: 503 });
    }
    const url = new URL(request.url);
    url.hostname = origin;
    const headers = new Headers(request.headers);
    headers.set("X-Forwarded-Host", new URL(request.url).hostname);
    headers.set("X-Forwarded-Proto", "https");
    let upstream;
    try {
      upstream = await fetch(new Request(url.toString(), { method: request.method, headers, body: request.body, redirect: "manual" }));
    } catch {
      // The origin is down or unreachable: a plain 502 the edge can show its error page for,
      // instead of an uncaught Worker exception.
      return new Response("The site is temporarily unavailable. Please try again in a minute.", {
        status: 502,
        headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store", "Retry-After": "60" },
      });
    }
    const out = new Headers(upstream.headers);
    const location = out.get("location");
    if (location && location.includes(origin)) {
      out.set("location", location.replace(origin, new URL(request.url).hostname));
    }
    return new Response(upstream.body, { status: upstream.status, headers: out });
  },
};
