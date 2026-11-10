<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\TemplateUpdateNotice;
use App\Models\TemplateUpdateOptout;
use App\Services\Templates\TemplateUpdateNotices;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The unsubscribe link in template update emails. The URL is signed and names the notice,
 * not the address. Opening it changes nothing (mail scanners follow every link); the
 * button, or a mail client's RFC 8058 one-click POST to the same URL, does.
 */
class TemplateUpdatesController extends Controller
{
    public function unsubscribe(Request $request, TemplateUpdateNotice $notice): View
    {
        $seo = Seo::make('Stop template update emails', 'Stop emails about new versions of templates.', route('templates.index'), false)->noindex();
        $optout = TemplateUpdateOptout::where('email', strtolower($notice->email))->first();

        return view('site.templates.updates-unsubscribe', [
            'seo' => $seo,
            'email' => $notice->email,
            'done' => $optout !== null && $optout->unsubscribed_at >= $notice->created_at,
            'action' => $request->fullUrl(),
        ]);
    }

    public function unsubscribePost(Request $request, TemplateUpdateNotice $notice, TemplateUpdateNotices $notices): RedirectResponse
    {
        $notices->unsubscribe($notice->email, 'email');

        return redirect()->to($request->fullUrl());
    }
}
