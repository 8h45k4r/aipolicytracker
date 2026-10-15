<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resolves a permanent record id to the record's current address. A browser is sent
 * there (302, because the target may move again; the id is the stable part); a client
 * asking for JSON gets the id, kind and addresses without a redirect.
 */
class RecordIdController extends Controller
{
    private const KINDS = [
        'POL' => [PolicyInstrument::class, 'policy'],
        'OBL' => [Obligation::class, 'obligation'],
        'CHG' => [ChangeEvent::class, 'change'],
        'CTL' => [Control::class, 'control'],
    ];

    public function __invoke(Request $request, string $aiptId): RedirectResponse|JsonResponse
    {
        // Ids are written upper case; one typed or pasted in lower case still resolves.
        $aiptId = strtoupper($aiptId);
        abort_unless(preg_match('/^AIPT-(POL|OBL|CHG|CTL)-\d{4,}$/', $aiptId) === 1, 404);
        [$model, $kind] = self::KINDS[explode('-', $aiptId)[1]];
        $record = $model::query()->where('aipt_id', $aiptId)->whereNotNull('published_at')->where('published_at', '<=', now())->first();
        abort_unless($record, 404);

        // Private and short-lived: the target can move again (a rename, a takedown), the same
        // address answers JSON or a redirect depending on Accept, and the response carries
        // the session cookie, so no shared cache may keep it.
        $headers = ['Cache-Control' => 'private, max-age=300', 'Vary' => 'Accept'];
        if ($request->wantsJson()) {
            return response()->json(['id' => $aiptId, 'kind' => $kind, 'slug' => $record->slug, 'url' => $record->url(), 'permanent_url' => route('record.id', $aiptId)], 200, $headers);
        }

        return redirect()->to($record->url(), 302)->withHeaders($headers);
    }
}
