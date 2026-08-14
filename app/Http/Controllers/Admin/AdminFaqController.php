<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFaqRequest;
use App\Models\Faq;
use App\Services\Admin\FaqManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminFaqController extends Controller
{
    public function __construct(
        private readonly FaqManagementService $service
    ) {}

    public function index(): View
    {
        $faqs = Faq::with('faqCategory')->orderBy('faq_category_id')->orderBy('sort_order')->get();

        return view('admin.faqs.index', compact('faqs'));
    }

    public function store(StoreFaqRequest $request): RedirectResponse
    {
        $this->service->createFaq($request->user(), $request->validated());

        return redirect()->back()->with('success', 'FAQ created successfully.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'faq_category_id' => 'required|integer|exists:faq_categories,id',
            'question' => 'required|string|max:1000',
            'answer' => 'required|string|max:5000',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $this->service->updateFaq($request->user(), $faq, $validated);

        return redirect()->back()->with('success', 'FAQ updated successfully.');
    }

    public function toggleStatus(Request $request, Faq $faq): RedirectResponse
    {
        $this->service->toggleFaqStatus($request->user(), $faq);

        return redirect()->back()->with('success', 'FAQ status toggled successfully.');
    }
}
