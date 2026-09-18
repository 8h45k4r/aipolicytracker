{{--
    Self-contained error page for the failures where the application itself may be
    the thing that is broken.

    It extends no layout, queries nothing, reads no cached config beyond the app
    name, loads no compiled CSS and calls no named route. That is the whole point:
    the site layout renders a footer and a navigation built from the app's own
    routes and settings, so when the failure is a 500 the layout can throw while
    rendering the page meant to explain the 500 — and the visitor gets a bare
    "Server Error" from the framework instead of anything useful. That is exactly
    what this project shipped.

    Styles are inline for the same reason: a missing or unbuilt asset bundle must
    not be able to turn this page into unstyled text.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $title }} · AIPolicyTracker</title>
<style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
        padding: 2rem 1rem; background: #F5F7FA; color: #1E2A3B;
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        line-height: 1.6;
    }
    .card { width: 100%; max-width: 34rem; background: #fff; border: 1px solid #D8DEE8; border-radius: 4px; padding: 2rem; }
    .code { font-size: .75rem; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #5D6B7E; margin: 0; }
    h1 { margin: .5rem 0 0; font-size: 1.5rem; line-height: 1.3; color: #002147; font-weight: 600; letter-spacing: -.01em; }
    p { margin: .75rem 0 0; }
    .muted { color: #5D6B7E; font-size: .875rem; }
    .actions { margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: .5rem; }
    a.btn {
        display: inline-block; padding: .5rem .9rem; border-radius: 3px; text-decoration: none;
        font-size: .875rem; font-weight: 500; border: 1px solid #D8DEE8; color: #002147; background: #fff;
    }
    a.btn.primary { background: #002147; border-color: #002147; color: #fff; }
    a.btn:hover { border-color: #006AAC; }
    a.btn:focus-visible { outline: 2px solid #009CE0; outline-offset: 2px; }
    .ref { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #D8DEE8; }
    code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; background: #F5F7FA; padding: .1rem .3rem; border-radius: 2px; }
    @media (prefers-color-scheme: dark) {
        :root { color-scheme: dark; }
        body { background: #00142B; color: #E4E9F0; }
        .card { background: #002147; border-color: #123A63; }
        h1 { color: #fff; }
        .muted, .code { color: #9FB0C4; }
        a.btn { background: transparent; color: #E4E9F0; border-color: #123A63; }
        a.btn.primary { background: #009CE0; border-color: #009CE0; color: #00142B; }
        code { background: #00142B; }
        .ref { border-top-color: #123A63; }
    }
</style>
</head>
<body>
    <main class="card">
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $body }}</p>
        @isset($detail)<p class="muted">{{ $detail }}</p>@endisset

        <div class="actions">
            <a class="btn primary" href="/">Home</a>
            <a class="btn" href="/policies">Policies</a>
            <a class="btn" href="/changes">Change log</a>
            <a class="btn" href="/open-data/health.json">Data status</a>
        </div>

        <div class="ref">
            <p class="muted">
                If this keeps happening, report it at <code>{{ config('aipolicytracker.github_url', 'the project repository') }}</code>@isset($reference) and quote reference <code>{{ $reference }}</code>@endisset.
            </p>
        </div>
    </main>
</body>
</html>
