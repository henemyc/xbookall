<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\BugReport;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class BugReportController extends BaseController
{
    /**
     * Public endpoint for users (gym owners + members) to report bugs.
     * Gym details are auto-collected from the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'             => 'required|string|max:255',
            'description'       => 'required|string|min:10',
            'gym_name'          => 'nullable|string|max:255',
            'screenshot'        => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192',
            'screenshot_base64' => 'nullable|string',
            'screenshot_name'   => 'nullable|string|max:255',
        ]);

        $user = $this->currentUser();
        $pid  = $this->getParentId();

        $gymName = $validated['gym_name'] 
            ?? $user->business_name 
            ?? $user->gym_name 
            ?? $user->company_name 
            ?? \App\Models\Setting::getValue('company_name', $pid, 'GymXBook User');

        [$screenshotPath, $hasScreenshot] = $this->storeScreenshot($request);

        $report = BugReport::create([
            'user_id'        => $user->id,
            'gym_name'       => $gymName,
            'email'          => $user->email,
            'title'          => $validated['title'],
            'description'    => $validated['description'],
            'has_screenshot' => $hasScreenshot,
            'screenshot_path'=> $screenshotPath,
            'status'         => 'open',
        ]);

        return $this->success([
            'report' => $report,
            'screenshot_url' => $screenshotPath ? $this->screenshotUrl($screenshotPath) : null,
            'message' => 'Thank you! Your bug report has been submitted.',
        ], 'Bug report submitted', 201);
    }

    private function storeScreenshot(Request $request): array
    {
        $directory = public_path('uploads/bug_screenshots');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if ($request->hasFile('screenshot') && $request->file('screenshot')->isValid()) {
            $file = $request->file('screenshot');
            $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                $extension = 'jpg';
            }
            $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'screenshot';
            $filename = now()->format('Ymd_His') . '_' . Str::random(8) . '_' . Str::slug($baseName) . '.' . $extension;
            $file->move($directory, $filename);

            return ['uploads/bug_screenshots/' . $filename, true];
        }

        $base64 = trim((string) $request->input('screenshot_base64', ''));
        if ($base64 !== '') {
            if (str_contains($base64, ',')) {
                $base64 = substr($base64, strpos($base64, ',') + 1);
            }
            $bytes = base64_decode($base64, true);
            if ($bytes !== false && strlen($bytes) > 0) {
                $original = $request->input('screenshot_name', 'screenshot.jpg');
                $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION) ?: 'jpg');
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $extension = 'jpg';
                }
                $filename = now()->format('Ymd_His') . '_' . Str::random(8) . '_screenshot.' . $extension;
                File::put($directory . DIRECTORY_SEPARATOR . $filename, $bytes);

                return ['uploads/bug_screenshots/' . $filename, true];
            }
        }

        if ($request->boolean('has_screenshot') || $request->input('has_screenshot') === '1' || $request->input('has_screenshot') === true) {
            return [$request->filled('screenshot_name') ? $request->input('screenshot_name') : null, true];
        }

        return [null, false];
    }

    private function screenshotUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'uploads/')) return asset($path);
        return asset('storage/' . ltrim($path, '/'));
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status', 'all');
        $search = $request->get('search', '');

        $query = BugReport::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('gym_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $reports = $query->paginate(20);

        return $this->success([
            'reports' => $reports->items(),
            'total' => $reports->total(),
            'current_page' => $reports->currentPage(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $report = BugReport::with('user')->findOrFail($id);
        return $this->success(['report' => $report]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $report = BugReport::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved',
            'admin_notes' => 'nullable|string',
        ]);

        $report->update($validated);

        $this->notifyBugReportUser($report, $validated);

        // Optional: Send WhatsApp reply notification (if admin_notes provided)
        if (!empty($validated['admin_notes']) && $report->user) {
            try {
                $whatsapp = new \App\Services\WhatsAppService();
                if ($whatsapp->isConfigured() && $report->user->phone_number) {
                    $whatsapp->sendTemplate(
                        $report->user->phone_number,
                        'gymxbook_bug_reply', // You can create a simple template or use custom
                        'en',
                        [
                            $report->user->name ?? 'User',
                            \Illuminate\Support\Str::limit($report->title, 30),
                            $report->status,
                            \Illuminate\Support\Str::limit($validated['admin_notes'], 100),
                        ],
                        $report->user->id,
                        $report->user->name
                    );
                }
            } catch (\Exception $e) {
                \Log::info('WhatsApp bug reply failed (non-blocking): ' . $e->getMessage());
            }
        }

        return $this->success(['report' => $report], 'Bug report updated and user notified');
    }

    public function destroy(int $id): JsonResponse
    {
        $report = BugReport::findOrFail($id);
        $report->delete();

        return $this->success([], 'Bug report deleted');
    }

    // =====================================================
    // WEB (Super Admin Panel) Methods
    // =====================================================

    public function webIndex(Request $request)
    {
        $status = $request->get('status', 'all');
        $search = $request->get('search', '');

        $query = BugReport::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('gym_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $reports = $query->paginate(25);

        return view('admin.bugs.index', compact('reports', 'status', 'search'));
    }

    public function webShow(int $id)
    {
        $report = BugReport::with('user')->findOrFail($id);
        return view('admin.bugs.show', compact('report'));
    }

    public function webUpdate(Request $request, int $id)
    {
        $report = BugReport::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $oldStatus = $report->status;
        $report->update($validated);

        $this->notifyBugReportUser($report, $validated);

        // Optional WhatsApp notification if admin added notes
        if (!empty($validated['admin_notes']) && $report->user && $report->user->phone_number) {
            try {
                $whatsapp = new \App\Services\WhatsAppService();
                if ($whatsapp->isConfigured()) {
                    $whatsapp->sendTemplate(
                        $report->user->phone_number,
                        'gymxbook_bug_reply',
                        'en',
                        [
                            $report->user->name ?? 'User',
                            \Illuminate\Support\Str::limit($report->title, 35),
                            ucfirst($report->status),
                            \Illuminate\Support\Str::limit($validated['admin_notes'], 80),
                        ],
                        $report->user->id,
                        $report->user->name
                    );
                }
            } catch (\Exception $e) {
                \Log::info('WhatsApp bug reply failed (non-blocking): ' . $e->getMessage());
            }
        }

        return redirect()->route('admin.bugs.show', $report->id)
            ->with('success', 'Bug report updated. User has been notified.');
    }

    public function bulkAction(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:bug_reports,id',
            'action' => 'required|in:open,in_progress,resolved,delete',
        ]);

        $reports = BugReport::whereIn('id', $data['ids'])->get();
        if ($data['action'] === 'delete') {
            BugReport::whereIn('id', $reports->pluck('id'))->delete();
            return redirect()->route('admin.bugs.index')->with('success', $reports->count() . ' bug reports deleted');
        }

        foreach ($reports as $report) {
            $report->update(['status' => $data['action']]);
            $this->notifyBugReportUser($report, ['status' => $data['action'], 'admin_notes' => null]);
        }
        return redirect()->route('admin.bugs.index')->with('success', $reports->count() . ' bug reports updated');
    }

    private function notifyBugReportUser(BugReport $report, array $validated): void
    {
        if (!$report->user_id) return;

        try {
            $recipient = \App\Models\User::find($report->user_id);
            if (!$recipient) return;

            $ownerId = in_array($recipient->type, ['admin', 'owner'], true)
                ? (int) $recipient->id
                : (int) ($recipient->parent_id ?: $recipient->id);

            $note = trim((string) ($validated['admin_notes'] ?? ''));
            $message = $note !== ''
                ? 'Admin replied on your bug report "' . \Illuminate\Support\Str::limit($report->title, 45) . '": ' . \Illuminate\Support\Str::limit($note, 120)
                : 'Your bug report "' . \Illuminate\Support\Str::limit($report->title, 45) . '" status changed to: ' . ucfirst(str_replace('_', ' ', $report->status));

            $payload = [
                'parent_id' => $ownerId,
                'user_id' => $recipient->id,
                'title' => 'Bug Report Reply',
                'message' => $message,
                'type' => 'bug_report',
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('app_notifications', 'created_at')) {
                $payload['created_at'] = now('Asia/Kolkata');
            }
            \DB::table('app_notifications')->insert($payload);
        } catch (\Throwable $e) {
            \Log::warning('Failed to create bug reply notification: ' . $e->getMessage());
        }
    }
}