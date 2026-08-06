<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    /**
     * List all notifications across all gyms
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $type = $request->get('type', '');

        $query = AppNotification::with('parent')
            ->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->where('type', $type);
        }

        $notifications = $query->paginate(30);

        return view('admin.notifications.index', compact('notifications', 'search', 'type'));
    }

    /**
     * Send broadcast notification to all gyms
     */
    public function broadcast(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|in:info,warning,error,success',
        ]);

        // Get all gym admins
        $gyms = User::where('type', 'admin')
            ->where('parent_id', 1)
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($gyms as $gym) {
            // PURE RAW INSERT - never Eloquent create (avoids updated_at)
            try {
                \DB::insert(
                    "INSERT INTO app_notifications (parent_id, user_id, title, message, type) VALUES (?, ?, ?, ?, ?)",
                    [
                        $gym->id,
                        null,
                        $request->title,
                        $request->message,
                        $request->type,
                    ]
                );
            } catch (\Throwable $e) {
                \Log::warning('Broadcast notification skipped: ' . $e->getMessage());
            }
            $count++;
        }

        return redirect()->route('admin.notifications.index')
            ->with('success', "Notification sent to {$count} gyms");
    }

    /**
     * Delete notification
     */
    public function destroy(int $id)
    {
        $notification = AppNotification::findOrFail($id);
        $notification->delete();

        return redirect()->route('admin.notifications.index')
            ->with('success', 'Notification deleted');
    }

    /**
     * Delete all notifications
     */
    public function destroyAll()
    {
        AppNotification::truncate();

        return redirect()->route('admin.notifications.index')
            ->with('success', 'All notifications deleted');
    }
}
