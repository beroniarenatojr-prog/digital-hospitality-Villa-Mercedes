<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RespondInquiryRequest;
use App\Models\Inquiry;
use App\Services\Admin\InquiryManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminInquiryController extends Controller
{
    public function __construct(
        private readonly InquiryManagementService $service
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['status', 'date_from', 'date_until', 'search']);
        $inquiries = $this->service->getInquiries($filters);

        return view('admin.inquiries.index', compact('inquiries'));
    }

    public function show(Inquiry $inquiry): View
    {
        $inquiry->load(['user', 'responder']);

        return view('admin.inquiries.show', compact('inquiry'));
    }

    public function respond(RespondInquiryRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $statusEnum = $request->filled('status') 
            ? \App\Enums\InquiryStatus::from($request->validated('status')) 
            : null;

        $this->service->respondToInquiry(
            $inquiry,
            $request->user(),
            $request->validated('response'),
            $statusEnum
        );

        return redirect()->back()->with('success', 'Responded to inquiry successfully.');
    }

    public function updateStatus(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', \Illuminate\Validation\Rule::in(\App\Enums\InquiryStatus::values())],
        ]);

        $statusEnum = \App\Enums\InquiryStatus::from($validated['status']);
        
        $this->service->updateInquiryStatus($inquiry, $request->user(), $statusEnum);

        return redirect()->back()->with('success', 'Inquiry status updated successfully.');
    }
}
