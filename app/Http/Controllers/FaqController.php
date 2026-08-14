<?php

namespace App\Http\Controllers;

use App\Services\Customer\CustomerFaqService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function __construct(
        private readonly CustomerFaqService $faqService
    ) {}

    public function index(): View
    {
        $categories = $this->faqService->getActiveCategories();
        $faqs = $this->faqService->getActiveFaqs();

        return view('pages.faq', compact('categories', 'faqs'));
    }
}
