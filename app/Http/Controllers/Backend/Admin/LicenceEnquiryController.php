<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenceEnquiry;
use App\Support\Admin\CsvStream;
use App\Support\Admin\ListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Licence, team and coverage enquiries from the public form, newest first, filterable by
 * tier and exportable. Read-only: the conversation itself happens by email.
 */
class LicenceEnquiryController extends Controller
{
    private const SORTS = ['received' => 'created_at', 'organisation' => 'organization', 'tier' => 'tier', 'email' => 'email'];

    public function index(Request $request): View
    {
        $filters = ListFilters::from($request, self::SORTS);
        $enquiries = $this->query($request, $filters)->paginate(25)->withQueryString();
        $byTier = LicenceEnquiry::selectRaw('tier, COUNT(*) as n')->groupBy('tier')->pluck('n', 'tier');
        $summary = [
            'total' => LicenceEnquiry::count(),
            'month' => LicenceEnquiry::where('created_at', '>=', now()->subDays(29)->startOfDay())->count(),
            'unsent' => LicenceEnquiry::whereNull('notified_at')->count(),
        ];

        return view('backend.admin.enquiries', compact('enquiries', 'filters', 'byTier', 'summary'));
    }

    public function export(Request $request): StreamedResponse
    {
        return CsvStream::from($this->query($request, ListFilters::from($request, self::SORTS)), 'licence-enquiries',
            ['received_at', 'tier', 'name', 'email', 'organisation', 'use_case', 'coverage_request', 'source', 'notified_at'],
            fn (LicenceEnquiry $e) => [$e->created_at, $e->tierLabel(), $e->name, $e->email, $e->organization, $e->use_case, $e->coverage_request, $e->source, $e->notified_at]);
    }

    private function query(Request $request, ListFilters $filters): Builder
    {
        $query = LicenceEnquiry::query();
        $filters->search($query, ['name', 'email', 'organization', 'use_case', 'coverage_request']);
        $filters->dateRange($query, 'created_at');
        $query->when(array_key_exists((string) $request->query('tier'), LicenceEnquiry::TIERS), fn ($q) => $q->where('tier', (string) $request->query('tier')))
            ->when($request->query('coverage') === '1', fn ($q) => $q->whereNotNull('coverage_request'));

        return $filters->order($query);
    }
}
