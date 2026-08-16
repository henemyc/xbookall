<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationDeliveryService;
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

        $gyms = User::where('type', 'admin')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        return view('admin.notifications.index', compact('notifications', 'search', 'type', 'gyms'));
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
            'gym_ids' => 'nullable|array',
            'gym_ids.*' => 'integer|exists:users,id',
        ]);

        // Send to all active gyms, or an explicitly selected set of active gyms.
        $selectedGymIds = collect($request->input('gym_ids', []))->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $gyms = User::where('type', 'admin')
            ->where('is_active', true)
            ->when($selectedGymIds->isNotEmpty(), fn ($q) => $q->whereIn('id', $selectedGymIds))
            ->get();

        $count = 0;
        $delivery = app(NotificationDeliveryService::class);
        foreach ($gyms as $gym) {
            $delivery->notifyGymOwner($gym->id, $request->title, $request->message, $request->type, [
                'source' => 'super_admin',
                'category' => 'super_admin',
            ]);
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
