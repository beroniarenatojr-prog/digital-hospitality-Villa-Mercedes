<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * The page's own name, when it has one. The auth screens leave this null and
     * are titled from the route they are on; a screen that is not a route — the
     * expired-session page — has to say what it is itself.
     */
    public function __construct(public ?string $title = null, public bool $wide = false) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
