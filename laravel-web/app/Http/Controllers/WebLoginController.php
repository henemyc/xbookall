<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WebLoginToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WebLoginController extends BaseController
{
    /**
     * Browser creates a short-lived QR login token.
     * The token is tied to that browser session so another browser cannot poll it.
     */
    public function create(Request $request): JsonResponse
    {
        // Keep table clean: when browser requests a new QR, remove previous
        // pending QR for this browser session and all expired pending QR rows.
        WebLoginToken::where('browser_session_id', $request->session()->getId())
            ->where('status', 'pending')
            ->delete();

        WebLoginToken::where('expires_at', '<', now())
            ->where('status', 'pending')
            ->delete();

        WebLoginToken::where('created_at', '<', now()->subDay())
            ->where('status', 'expired')
            ->delete();

        $token = Str::random(64);
        $record = WebLoginToken::create([
            'token' => $token,
            'browser_session_id' => $request->session()->getId(),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
            'browser_ip' => $request->ip(),
            'browser_user_agent' => substr((string) $request->userAgent(), 0, 2000),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'expires_at' => $record->expires_at->toIso8601String(),
            'expires_in' => 120,
            'qr_payload' => json_encode([
                'type' => 'gymxbook_web_login',
                'token' => $token,
                'host' => $request->getHost(),
            ]),
        ]);
    }

    /**
     * Browser polls status. When approved by Flutter app, this logs the browser
     * session into the gym panel automatically.
     */
    public function status(Request $request): JsonResponse
    {
        $token = trim((string) $request->get('token'));
        if (!$token) {
            return response()->json(['success' => false, 'status' => 'invalid', 'message' => 'Token missing'], 400);
        }

        $record = WebLoginToken::where('token', $token)
            ->where('browser_session_id', $request->session()->getId())
            ->first();

        if (!$record) {
            return response()->json(['success' => false, 'status' => 'invalid', 'message' => 'Invalid QR session'], 404);
        }

        if ($record->isExpired() && $record->status === 'pending') {
            $record->delete();
            return response()->json(['success' => false, 'status' => 'expired', 'message' => 'QR expired']);
        }

        if ($record->status === 'approved' && $record->user_id) {
            $user = User::where('id', $record->user_id)
                ->whereIn('type', ['admin', 'owner'])
                ->where('is_active', true)
                ->first();

            if (!$user) {
                $record->delete();
                return response()->json(['success' => false, 'status' => 'invalid', 'message' => 'Gym owner not active'], 403);
            }

            Auth::login($user);
            $request->session()->regenerate();

            // Store the regenerated browser session id. This is the session that
            // must be destroyed when the gym owner logs out the PC from Flutter.
            $record->update([
                'status' => 'used',
                'used_at' => now(),
                'browser_session_id' => $request->session()->getId(),
            ]);

            return response()->json([
                'success' => true,
                'status' => 'logged_in',
                'message' => 'Login successful',
                'redirect' => session()->pull('url.intended', '/panel'),
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => $record->status,
            'message' => $record->status === 'pending' ? 'Waiting for scan' : 'Processing',
        ]);
    }

    /**
     * Flutter app approves a scanned QR token.
     */
    public function approve(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !in_array($user->type, ['admin', 'owner'])) {
            return $this->error('Only gym owners can approve web login', 403);
        }

        $raw = trim((string) $request->input('token', ''));
        $token = $this->extractToken($raw);
        if (!$token) {
            return $this->error('Invalid QR code', 400);
        }

        $record = WebLoginToken::where('token', $token)->first();
        if (!$record) {
            return $this->error('QR login request not found', 404);
        }

        if ($record->isExpired() || $record->status !== 'pending') {
            if ($record->status === 'pending') {
                $record->delete();
            }
            return $this->error('QR code expired. Refresh QR on web and scan again.', 400);
        }

        $record->update([
            'status' => 'approved',
            'user_id' => $user->id,
            'approved_at' => now(),
            'approved_ip' => $request->ip(),
            'approved_user_agent' => substr((string) $request->userAgent(), 0, 2000),
        ]);

        return $this->success([
            'status' => 'approved',
            'gym_name' => $user->name,
        ], 'Web login approved. Your browser will login automatically.');
    }

    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !in_array($user->type, ['admin', 'owner'])) {
            return $this->error('Only gym owners can view web sessions', 403);
        }

        $sessions = WebLoginToken::where('user_id', $user->id)
            ->where('status', 'used')
            ->orderBy('used_at', 'desc')
            ->limit(10)
            ->get()
            ->filter(function ($session) {
                $active = $this->browserSessionExists($session->browser_session_id);
                if (!$active) {
                    $session->delete();
                }
                return $active;
            })
            ->values()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'login_time' => optional($session->used_at)->toDateTimeString(),
                    'login_time_human' => $session->used_at ? $session->used_at->diffForHumans() : 'Recently',
                    'browser_ip' => $session->browser_ip,
                    'browser_user_agent' => $this->shortBrowserName($session->browser_user_agent),
                ];
            });

        return $this->success([
            'active_sessions' => $sessions,
            'count' => $sessions->count(),
        ]);
    }

    public function logoutSession(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !in_array($user->type, ['admin', 'owner'])) {
            return $this->error('Only gym owners can logout web sessions', 403);
        }

        $sessionId = (int) $request->input('session_id', 0);
        $query = WebLoginToken::where('user_id', $user->id)->where('status', 'used');
        if ($sessionId > 0) {
            $query->where('id', $sessionId);
        }

        $sessions = $query->get();
        if ($sessions->isEmpty()) {
            return $this->error('No active web session found', 404);
        }

        foreach ($sessions as $session) {
            $this->destroyBrowserSession($session->browser_session_id);
            $session->delete();
        }

        return $this->success([
            'logged_out' => $sessions->count(),
        ], $sessions->count() === 1 ? 'Web session logged out' : 'All web sessions logged out');
    }

    private function browserSessionExists(?string $sessionId): bool
    {
        if (!$sessionId) return false;
        $driver = config('session.driver');
        if ($driver === 'file') {
            return File::exists(storage_path('framework/sessions/' . $sessionId));
        }
        if ($driver === 'database') {
            return DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->exists();
        }
        // For redis/cookie/array drivers we cannot reliably inspect server-side.
        return true;
    }

    private function destroyBrowserSession(?string $sessionId): void
    {
        if (!$sessionId) return;
        $driver = config('session.driver');
        try {
            if ($driver === 'file') {
                $path = storage_path('framework/sessions/' . $sessionId);
                if (File::exists($path)) File::delete($path);
            } elseif ($driver === 'database') {
                DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->delete();
            }
        } catch (\Throwable $e) {
            \Log::warning('Remote web session logout failed: ' . $e->getMessage());
        }
    }

    private function shortBrowserName(?string $ua): string
    {
        $ua = (string) $ua;
        if (str_contains($ua, 'Edg/')) return 'Microsoft Edge';
        if (str_contains($ua, 'Chrome/')) return 'Chrome';
        if (str_contains($ua, 'Firefox/')) return 'Firefox';
        if (str_contains($ua, 'Safari/') && !str_contains($ua, 'Chrome/')) return 'Safari';
        return 'Web Browser';
    }

    private function extractToken(string $raw): ?string
    {
        if ($raw === '') return null;

        if (str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && !empty($decoded['token'])) {
                return trim((string) $decoded['token']);
            }
        }

        if (filter_var($raw, FILTER_VALIDATE_URL)) {
            $query = parse_url($raw, PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
                if (!empty($params['token'])) return trim((string) $params['token']);
            }
        }

        return preg_match('/^[A-Za-z0-9]{40,100}$/', $raw) ? $raw : null;
    }
}
