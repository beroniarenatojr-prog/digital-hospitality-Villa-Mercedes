<?php

namespace App\Services\Customer;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CustomerInquiryService
{
    /**
     * Submit a new inquiry.
     */
    public function submitInquiry(User $customer, string $subject, string $message): Inquiry
    {
        $inquiry = Inquiry::create([
            'user_id' => $customer->id,
            'subject' => $subject,
            'message' => $message,
            'status' => InquiryStatus::Open->value,
        ]);

        activity()
            ->performedOn($inquiry)
            ->causedBy($customer)
            ->event('created')
            ->log("Customer submitted a new inquiry.");

        return $inquiry;
    }

    /**
     * Retrieve all inquiries belonging to a customer.
     *
     * @return Collection<int, Inquiry>
     */
    public function getCustomerInquiries(User $customer): Collection
    {
        return Inquiry::where('user_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Retrieve a specific inquiry, verifying ownership.
     */
    public function getInquiry(User $customer, Inquiry $inquiry): Inquiry
    {
        $this->verifyOwnership($customer, $inquiry);

        return $inquiry;
    }

    /**
     * Verify the customer owns the inquiry.
     *
     * @throws AccessDeniedHttpException
     */
    private function verifyOwnership(User $customer, Inquiry $inquiry): void
    {
        if ($inquiry->user_id !== $customer->id) {
            throw new AccessDeniedHttpException('You do not have permission to access this inquiry.');
        }
    }
}
