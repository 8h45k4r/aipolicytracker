# Cloudflare edge

Cloudflare provides DNS, TLS and caching for the public domain and a small Worker (`worker.js`) that forwards requests to the application origin.

- **Worker:** deploy `worker.js` and attach routes for the apex and `www` hosts of the public domain. Do not use a wildcard route if other subdomains are served elsewhere.
- **Origin host:** set the `ORIGIN_HOST` variable on the Worker (Settings → Variables) to the hostname that serves the application. The value is deliberately not stored in this repository.
- **Cache:** HTML is served `no-store`; hashed assets are cached. The deploy workflow purges the zone cache after each release when the `CLOUDFLARE_ZONE_ID` and `CLOUDFLARE_API_TOKEN` repository secrets are set (token needs *Zone → Cache Purge*).
- **Rate limiting:** add rules for sign-in, registration, contribution and API paths in the dashboard; login is also throttled in the application.
