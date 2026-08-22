<?php

namespace App\Http\Controllers;

use App\Services\Customer\CustomerFaqService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\FaqCategory;

class FaqController extends Controller
{
    public function __construct(
        private readonly CustomerFaqService $faqService
    ) {}

    public function index(Request $request): View
    {
        $categories = $this->faqService->getActiveCategories();
        
        $selectedCategory = null;
        if ($request->filled('category_id')) {
            $selectedCategory = FaqCategory::find($request->input('category_id'));
        }

        $faqs = $this->faqService->getActiveFaqs($selectedCategory);

        return view('pages.faq', compact('categories', 'faqs', 'selectedCategory'));
    }
}
