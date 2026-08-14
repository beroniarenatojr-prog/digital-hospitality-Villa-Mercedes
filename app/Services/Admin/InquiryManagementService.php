<?php

namespace App\Services\Admin;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class InquiryManagementService
{
    /**
     * Retrieve inquiries, optionally filtered by status.
     *
     * @param array $filters
     * @return Collection<int, Inquiry>
     */
    public function getInquiries(array $filters = []): Collection
    {
        $query = Inquiry::with(['user', 'responder'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * Respond to an inquiry and optionally set it to Pending or Closed.
     */
    public function respondToInquiry(Inquiry $inquiry, User $admin, string $response, ?InquiryStatus $newStatus = null): Inquiry
    {
        $status = $newStatus ?? InquiryStatus::Pending;

        $inquiry->update([
            'admin_response' => $response,
            'responded_by' => $admin->id,
            'responded_at' => now(),
            'status' => $status->value,
        ]);

        activity()
            ->performedOn($inquiry)
            ->causedBy($admin)
            ->event('responded')
            ->log("Admin responded to inquiry and set status to {$status->label()}.");

        return $inquiry;
    }

    /**
     * Manually update the status of an inquiry.
     */
    public function updateInquiryStatus(Inquiry $inquiry, User $admin, InquiryStatus $status): Inquiry
    {
        $inquiry->update([
            'status' => $status->value,
        ]);

        activity()
            ->performedOn($inquiry)
            ->causedBy($admin)
            ->event('status_changed')
            ->log("Admin changed inquiry status to {$status->label()}.");

        return $inquiry;
    }
}
