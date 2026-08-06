<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionTier;
use App\Models\User;
use App\Services\SubscriptionFeatureService;
use Illuminate\Http\Request;

class PanelSubscriptionController extends BaseController
{
    /**
     * New Bronze / Silver / Gold subscription display.
     * Payment activation for tier prices starts in the next phase; old payment
     * controller methods are kept below for backward compatibility.
     */
    public function index()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $user = User::with('subscriptionTier', 'subscriptionPrice')->find($pid);

        $tiers = collect();
        if (\Schema::hasTable('subscription_tiers')) {
            $relations = [
                'features',
                'prices' => fn($q) => $q->orderBy('sort_order')->orderBy('duration_months'),
            ];
            if (\Schema::hasTable('subscription_tier_card_features')) {
                $relations[] = 'cardFeatures';
            }

            $tiers = SubscriptionTier::with($relations)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        $current = SubscriptionFeatureService::tier($pid);
        $legacyCurrent = null;
        $daysLeft = null;
        $isExpired = false;
        $expiry = $user?->subscription_ends_at ?: $user?->subscription_expire_date;

        if (!$current && $user && $user->subscription) {
            $legacyCurrent = Subscription::find($user->subscription);
        }
        if ($expiry) {
            $daysLeft = (int) now('Asia/Kolkata')->startOfDay()->diffInDays($expiry, false);
            $isExpired = $daysLeft < 0;
        }

        $orders = SubscriptionOrder::whereIn('parent_id', $parentIds)
            ->with(['plan', 'tier', 'tierPrice'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('panel.subscription.index', compact('tiers', 'current', 'legacyCurrent', 'daysLeft', 'isExpired', 'expiry', 'orders'));
    }

    /**
     * Create tier-price payment link and redirect to gateway.
     */
    public function createPayment(Request $request)
    {
        $request->validate([
            'subscription_tier_id' => 'required|integer|exists:subscription_tiers,id',
            'subscription_tier_price_id' => 'required|integer|exists:subscription_tier_prices,id',
            'type' => 'nullable|in:renew,upgrade,topup',
        ]);

        $request->merge(['type' => $request->input('type', 'topup')]);
        $controller = app(\App\Http\Controllers\SubscriptionController::class);
        $response = $controller->createPaymentLink($request);
        $payload = $response->getData(true);
        $data = $payload['data'] ?? $payload;

        if (!empty($payload['success']) && !empty($data['payment_link'])) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($payload, $response->getStatusCode());
            }
            return redirect($data['payment_link']);
        }

        $message = $payload['error'] ?? $payload['message'] ?? $data['error'] ?? $data['message'] ?? 'Could not create payment link';
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message], $response->getStatusCode());
        }
        return back()->with('error', $message);
    }

    public function verifyPayment(Request $request)
    {
        return redirect()->route('panel.subscription.index')->with('info', 'New subscription verification starts in the next subscription phase.');
    }

    private function calculateTopUpExpiry(?string $currentExpiry, string $interval): string
    {
        $base = $currentExpiry && \Carbon\Carbon::parse($currentExpiry)->isFuture()
            ? \Carbon\Carbon::parse($currentExpiry)
            : now();

        $interval = strtolower(trim($interval));
        if (str_contains($interval, 'year') || str_contains($interval, '12')) return $base->addMonths(12)->toDateString();
        if (str_contains($interval, 'quarter') || str_contains($interval, '3')) return $base->addMonths(3)->toDateString();
        if (str_contains($interval, 'half') || str_contains($interval, '6')) return $base->addMonths(6)->toDateString();
        return $base->addMonth()->toDateString();
    }
}
