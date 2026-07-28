<?php

namespace Modules\System\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\System\Http\Resources\NotificationResource;
use Modules\System\Models\Notification;

class NotificationsController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $notifications = $this->visibleTo($request)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return NotificationResource::collection($notifications)->additional([
            'success' => true,
            'unread_count' => $this->visibleTo($request)->where('status', 'unread')->count(),
        ]);
    }

    public function markRead(Notification $notification, Request $request)
    {
        $user = $request->user();

        abort_unless(
            $notification->user_id === $user->id || ($notification->role && $user->hasRole($notification->role)),
            403
        );

        $notification->update([
            'status' => 'read',
            'read_at' => now(),
        ]);

        return $this->ok(new NotificationResource($notification));
    }

    public function markAllRead(Request $request)
    {
        $now = now();

        $this->visibleTo($request)
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => $now,
                'updated_at' => $now,
            ]);

        return $this->ok(['unread_count' => 0], 'Notifications marked as read.');
    }

    private function visibleTo(Request $request)
    {
        $user = $request->user();
        $roles = $user->getRoleNames()->all();

        return Notification::query()
            ->where(function ($query) use ($user, $roles) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('role', $roles);
            });
    }
}
