<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Applicability\ApplicabilityScreener;
use App\Services\Applicability\ObligationsRegister;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** The applicability check's obligations register as a file. State lives in the URL; nothing is stored. */
class RegisterExportController extends Controller
{
    public function export(Request $request, string $format, ApplicabilityScreener $screener, ObligationsRegister $register): Response
    {
        abort_unless(in_array($format, ObligationsRegister::FORMATS, true), 404);
        $answers = $screener->normalise($request->query());
        abort_if($answers['jurisdictions'] === [], 404);
        $built = $register->build($answers);
        $body = match ($format) {
            'xlsx' => $register->xlsx($built),
            'csv' => $register->csv($built),
            'json' => json_encode($register->json($built), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            'pdf' => $register->pdf($built),
        };

        return response($body, 200, [
            'Content-Type' => ObligationsRegister::mime($format),
            'Content-Disposition' => 'attachment; filename="obligations-register.'.$format.'"',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
