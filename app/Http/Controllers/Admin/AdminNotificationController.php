<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LayoutBadges;
use App\Support\NotificationLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);
        $hasUnreadNotifications = $request->user()->unreadNotifications()->exists();

        return view('admin.notifications.index', compact('notifications', 'hasUnreadNotifications'));
    }

    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        LayoutBadges::forgetUnread($request->user()->id);

        return redirect(NotificationLink::forAdmin($notification->data));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);
        LayoutBadges::forgetUnread($request->user()->id);

        return back()->with('success', 'All notifications marked as read.');
    }
}
