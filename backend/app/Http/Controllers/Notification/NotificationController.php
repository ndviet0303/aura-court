<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = $user->notifications()->latest()->paginate(30);
        $unread = $notifications->total() === 0
            ? 0
            : $user->unreadNotifications()->count();

        return [
            'unread' => $unread,
            'notifications' => $notifications,
        ];
    }

    public function read(Request $request, string $notification)
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return ['message' => 'Đã đánh dấu đã đọc.'];
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ['message' => 'Đã đánh dấu tất cả là đã đọc.'];
    }
}
