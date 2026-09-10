// Cloudflare Worker: proxies the public domain to the App Service origin.
// Deployed as "aip-scaffolders-edge" with routes aipolicytracker.org/* and
// www.aipolicytracker.org/*. The Laravel app sets APP_URL to the public
// domain and forces its root URL, so generated links never expose the origin.
export default {
  async fetch(request) {
    const url = new URL(request.url);
    url.hostname = "aip-scaffolders.azurewebsites.net";
    const upstream = new Request(url.toString(), request);
    upstream.headers.set("X-Forwarded-Host", "aipolicytracker.org");
    upstream.headers.set("X-Forwarded-Proto", "https");
    return fetch(upstream, { redirect: "manual" });
  },
};
