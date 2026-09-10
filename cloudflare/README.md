# Cloudflare edge configuration

The application runs on a PHP host; Cloudflare provides DNS, TLS, CDN, and a tiny
Worker that forwards requests for the public domain to the origin.

- **DNS:** `aipolicytracker.org` CNAME (proxied) to the origin hostname; `www` CNAME to the apex.
- **Worker:** `worker.js`, deployed as `aip-scaffolders-edge` with routes
  `aipolicytracker.org/*` and `www.aipolicytracker.org/*`. Deploy from the dashboard
  editor or with `npx wrangler deploy` after creating a `wrangler.toml` with a
  `name`, `main = "worker.js"`, and the two routes.
- **Why a Worker:** the origin runs on a plan that cannot bind custom domains and
  Cloudflare's Host-header override rule is Enterprise-only; a Worker fetch sets the
  origin Host automatically and stays within the free tier.
