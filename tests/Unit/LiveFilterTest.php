<?php

namespace Tests\Unit;

use App\Support\LiveFilter;
use Illuminate\Http\Request;
use Tests\TestCase;

class LiveFilterTest extends TestCase
{
    public function test_detects_the_live_filter_header(): void
    {
        $request = Request::create('/admin/payments', 'GET');
        $request->headers->set(LiveFilter::HEADER, '1');

        $this->assertTrue(LiveFilter::wantsPartial($request));
    }

    public function test_ignores_ordinary_page_requests(): void
    {
        $request = Request::create('/admin/payments', 'GET');

        $this->assertFalse(LiveFilter::wantsPartial($request));
    }
}
