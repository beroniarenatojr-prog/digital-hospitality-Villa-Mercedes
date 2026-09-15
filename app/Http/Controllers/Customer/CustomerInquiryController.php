<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreInquiryRequest;
use App\Models\Inquiry;
use App\Services\Customer\CustomerInquiryService;
use Illuminate\Http\JsonResponse;
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

    public function store(StoreInquiryRequest $request): RedirectResponse|JsonResponse
    {
        $inquiry = $this->service->submitInquiry(
            $request->user(),
            $request->validated('subject'),
            $request->validated('message')
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your message was sent to the resort team.',
                'inquiry_url' => route('customer.inquiries.show', $inquiry),
                'inquiry_id' => $inquiry->id,
            ], 201);
        }

        return redirect()->route('customer.inquiries.show', $inquiry)
            ->with('success', 'Inquiry submitted successfully.');
    }
}
