<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Review of collected consultations. A reviewer opens the official page and confirms the
 * entry is about AI governance and its date is right, or hides it. Only a person can move
 * an entry to confirmed; the collector never does.
 */
class ConsultationController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), Consultation::STATUSES, true) ? $request->query('status') : 'collected';
        $consultations = Consultation::with('reviewer')->where('status', $status)
            ->when($request->query('closed') !== '1', fn ($q) => $q->open())
            ->orderBy('comments_close_on')->paginate(50)->withQueryString();
        $counts = Consultation::open()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('backend.admin.consultations', compact('consultations', 'counts', 'status'));
    }

    public function review(Request $request, Consultation $consultation): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['confirmed', 'hidden', 'collected'])]]);
        $consultation->update(['status' => $data['status'], 'reviewed_by' => $data['status'] === 'collected' ? null : $request->user()->id, 'reviewed_at' => $data['status'] === 'collected' ? null : now()]);

        return back()->with('success', match ($data['status']) {
            'confirmed' => 'Confirmed: it shows on the calendar as reviewed.',
            'hidden' => 'Hidden from the calendar.',
            default => 'Back to review.',
        });
    }
}
