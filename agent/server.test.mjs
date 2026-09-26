/**
 * Exercises the agent server against a local stub of the site, so the test
 * proves the protocol and the tool wiring without depending on the network or
 * on aipolicytracker.org being up.
 */
import { test, before, after } from 'node:test'
import assert from 'node:assert/strict'
import { spawn } from 'node:child_process'
import { createServer } from 'node:http'
import { fileURLToPath } from 'node:url'

const SERVER = fileURLToPath(new URL('./server.mjs', import.meta.url))

const HEALTH = {
  records_published: 594,
  completeness: { required_gaps: 0, expected_gaps: 423, queue_url: 'http://stub/gaps' },
  freshness: { never_verified: 594 },
}

let stub
let baseUrl
const requested = []

before(async () => {
  stub = createServer((req, res) => {
    requested.push(req.url)
    if (req.url === '/open-data/health.json') {
      res.writeHead(200, { 'content-type': 'application/json' })
      return res.end(JSON.stringify(HEALTH))
    }
    if (req.url === '/policies/eu-ai-act.md') {
      res.writeHead(200, { 'content-type': 'text/markdown' })
      return res.end('# Regulation (EU) 2024/1689\n\n## Provenance\n\n- **Review status**: pending review\n')
    }
    if (req.url.startsWith('/api/v1/policies')) {
      res.writeHead(200, { 'content-type': 'application/json' })
      return res.end(JSON.stringify({ data: [{ slug: 'eu-ai-act' }] }))
    }
    res.writeHead(404).end('not found')
  })
  await new Promise((resolve) => stub.listen(0, '127.0.0.1', resolve))
  baseUrl = `http://127.0.0.1:${stub.address().port}`
})

after(() => stub.close())

/** Sends each request in order and returns the responses, keyed by id. */
function converse (requests) {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [SERVER], {
      env: { ...process.env, AIPOLICYTRACKER_BASE_URL: baseUrl },
      stdio: ['pipe', 'pipe', 'pipe'],
    })
    const responses = []
    let out = ''
    child.stdout.setEncoding('utf8')
    child.stdout.on('data', (chunk) => {
      out += chunk
      let i
      while ((i = out.indexOf('\n')) !== -1) {
        const line = out.slice(0, i).trim()
        out = out.slice(i + 1)
        if (line) responses.push(JSON.parse(line))
        if (responses.length === requests.filter((r) => r.id !== undefined).length) {
          child.stdin.end()
        }
      }
    })
    child.on('error', reject)
    child.on('close', () => resolve(responses))
    for (const request of requests) child.stdin.write(JSON.stringify(request) + '\n')
  })
}

test('it announces the protocol version and warns the caller in its instructions', async () => {
  const [response] = await converse([{ jsonrpc: '2.0', id: 1, method: 'initialize', params: {} }])
  assert.equal(response.result.protocolVersion, '2024-11-05')
  assert.equal(response.result.serverInfo.name, 'aipolicytracker')
  assert.match(response.result.instructions, /not legal advice/)
  assert.match(response.result.instructions, /corpus_health/)
})

test('it lists every tool with a schema, health first', async () => {
  const [response] = await converse([{ jsonrpc: '2.0', id: 1, method: 'tools/list' }])
  const tools = response.result.tools
  assert.equal(tools[0].name, 'corpus_health', 'the trust check is offered before the lookups')
  for (const tool of tools) {
    assert.ok(tool.description.length > 40, `${tool.name} needs a description a model can choose on`)
    assert.equal(tool.inputSchema.type, 'object')
    assert.equal(tool.inputSchema.additionalProperties, false)
  }
  assert.deepEqual(
    tools.map((t) => t.name).sort(),
    ['corpus_health', 'get_applicable_deadlines', 'get_jurisdiction', 'get_policy', 'list_obligations', 'list_templates', 'open_gaps', 'recent_changes', 'search_incidents', 'search_policies']
  )
})

test('a tool call returns the record and repeats the caveat in the same result', async () => {
  const [response] = await converse([
    { jsonrpc: '2.0', id: 1, method: 'tools/call', params: { name: 'get_policy', arguments: { slug: 'eu-ai-act' } } },
  ])
  assert.ok(!response.result.isError)
  assert.match(response.result.content[0].text, /Regulation \(EU\) 2024\/1689/)
  assert.match(response.result.content[1].text, /not legal advice/)
})

test('a bad slug is refused before it reaches a URL', async () => {
  const before = requested.length
  const [response] = await converse([
    { jsonrpc: '2.0', id: 1, method: 'tools/call', params: { name: 'get_policy', arguments: { slug: '../../etc/passwd' } } },
  ])
  assert.equal(response.result.isError, true)
  assert.match(response.result.content[0].text, /lowercase slug/)
  assert.equal(requested.length, before, 'no request was made')
})

test('the result limit is clamped rather than passed through', async () => {
  await converse([
    { jsonrpc: '2.0', id: 1, method: 'tools/call', params: { name: 'search_policies', arguments: { limit: 100000 } } },
  ])
  assert.ok(requested.some((url) => url.includes('per_page=100')), `expected a clamped per_page, saw ${requested.join(' ')}`)
})

test('an unknown tool and an upstream failure are reported to the model, not thrown', async () => {
  const responses = await converse([
    { jsonrpc: '2.0', id: 1, method: 'tools/call', params: { name: 'nope' } },
    { jsonrpc: '2.0', id: 2, method: 'tools/call', params: { name: 'get_jurisdiction', arguments: { slug: 'nowhere' } } },
  ])
  const byId = Object.fromEntries(responses.map((r) => [r.id, r]))
  assert.equal(byId[1].result.isError, true)
  assert.match(byId[1].result.content[0].text, /Unknown tool/)
  assert.equal(byId[2].result.isError, true)
  assert.match(byId[2].result.content[0].text, /404/)
})

test('an unknown method is a JSON-RPC error and a notification is never answered', async () => {
  const responses = await converse([
    { jsonrpc: '2.0', method: 'notifications/initialized' },
    { jsonrpc: '2.0', id: 7, method: 'resources/list' },
  ])
  assert.equal(responses.length, 1, 'the notification produced no reply')
  assert.equal(responses[0].id, 7)
  assert.equal(responses[0].error.code, -32601)
})
