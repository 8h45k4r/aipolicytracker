<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/** Split-panel shell for guest and account pages in the site theme. */
class AuthShell extends Component
{
    public function __construct(public string $title, public ?string $eyebrow = null, public ?string $panelTitle = null, public ?string $panelText = null) {}

    public function render(): View
    {
        return view('auth.shell');
    }
}
