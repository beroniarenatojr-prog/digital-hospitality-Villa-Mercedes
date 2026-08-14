<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreInquiryRequest;
use App\Models\Inquiry;
use App\Services\Customer\CustomerInquiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerInquiryController extends Controller
{
    public function __construct(
        private readonly CustomerInquiryService $service
    ) {}

    public function index(Request $request): View
    {
        $inquiries = $this->service->getCustomerInquiries($request->user());

        return view('customer.inquiries.index', compact('inquiries'));
    }

    public function show(Request $request, Inquiry $inquiry): View
    {
        $inquiry = $this->service->getInquiry($request->user(), $inquiry);

        return view('customer.inquiries.show', compact('inquiry'));
    }

    public function store(StoreInquiryRequest $request): RedirectResponse
    {
        $inquiry = $this->service->submitInquiry(
            $request->user(),
            $request->validated('subject'),
            $request->validated('message')
        );

        return redirect()->route('customer.inquiries.show', $inquiry)
            ->with('success', 'Inquiry submitted successfully.');
    }
}
