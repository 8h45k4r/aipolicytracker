#!/usr/bin/env node
/**
 * AIPolicyTracker agent server (Model Context Protocol, stdio transport).
 *
 * Wraps the public read-only surfaces so an assistant can look up AI policy
 * records without scraping pages. No dependencies: the protocol is JSON-RPC 2.0
 * over newline-delimited stdio, and adding an SDK to a Laravel repository for a
 * few hundred lines of message handling is a dependency nobody wants to patch.
 *
 * Nothing here is authenticated and nothing writes. Every tool hits a public
 * URL, so the worst a misconfigured client can do is read what any browser can.
 *
 * The corpus_health tool is deliberately listed first and described as the thing
 * to call before quoting a record. These records are structured summaries with
 * links to official texts, most of them not yet confirmed by a named reviewer,
 * and a caller that cannot see that will present them as settled law.
 *
 * Usage:
 *   node agent/server.mjs
 *   AIPOLICYTRACKER_BASE_URL=https://aipolicytracker.org node agent/server.mjs
 */

const BASE = (process.env.AIPOLICYTRACKER_BASE_URL || 'https://aipolicytracker.org').replace(/\/+$/, '')
const TIMEOUT_MS = Number(process.env.AIPOLICYTRACKER_TIMEOUT_MS || 15000)
const PROTOCOL_VERSION = '2024-11-05'

const NOTICE =
  'AIPolicyTracker records are structured summaries with links to official texts. They are not legal advice. ' +
  'Check the review status and the date the facts were last confirmed before relying on any date or duty, and open the official source.'

async function get (path, { json = true } = {}) {
  const controller = new AbortController()
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS)
  try {
    const response = await fetch(BASE + path, {
      signal: controller.signal,
      headers: { accept: json ? 'application/json' : 'text/markdown', 'user-agent': 'aipolicytracker-agent/1.0' },
    })
    if (!response.ok) {
      throw new Error(`${response.status} ${response.statusText} for ${path}`)
    }
    return json ? await response.json() : await response.text()
  } finally {
    clearTimeout(timer)
  }
}

function query (params) {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') search.set(key, String(value))
  }
  const string = search.toString()
  return string ? `?${string}` : ''
}

/** Slugs come from the caller, so they are constrained before they reach a URL. */
function slug (value, field) {
  if (typeof value !== 'string' || !/^[a-z0-9-]{1,160}$/.test(value)) {
    throw new Error(`${field} must be a lowercase slug such as "eu-ai-act"`)
  }
  return value
}

function limit (value, fallback = 20) {
  const n = Number.isFinite(Number(value)) ? Math.floor(Number(value)) : fallback
  return Math.min(Math.max(n, 1), 100)
}

