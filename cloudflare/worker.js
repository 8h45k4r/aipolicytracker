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
    const upstream = await fetch(new Request(url.toString(), { method: request.method, headers, body: request.body, redirect: "manual" }));
    const out = new Headers(upstream.headers);
    const location = out.get("location");
    if (location && location.includes(origin)) {
      out.set("location", location.replace(origin, new URL(request.url).hostname));
    }
    return new Response(upstream.body, { status: upstream.status, headers: out });
  },
};
