<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PolicyInstrument;
use App\Models\ResourceDownload;
use App\Models\Tool;
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
    private function tool(string $slug): Tool
    {
        return Tool::published()->with('activeFiles')->where('slug', $slug)->firstOrFail();
    }

    public function show(string $slug): View
    {
        $tool = $this->tool($slug);
        $guides = collect(config('content.guides'))->only($tool->related_guides ?? [])->map(fn ($g, $s) => ['slug' => $s, 'h1' => $g['h1']])->values();
        $policies = PolicyInstrument::published()->with('jurisdiction')->whereIn('slug', $tool->related_policies ?? [])->get();
        $next = $tool->next_slug ? Tool::published()->where('slug', $tool->next_slug)->first() : null;
        $previousDownload = auth()->check() ? ResourceDownload::where('user_id', auth()->id())->where('resource_slug', $slug)->latest('id')->first() : null;
        $formats = $tool->formatList();
        $faq = [
            ['Is the '.$tool->title.' free?', 'Yes. Preview every field online; download the '.($formats ?: 'files').' with a free account under a CC BY 4.0 licence.'],
            ['Which frameworks does it map to?', implode(', ', array_map(fn ($f) => config('resources.frameworks')[$f] ?? $f, $tool->frameworks ?? [])).'. The mapping section links the recorded policy instruments and guides it draws on.'],
            ['Does completing it make us compliant?', 'No. It is an informational resource, not legal advice; it helps produce the evidence that regulators, customers and auditors ask for.'],
        ];

        $seo = Seo::make($tool->seo_title ?: 'Free '.$tool->title.($formats ? ' ('.$formats.')' : ''), mb_substr($tool->seo_description ?: $tool->short, 0, 155), $tool->url())
            ->withBreadcrumbs([['Home', route('home')], ['Guides', route('guides.index')], [$tool->title, $tool->url()]])
            ->withModified($tool->updated_on ?? $tool->updated_at)
            ->withJsonLd(['@type' => 'CreativeWork', 'name' => $tool->title, 'description' => $tool->short, 'url' => $tool->url(), 'version' => $tool->version, 'dateModified' => $tool->updated_on?->toDateString(), 'isAccessibleForFree' => true, 'license' => 'https://creativecommons.org/licenses/by/4.0/', 'author' => ['@id' => url('/').'#organization'], 'encodingFormat' => $tool->activeFiles->pluck('label')->all()])
            ->withJsonLd(['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)]);

        return view('site.guides.tool', compact('seo', 'tool', 'guides', 'policies', 'next', 'previousDownload', 'faq', 'formats'));
    }

    /** Gate for signed-out visitors: remember where to come back to, then offer sign-up or sign-in. */
    public function gate(string $slug): View|RedirectResponse
    {
        $tool = $this->tool($slug);
        if (auth()->check()) {
            return redirect()->to($tool->url().'#download');
        }
        session(['url.intended' => $tool->url().'#download']);
        $seo = Seo::make('Create a free account to download '.$tool->title, 'Free account, instant download, updates when related AI policy requirements change.', route('tools.gate', $slug), false)->noindex()
            ->withBreadcrumbs([['Home', route('home')], ['Guides', route('guides.index')], [$tool->title, $tool->url()], ['Download', route('tools.gate', $slug)]]);

        return view('site.guides.gate', compact('seo', 'tool'));
    }

    /** Records the download (terms acceptance, optional consent) and shows the format links. */
    public function download(Request $request, string $slug): RedirectResponse
    {
        $tool = $this->tool($slug);
        abort_if($tool->activeFiles->isEmpty(), 404);
        $data = $request->validate(['terms' => ['accepted'], 'updates' => ['nullable', 'boolean']], ['terms.accepted' => 'Please accept the template licence to download.']);
        $user = $request->user();
        $user->forceFill(['terms_accepted_at' => $user->terms_accepted_at ?? now()]);
        if (! empty($data['updates']) && ! $user->marketing_consent_at) {
            $user->marketing_consent_at = now();
        }
        $user->save();
        $download = ResourceDownload::create([
            'user_id' => $user->id, 'resource_slug' => $slug, 'file_name' => $tool->activeFiles->first()->file_name, 'version' => $tool->version,
            'terms_accepted_at' => now(), 'ip_hash' => hash('sha256', (string) $request->ip()), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'referrer' => mb_substr((string) $request->headers->get('referer'), 0, 255),
        ]);

        try {
            \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\DownloadLinksMail($download, $tool));
        } catch (\Throwable $e) {
            report($e); // the ready page still shows the links
        }

        return redirect()->route('tools.ready', [$slug, $download]);
    }

    public function ready(Request $request, string $slug, ResourceDownload $download): View
    {
        $tool = $this->tool($slug);
        abort_unless($download->user_id === $request->user()->id && $download->resource_slug === $slug, 404);
        $links = $tool->activeFiles->map(fn ($f) => ['label' => $f->label, 'name' => $f->file_name, 'url' => URL::temporarySignedRoute('tools.file', now()->addMinutes(30), ['slug' => $slug, 'download' => $download->id, 'file' => $f->file_name])]);
        $next = $tool->next_slug ? Tool::published()->where('slug', $tool->next_slug)->first() : null;
        $seo = Seo::make('Your download is ready: '.$tool->title, 'Choose a format.', $tool->url(), false)->noindex();

        return view('site.guides.ready', compact('seo', 'tool', 'links', 'next', 'download'));
    }

    /** Serves the file for the owner of the download record; the URL is signed and short-lived. */
    public function file(Request $request, string $slug, ResourceDownload $download, string $file): BinaryFileResponse
    {
        abort_unless($download->user_id === $request->user()->id && $download->resource_slug === $slug, 403);
        $tool = $this->tool($slug);
        $found = $tool->activeFiles->firstWhere('file_name', $file);
        $path = $found?->servablePath();
        abort_unless($path, 404);
        $download->forceFill(['file_name' => $file, 'tool_file_id' => $found->id, 'downloaded_at' => now()])->save();
        $found->increment('download_count');

        return response()->download($path, $file, ['Cache-Control' => 'private, no-store']);
    }
}
