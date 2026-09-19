# Live server snapshot

What `deploy/server-snapshot.sh --write` recorded from the host, so that drift
between the repository and the running server shows up as a diff instead of
being rediscovered during an outage.

| File | What it is |
|---|---|
| `nginx-aip.conf` | The server block as it is on disk, which is not always what the repository says |
| `env-keys.txt` | Environment key names with `set` or `empty`, never values |
| `containers.txt` | Container names, images and state |
| `tls.txt` | Certificate subject, expiry and SHA-256 fingerprint, never the certificate |
| `cron.txt` | The managed cron block |

**Nothing here holds a secret.** Values are never written; the environment is
recorded as names and whether each is populated, and certificates appear as
fingerprints. Read the diff before pushing anyway.

These files are a record, not a source. Changing them changes nothing on the
server — edit the real file in `deploy/`, install it, then snapshot again.
