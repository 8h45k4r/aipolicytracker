<?php

namespace App\Http\Controllers\Backend\Admin;

use App\Http\Controllers\Controller;
use App\Models\Funder;
use App\Support\FundingDisclosure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The funders disclosed on /funding, kept by the owner. Owner only (settings.manage),
 * because what the site says about its money is a statement by the project, not an
 * editorial decision. Every write is audited by the admin.audit middleware.
 */
class FundingController extends Controller
{
    public function index(Request $request): View
    {
        $funders = Funder::ordered()->get();
        // ?new=1 and ?edit=<id> are the addresses of the forms without JavaScript: the form
        // is then on the page. With JavaScript the same links open a side panel instead.
        $editing = $request->filled('edit') ? $funders->firstWhere('id', (int) $request->query('edit')) : null;

        return view('backend.admin.funding', [
            'funders' => $funders,
            'editing' => $editing,
            'adding' => ! $editing && $request->boolean('new'),
            'threshold' => FundingDisclosure::threshold(),
            'sponsorUrl' => FundingDisclosure::sponsorUrl(),
            'usingConfig' => $funders->isEmpty() && (array) config('funding.funders') !== [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] ??= (int) Funder::max('sort') + 1;
        $funder = Funder::create($data);

        return redirect()->route('backend.admin.funding.index')->with('success', $funder->name.' added'.($funder->published ? ' and published on /funding.' : ' as a draft. Publish it to show it on /funding.'));
    }

    public function update(Request $request, Funder $funder): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] ??= $funder->sort;
        $funder->update($data);

        return redirect()->route('backend.admin.funding.index')->with('success', $funder->name.' saved.');
    }

    public function publish(Request $request, Funder $funder): RedirectResponse
    {
        $state = $request->validate(['state' => ['required', 'in:on,off']])['state'];
        $funder->update(['published' => $state === 'on']);

        return back()->with('success', $funder->name.($funder->published ? ' is now shown on /funding.' : ' is hidden from /funding.'));
    }

    /** Swaps the funder with its neighbour, then renumbers so the order has no ties. */
    public function move(Request $request, Funder $funder): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', 'in:up,down']])['action'];
        $ids = Funder::ordered()->pluck('id')->all();
        $at = array_search($funder->id, $ids, true);
        $to = $action === 'up' ? $at - 1 : $at + 1;
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
        }
        DB::transaction(function () use ($ids) {
            foreach ($ids as $i => $id) {
                Funder::whereKey($id)->update(['sort' => $i + 1]);
            }
        });

        return back()->with('success', 'Order saved.');
    }

    public function destroy(Funder $funder): RedirectResponse
    {
        $name = $funder->name;
        $funder->delete();

        return redirect()->route('backend.admin.funding.index')->with('success', $name.' removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::in(array_keys(Funder::KINDS))],
            'amount_display' => ['required', 'string', 'max:64'],
            'period' => ['nullable', 'string', 'max:64'],
            'purpose' => ['required', 'string', 'max:2000'],
            'url' => ['nullable', 'url:https', 'max:512'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'published' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'url.url' => 'Use a full https:// address, for example https://example.org/grants.',
            'ends_on.after_or_equal' => 'The end date cannot be before the start date.',
        ]);
        $data['published'] = (bool) ($data['published'] ?? false);

        return $data;
    }
}
