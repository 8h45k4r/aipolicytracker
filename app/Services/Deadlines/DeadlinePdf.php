<?php

namespace App\Services\Deadlines;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

/** The personal timeline as a one- or two-page PDF, rendered from a plain template with no remote assets. */
final class DeadlinePdf
{
    public function render(Collection $rows, array $answers, array $labels, string $summary): string
    {
        $html = view('site.deadlines.pdf', compact('rows', 'answers', 'labels', 'summary'))->render();
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }
}
