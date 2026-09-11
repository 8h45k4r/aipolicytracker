<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\FreeTool;
use App\Models\PolicyInstrument;
use App\Models\ResourceDownload;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Free tools on /guides: public preview pages, a sign-in gate on Download, a terms-accepted
 * download record, and file delivery through short-lived signed URLs for the signed-in user.
 */
class FreeToolController extends Controller
{
    public function show(string $slug): View
    {
        $tool = FreeTool::find($slug);
        abort_unless($tool, 404);
        $guides = collect(config('content.guides'))->only($tool['related_guides'] ?? [])->map(fn ($g, $s) => ['slug' => $s, 'h1' => $g['h1']])->values();
        $policies = PolicyInstrument::published()->with('jurisdiction')->whereIn('slug', $tool['related_policies'] ?? [])->get();
        $next = ! empty($tool['next']) ? FreeTool::find($tool['next']) : null;
        $previousDownload = auth()->check() ? ResourceDownload::where('user_id', auth()->id())->where('resource_slug', $slug)->latest('id')->first() : null;

        $seo = Seo::make('Free '.$tool['title'].' ('.implode(', ', array_map(fn ($f) => $f[1], $tool['files'])).')', mb_substr($tool['short'], 0, 155), route('tools.show', $slug))
            ->withBreadcrumbs([['Home', route('home')], ['Guides', route('guides.index')], [$tool['title'], route('tools.show', $slug)]])
            ->withModified(\Carbon\Carbon::parse($tool['updated']))
            ->withJsonLd(['@type' => 'CreativeWork', 'name' => $tool['title'], 'description' => $tool['short'], 'url' => route('tools.show', $slug), 'version' => $tool['version'], 'dateModified' => $tool['updated'], 'isAccessibleForFree' => true, 'license' => 'https://creativecommons.org/licenses/by/4.0/', 'author' => ['@id' => url('/').'#organization'], 'encodingFormat' => array_map(fn ($f) => $f[1], $tool['files'])]);

        $faq = [
            ['Is the '.$tool['title'].' free?', 'Yes. Preview every field online; download the '.implode(', ', array_map(fn ($f) => $f[1], $tool['files'])).' files with a free account under a CC BY 4.0 licence.'],
            ['Which frameworks does it map to?', implode(', ', array_map(fn ($f) => config('resources.frameworks')[$f] ?? $f, $tool['frameworks'])).'. The mapping section links the recorded policy instruments and guides it draws on.'],
            ['Does completing it make us compliant?', 'No. It is an informational resource, not legal advice; it helps produce the evidence that regulators, customers and auditors ask for.'],
        ];
        $seo->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)]);

        return view('site.guides.tool', compact('seo', 'tool', 'guides', 'policies', 'next', 'previousDownload', 'faq'));
    }

    /** Gate for signed-out visitors: remember where to come back to, then offer sign-up or sign-in. */
    public function gate(string $slug): View|RedirectResponse
    {
        $tool = FreeTool::find($slug);
        abort_unless($tool, 404);
        if (auth()->check()) {
            return redirect()->route('tools.show', $slug)->withFragment('download');
        }
        session(['url.intended' => route('tools.show', $slug).'#download']);
        $seo = Seo::make('Create a free account to download '.$tool['title'], 'Free account, instant download, updates when related AI policy requirements change.', route('tools.gate', $slug), false)->noindex()
            ->withBreadcrumbs([['Home', route('home')], ['Guides', route('guides.index')], [$tool['title'], route('tools.show', $slug)], ['Download', route('tools.gate', $slug)]]);

        return view('site.guides.gate', compact('seo', 'tool'));
    }

    /** Records the download (terms acceptance, optional consent) and shows the format links. */
    public function download(Request $request, string $slug): RedirectResponse
    {
        $tool = FreeTool::find($slug);
        abort_unless($tool, 404);
        $data = $request->validate(['terms' => ['accepted'], 'updates' => ['nullable', 'boolean']], ['terms.accepted' => 'Please accept the template licence to download.']);
        $user = $request->user();
        $user->forceFill(['terms_accepted_at' => $user->terms_accepted_at ?? now()]);
        if (! empty($data['updates']) && ! $user->marketing_consent_at) {
            $user->marketing_consent_at = now();
        }
        $user->save();
        $download = ResourceDownload::create([
            'user_id' => $user->id, 'resource_slug' => $slug, 'file_name' => $tool['files'][0][0], 'version' => $tool['version'],
            'terms_accepted_at' => now(), 'ip_hash' => hash('sha256', (string) $request->ip()), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 255),
        ]);

        return redirect()->route('tools.ready', [$slug, $download]);
    }

    public function ready(Request $request, string $slug, ResourceDownload $download): View
    {
        $tool = FreeTool::find($slug);
        abort_unless($tool && $download->user_id === $request->user()->id && $download->resource_slug === $slug, 404);
        $links = collect($tool['files'])->map(fn ($f) => ['label' => $f[1], 'name' => $f[0], 'url' => URL::temporarySignedRoute('tools.file', now()->addMinutes(30), ['slug' => $slug, 'download' => $download->id, 'file' => $f[0]])]);
        $next = ! empty($tool['next']) ? FreeTool::find($tool['next']) : null;
        $seo = Seo::make('Your download is ready: '.$tool['title'], 'Choose a format.', route('tools.show', $slug), false)->noindex();

        return view('site.guides.ready', compact('seo', 'tool', 'links', 'next', 'download'));
    }

    /** Serves the file for the owner of the download record; the URL is signed and short-lived. */
    public function file(Request $request, string $slug, ResourceDownload $download, string $file): BinaryFileResponse
    {
        abort_unless($download->user_id === $request->user()->id && $download->resource_slug === $slug, 403);
        $found = FreeTool::file($slug, $file);
        abort_unless($found, 404);
        $download->forceFill(['file_name' => $file, 'downloaded_at' => now()])->save();

        return response()->download($found['path'], $file, ['Cache-Control' => 'private, no-store']);
    }
}
