# Cloudflare edge

Cloudflare provides DNS, TLS and caching for the public domain and a small Worker (`worker.js`) that forwards requests to the application origin.

- **Worker:** deploy `worker.js` and attach routes for the apex and `www` hosts of the public domain. Do not use a wildcard route if other subdomains are served elsewhere.
- **Origin host:** set the `ORIGIN_HOST` variable on the Worker (Settings → Variables) to the hostname that serves the application. The value is deliberately not stored in this repository.
- **Cache:** HTML is served `no-store`; hashed assets, `robots.txt`, the sitemaps, `llms.txt`, the feeds and the open-data files are cached at the edge. After a successful deploy, the **Purge the Cloudflare cache** step in `.github/workflows/deploy.yml` purges the whole zone (`purge_everything`) through the Cloudflare API, so crawlers see the new release at once. It needs two repository (or `production` environment) secrets:

  | Secret | Value |
  |--------|-------|
  | `CLOUDFLARE_ZONE_ID` | The zone ID of the public domain (Cloudflare dashboard → the domain → Overview → API → Zone ID). |
  | `CLOUDFLARE_API_TOKEN` | An API token scoped to that zone with the single permission *Zone → Cache Purge → Purge*. Do not use the global API key. |

  The step runs only when both secrets are set; without them it is skipped and the deploy still succeeds. When it runs, an API error (bad token, wrong zone, a response without `"success": true`) fails the deploy job with the error Cloudflare returned, never the token. To purge by hand: `curl -X POST "https://api.cloudflare.com/client/v4/zones/$ZONE/purge_cache" -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" --data '{"purge_everything":true}'`.
- **Rate limiting:** add rules for sign-in, registration, contribution and API paths in the dashboard; login is also throttled in the application.