const TOOLS = [
  {
    name: 'corpus_health',
    description:
      'How far the corpus can be trusted right now: how many records are published, how many are past their re-check date, how many are missing required fields, and how many have been verified by a named reviewer. Call this before quoting any record as settled.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
    run: () => get('/open-data/health.json'),
  },
  {
    name: 'search_policies',
    description:
      'Search AI policy instruments (laws, regulations, standards, strategies and guidance) by keyword, jurisdiction or status. Returns records with their status, whether they are binding, and their official source.',
    inputSchema: {
      type: 'object',
      properties: {
        q: { type: 'string', description: 'Keyword, e.g. "high-risk" or "transparency".' },
        jurisdiction: { type: 'string', description: 'Jurisdiction slug, e.g. "european-union".' },
        status: { type: 'string', description: 'Instrument status, e.g. "in_force".' },
        limit: { type: 'integer', description: 'Maximum records to return (1-100, default 20).' },
      },
      additionalProperties: false,
    },
    run: (args) => get('/api/v1/policies' + query({ q: args.q, jurisdiction: args.jurisdiction, status: args.status, per_page: limit(args.limit) })),
  },
  {
    name: 'get_policy',
    description:
      'The full record for one policy instrument as a Markdown context file: summary, scope, who it applies to, dated milestones, the obligations recorded against it, and a provenance block with the official source, review status and the date the facts were last confirmed.',
    inputSchema: {
      type: 'object',
      properties: { slug: { type: 'string', description: 'Policy slug, e.g. "eu-ai-act".' } },
      required: ['slug'],
      additionalProperties: false,
    },
    run: (args) => get(`/policies/${slug(args.slug, 'slug')}.md`, { json: false }),
  },
  {
    name: 'get_jurisdiction',
    description:
      'The profile for one jurisdiction as a Markdown context file: where it stands on AI regulation, what is binding versus guidance, who regulates, and every instrument recorded for it.',
    inputSchema: {
      type: 'object',
      properties: { slug: { type: 'string', description: 'Jurisdiction slug, e.g. "european-union".' } },
      required: ['slug'],
      additionalProperties: false,
    },
    run: (args) => get(`/jurisdictions/${slug(args.slug, 'slug')}.md`, { json: false }),
  },
  {
    name: 'list_obligations',
    description:
      'Obligations recorded against instruments: what a duty requires, the provision it comes from, whether it is binding, and when it applies.',
    inputSchema: {
      type: 'object',
      properties: {
        policy: { type: 'string', description: 'Restrict to one instrument by slug.' },
        category: { type: 'string', description: 'Obligation category slug.' },
        limit: { type: 'integer', description: 'Maximum records to return (1-100, default 20).' },
      },
      additionalProperties: false,
    },
    run: (args) => get('/api/v1/obligations' + query({ policy: args.policy, category: args.category, per_page: limit(args.limit) })),
  },
  {
    name: 'recent_changes',
    description:
      'AI policy updates: dated entries recording what moved in AI policy, what it means in practice, and the official announcement behind it. Each carries a significance score (0-100, by a published rule) and the time it first appeared. The same records are arranged for people at /updates, with month, day and per-jurisdiction pages.',
    inputSchema: {
      type: 'object',
      properties: {
        jurisdiction: { type: 'string', description: 'Jurisdiction slug.' },
        since: { type: 'string', description: 'Only changes that occurred on or after this date (YYYY-MM-DD).' },
        limit: { type: 'integer', description: 'Maximum entries to return (1-100, default 20).' },
      },
      additionalProperties: false,
    },
    run: (args) => get('/api/v1/changes' + query({ jurisdiction: args.jurisdiction, since: args.since, per_page: limit(args.limit) })),
  },
  {
    name: 'search_incidents',
    description:
      'AI incidents mirrored from the AI Incident Database, with what this site adds: the harm domain (MIT AI Risk Repository taxonomy), the recorded laws that address that harm where it happened, and a one-sentence policy angle. Records about sexual imagery carry sensitivity "sensitive" and a neutral title; do not repeat their headlines. Attribution to the AI Incident Database (CC BY-SA 4.0) is a licence condition on reuse.',
    inputSchema: {
      type: 'object',
      properties: {
        domain: { type: 'string', description: 'MIT risk domain number, 1-7.' },
        country: { type: 'string', description: 'ISO-2 country code as the database records it, e.g. "US".' },
        from: { type: 'string', description: 'Only incidents on or after this date (YYYY-MM-DD).' },
        limit: { type: 'integer', description: 'Maximum records to return (1-100, default 20).' },
      },
      additionalProperties: false,
    },
    run: (args) => get('/api/v1/incidents' + query({ domain: args.domain, country: args.country, from: args.from, per_page: limit(args.limit) })),
  },
  {
    name: 'list_templates',
    description:
      'The templates library: free XLSX and DOCX files (AI system inventory, risk register, FRIA, policies, incident playbook, EU AI Act and ISO/IEC 42001 kits) generated from the recorded duties, controls and deadlines and rebuilt when the records change. Each entry gives the latest version, its dataset hash, what it covers and the download URLs. Filter by type, topic or framework.',
    inputSchema: {
      type: 'object',
      properties: {
        type: { type: 'string', description: 'register, assessment, policy, procedure, checklist, crosswalk or kit.' },
        topic: { type: 'string', description: 'inventory, risk, governance, transparency, incidents, vendors, oversight, workforce or evidence.' },
        framework: { type: 'string', description: 'eu-ai-act, iso-42001, nist-ai-rmf or colorado-ai-act.' },
      },
      additionalProperties: false,
    },
    run: (args) => get('/api/v1/templates' + query({ type: args.type, topic: args.topic, framework: args.framework })),
  },
  {
    name: 'open_gaps',
    description:
      'What the corpus is missing: published records that lack a source link, a summary, a provision reference or another field a checkable record needs. Use it to tell a user where the data is thin before they rely on it.',
    inputSchema: { type: 'object', properties: {}, additionalProperties: false },
    run: async () => {
      const health = await get('/open-data/health.json')
      return { completeness: health.completeness, queue_url: health.completeness.queue_url }
    },
  },
]

function text (value) {
  return typeof value === 'string' ? value : JSON.stringify(value, null, 2)
}

async function handle (request) {
  const { method, params } = request

  if (method === 'initialize') {
    return {
      protocolVersion: PROTOCOL_VERSION,
      capabilities: { tools: {} },
      serverInfo: { name: 'aipolicytracker', version: '1.0.0' },
      instructions: NOTICE + ' Call corpus_health before presenting any record as settled.',
    }
  }

  if (method === 'tools/list') {
    return { tools: TOOLS.map(({ name, description, inputSchema }) => ({ name, description, inputSchema })) }
  }

  if (method === 'tools/call') {
    const tool = TOOLS.find((t) => t.name === params?.name)
    if (!tool) {
      // A missing tool is the caller's error, reported inside the result so the
      // model can correct itself rather than the conversation failing.
      return { isError: true, content: [{ type: 'text', text: `Unknown tool "${params?.name}".` }] }
    }
    try {
      const result = await tool.run(params.arguments || {})
      return { content: [{ type: 'text', text: text(result) }, { type: 'text', text: NOTICE }] }
    } catch (error) {
      return { isError: true, content: [{ type: 'text', text: `${tool.name} failed: ${error.message}` }] }
    }
  }

  if (method === 'ping') return {}

  const error = new Error(`Method not found: ${method}`)
  error.code = -32601
  throw error
}

function send (message) {
  process.stdout.write(JSON.stringify(message) + '\n')
}

let buffer = ''
process.stdin.setEncoding('utf8')
process.stdin.on('data', (chunk) => {
  buffer += chunk
  let index
  while ((index = buffer.indexOf('\n')) !== -1) {
    const line = buffer.slice(0, index).trim()
    buffer = buffer.slice(index + 1)
    if (line === '') continue

    let request
    try {
      request = JSON.parse(line)
    } catch {
      send({ jsonrpc: '2.0', id: null, error: { code: -32700, message: 'Parse error' } })
      continue
    }

    // A notification carries no id and must not be answered.
    if (request.id === undefined || request.id === null) continue

    handle(request)
      .then((result) => send({ jsonrpc: '2.0', id: request.id, result }))
      .catch((error) => send({ jsonrpc: '2.0', id: request.id, error: { code: error.code || -32603, message: error.message } }))
  }
})

process.stdin.on('end', () => process.exit(0))
