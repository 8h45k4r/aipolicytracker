# AIPolicyTracker agent server

A Model Context Protocol (MCP) server that lets an assistant look up AI policy records
directly instead of scraping pages. Read-only, unauthenticated, no dependencies: it speaks
JSON-RPC 2.0 over stdio and calls the same public URLs a browser would.

```bash
node agent/server.mjs
```

| Environment variable | Default | Purpose |
|----------------------|---------|---------|
| `AIPOLICYTRACKER_BASE_URL` | `https://aipolicytracker.org` | Point it at a local instance while developing. |
| `AIPOLICYTRACKER_TIMEOUT_MS` | `15000` | Per-request timeout. |

## Registering it

Most MCP clients take a command and its environment. For example:

```json
{
  "mcpServers": {
    "aipolicytracker": {
      "command": "node",
      "args": ["/path/to/aipolicytracker/agent/server.mjs"]
    }
  }
}
```

## Tools

| Tool | Returns |
|------|---------|
| `corpus_health` | How far the corpus can be trusted right now: records published, how many are past their re-check date, how many are missing required fields, how many were verified by a named reviewer. |
| `search_policies` | Instruments by keyword, jurisdiction or status. |
| `get_policy` | One instrument as a Markdown context file, provenance block included. |
| `get_jurisdiction` | One jurisdiction profile as a Markdown context file. |
| `list_obligations` | Duties recorded against instruments, with the provision each comes from. |
| `recent_changes` | The dated change log. |
| `open_gaps` | What the corpus is missing, so an assistant can say where the data is thin. |

`corpus_health` is listed first on purpose, and the server's `initialize` instructions tell the
client to call it before presenting a record as settled. Most records have not yet been
confirmed against their official source by a named reviewer, and an assistant that cannot see
that will present a structured summary as if it were the law. Every tool result also repeats
the caveat in its own content block, because a result is what gets quoted, not the handshake.

## Design notes

- **No SDK.** The protocol surface used here is `initialize`, `tools/list`, `tools/call` and
  `ping`. Adding a dependency to a Laravel repository for a few hundred lines of message
  handling means somebody has to patch it later.
- **Caller input never reaches a URL unchecked.** Slugs must match `[a-z0-9-]{1,160}`; result
  limits are clamped to 1–100 rather than passed through.
- **Failures are results, not exceptions.** An unknown tool or a 404 upstream comes back as
  `isError` with a readable message, so the model can correct itself instead of the
  conversation dying.

## Tests

```bash
npm test
```

`agent/server.test.mjs` runs the server as a child process against a local stub of the site,
so the protocol and the tool wiring are proven without the network. It covers the handshake,
the tool listing, a successful call, a rejected slug, limit clamping, upstream failure,
unknown methods and the rule that a notification is never answered.
