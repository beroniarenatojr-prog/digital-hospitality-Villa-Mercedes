<?php

namespace App\Services\Admin;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class InquiryManagementService
{
    /**
     * Retrieve inquiries, optionally filtered by status and search text.
     *
     * @param array $filters
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getInquiries(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Inquiry::with(['user', 'responder'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'open' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_until'])) {
            $query->whereDate('created_at', '<=', $filters['date_until']);
        }

        if (isset($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', $search)
                  ->orWhere('message', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search)
                         ->orWhere('email', 'like', $search);
                  });
            });
        }

        return $query->paginate(20);
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
