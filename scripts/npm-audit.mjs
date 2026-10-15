// npm audit over every dependency, build tools included, failing on any high or critical
// advisory except the ones listed here with a reason. Usage: node scripts/npm-audit.mjs
import { execFileSync } from 'node:child_process';

// Tailwind 3's glob chain (braces → micromatch, chokidar, fast-glob → tailwindcss) carries
// a stack-exhaustion advisory with no fixed version short of Tailwind 4. It runs only at
// build time on our own globs; crafted patterns cannot reach it. Remove when Tailwind
// moves to 4.
const ALLOWED = new Set(['GHSA-vfj7-8cjw-p6xm']);

let out;
try {
    out = execFileSync('npm', ['audit', '--json'], { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
} catch (e) {
    out = e.stdout; // npm audit exits non-zero whenever it finds anything
}
const report = JSON.parse(out);
const vulns = report.vulnerabilities ?? {};

// The advisories behind a package, following "via" names down to their roots.
const roots = (name, seen = new Set()) => {
    if (seen.has(name)) return [];
    seen.add(name);
    return (vulns[name]?.via ?? []).flatMap((v) => (typeof v === 'string' ? roots(v, seen) : [v]));
};

const blocking = Object.entries(vulns)
    .filter(([, v]) => ['high', 'critical'].includes(v.severity))
    .flatMap(([name]) => roots(name).filter((a) => ['high', 'critical'].includes(a.severity)).map((a) => ({ name, id: (a.url ?? '').split('/').pop(), title: a.title })))
    .filter((a) => !ALLOWED.has(a.id));

if (blocking.length) {
    for (const a of blocking) console.error(`${a.name}: ${a.id} ${a.title}`);
    console.error(`\n${blocking.length} high or critical advisory path(s) not on the allowlist.`);
    process.exit(1);
}
process.stdout.write(`npm audit: no high or critical advisory outside the allowlist (${[...ALLOWED].join(', ')}).` + "\n");
