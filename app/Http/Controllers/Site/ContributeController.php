<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ContributorSubmission;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContributeController extends Controller
{
    public function show(Request $request): View
    {
        $seo = Seo::make(
            'Contribute: report errors, propose sources, submit policy records',
            'How to report an error, propose an official source, submit a new AI policy record or volunteer as a reviewer. Submissions default to pending review before publication.',
            route('contribute')
        )->withBreadcrumbs([['Home', route('home')], ['Contribute', route('contribute')]]);

        return view('site.pages.contribute', [
            'seo' => $seo,
            'types' => ContributorSubmission::TYPES,
            'prefill' => [
                'type' => array_key_exists($request->query('type', ''), ContributorSubmission::TYPES) ? $request->query('type') : 'correction',
                'subject_type' => $request->query('subject_type'),
                'subject_slug' => $request->query('subject_slug'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: bots fill the hidden "website" field.
        if ($request->filled('website')) {
            return redirect()->route('contribute')->with('success', 'Thank you. Your submission is pending review.');
        }

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(ContributorSubmission::TYPES))],
            'subject_type' => ['nullable', 'in:policy,jurisdiction,obligation,change,other'],
            'subject_slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9-]*$/'],
            'summary' => ['required', 'string', 'min:10', 'max:300'],
            'details' => ['nullable', 'string', 'max:5000'],
            'proposed_source_url' => ['nullable', 'url', 'max:2048'],
            'submitter_name' => ['nullable', 'string', 'max:120'],
            'submitter_email' => ['nullable', 'email', 'max:190'],
            'submitter_affiliation' => ['nullable', 'string', 'max:190'],
            'source_page' => ['nullable', 'url', 'max:2048'],
        ]);

        $submission = ContributorSubmission::create($data + ['status' => 'pending_review']);
        foreach (config('aipolicytracker.admin_emails', []) as $admin) {
            try {
                \Illuminate\Support\Facades\Mail::to($admin)->send(new \App\Mail\SubmissionReceivedMail($submission));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('contribute')->with('success', 'Thank you. Your submission has been recorded with status "pending review". A reviewer will check it against official sources before anything is published.');
    }
}
