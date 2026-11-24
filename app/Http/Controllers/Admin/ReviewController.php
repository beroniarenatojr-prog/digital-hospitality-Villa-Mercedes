<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuestReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function index() { return view('admin.reviews.index', ['reviews' => GuestReview::with('user', 'reservation.facility')->orderByRaw('moderated_at IS NOT NULL')->latest()->paginate(20)]); }

    public function update(Request $request, GuestReview $review)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['publish', 'unpublish'])], 'reason' => ['nullable', 'string', 'max:500']]);
        DB::transaction(function () use ($request, $review, $data) {
            $locked = GuestReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            if ($data['action'] === 'publish' && $locked->public_consent === false) {
                throw ValidationException::withMessages(['action' => 'This customer chose private feedback.']);
            }
            if (($data['action'] === 'unpublish' || ($locked->is_published && $data['action'] !== 'publish')) && blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(["reason.{$locked->id}" => 'Record the privacy, spam, or abuse reason. A low rating is not a removal reason.']);
            }
            $published = $data['action'] === 'publish';
            $locked->update(['is_published' => $published, 'published_at' => $published ? now() : $locked->published_at,
                'moderated_at' => now(), 'moderated_by' => $request->user()->id, 'moderation_reason' => $data['reason'] ?? null]);
            activity()->performedOn($locked)->causedBy($request->user())->event('guest_review_moderated')->withProperties($data)->log('Review visibility updated; original rating and comment retained.');
        }, 5);
        return back()->with('success', 'Review updated.');
    }
}
