<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Services\CashfreeService;
use App\Services\RazorpayService;
use App\Services\PayUService;
use App\Services\PhonePeService;
use App\Services\InstamojoService;
use App\Services\Payments\PaymentGatewayManager;
use App\Models\PaymentGatewaySetting;
use App\Models\Setting;
use App\Models\SubscriptionTier;
use App\Models\SubscriptionTierPrice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionController extends BaseController
{
    public function plans(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (!$user) return $this->error('Unauthorized', 401);

        if (!$this->isSubscriptionUser($user)) {
            return $this->error('Only gym owners can manage subscription', 403);
        }

        // New SaaS tier system: Bronze / Silver / Gold with duration pricing.
        $tiers = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('subscription_tiers')) {
            // Load card features with raw queries so the app keeps working even
            // when the Eloquent model file / relation is missing on a server.
            $cardFeaturesByTier = [];
            if (\Illuminate\Support\Facades\Schema::hasTable('subscription_tier_card_features')) {
                foreach (DB::table('subscription_tier_card_features')
                    ->where('is_visible', true)
                    ->orderBy('sort_order')
                    ->get() as $cf) {
                    $cardFeaturesByTier[(int) $cf->subscription_tier_id][] = [
                        'id' => $cf->id,
                        'label' => $cf->feature_label,
                        'is_included' => (bool) $cf->is_included,
                        'tooltip' => $cf->tooltip_text,
                        'sort_order' => $cf->sort_order,
                    ];
                }
            }

            $tiers = SubscriptionTier::with(['features', 'prices' => function ($q) {
                    $q->orderBy('sort_order')->orderBy('duration_months');
                }])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($tier) use ($cardFeaturesByTier) {
                    return [
                        'id' => $tier->id,
                        'code' => $tier->code,
                        'name' => $tier->name,
                        'description' => $tier->description,
                        'badge_text' => $tier->badge_text,
                        'is_active' => (bool) $tier->is_active,
                        'is_coming_soon' => (bool) $tier->is_coming_soon,
                        'features' => $tier->features->map(function ($feature) {
                            return [
                                'id' => $feature->id,
                                'key' => $feature->feature_key,
                                'label' => $feature->feature_label,
                                'value_type' => $feature->value_type,
                                'value' => $feature->castValue(),
                                'raw_value' => $feature->value,
                                'is_highlighted' => (bool) $feature->is_highlighted,
                                'sort_order' => $feature->sort_order,
                            ];
                        })->values(),
                        'card_features' => $cardFeaturesByTier[$tier->id] ?? [],
                        'prices' => $tier->prices->map(function ($price) {
                            return [
                                'id' => $price->id,
                                'billing_cycle' => $price->billing_cycle,
                                'duration_months' => $price->duration_months,
                                'price' => (float) $price->price,
                                'strike_price' => $price->strike_price === null ? null : (float) $price->strike_price,
                                'discount_text' => $price->discount_text,
                                'is_active' => (bool) $price->is_active,
                                'sort_order' => $price->sort_order,
                            ];
                        })->values(),
                    ];
                })->values();
        }

        // Legacy plans are still returned for backward compatibility until the
        // payment phase is fully switched to tier prices.
        $legacyPlans = Subscription::where('package_amount', '>', 0)
            ->orderBy('package_amount')
            ->get();

        $currentLegacy = null;
        $currentTier = null;
        $daysLeft = null;
        $isExpired = false;

        if ($user->subscription) {
            $currentLegacy = Subscription::find($user->subscription);
        }
        if (!empty($user->subscription_tier_id) && \Illuminate\Support\Facades\Schema::hasTable('subscription_tiers')) {
            $currentTier = SubscriptionTier::with('features', 'prices')->find($user->subscription_tier_id);
        }

        $expiry = $user->subscription_ends_at ?: $user->subscription_expire_date;
        if ($expiry) {
            $daysLeft = (int) now('Asia/Kolkata')->startOfDay()->diffInDays($expiry, false);
            $isExpired = $daysLeft < 0;
        }

        $gateway = $this->defaultGateway();
        $cashfree = $gateway && $gateway->gateway_key === 'cashfree'
            ? new CashfreeService($gateway)
            : new CashfreeService();

        return $this->success([
            'tiers' => $tiers,
            'plans' => $legacyPlans,
            'current_subscription' => $currentLegacy,
            'current_tier' => $currentTier,
            'current_tier_id' => $user->subscription_tier_id,
            'current_price_id' => $user->subscription_price_id,
            'subscription_status' => $user->subscription_status,
            'days_left' => $daysLeft,
            'is_expired' => $isExpired,
            'subscription_expire_date' => $expiry ? \Carbon\Carbon::parse($expiry)->toDateString() : null,
            'default_gateway' => $gateway ? [
                'key' => $gateway->gateway_key,
                'name' => $gateway->name,
                'mode' => $gateway->mode,
                'enabled' => $gateway->enabled,
            ] : null,
            'cashfree_configured' => $cashfree->isConfigured(),
        ]);
    }

    public function createPaymentLink(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (!$user) return $this->error('Unauthorized', 401);

        if (!$this->isSubscriptionUser($user)) {
            return $this->error('Only gym owners can buy subscription', 403);
        }


        $gateway = $this->defaultGateway();
        if (!$gateway || !$gateway->enabled) {
            return $this->error('No payment gateway is enabled. Please contact support.', 503);
        }

        if (!in_array($gateway->gateway_key, ['cashfree', 'razorpay', 'payu', 'phonepe', 'instamojo'])) {
            return $this->error($gateway->name . ' is selected as default but is not supported for subscription payments yet.', 400);
        }

        $cashfree = null;
        $razorpay = null;
        $payu = null;
        $phonepe = null;
        $instamojo = null;
        if ($gateway->gateway_key === 'cashfree') {
            $cashfree = new CashfreeService($gateway);
            if (!$cashfree->isConfigured()) return $this->error('Cashfree credentials are not configured in Super Admin → Payment Gateways.', 503);
        }
        if ($gateway->gateway_key === 'razorpay') {
            $razorpay = new RazorpayService($gateway);
            if (!$razorpay->isConfigured()) return $this->error('Razorpay credentials are not configured in Super Admin → Payment Gateways.', 503);
        }
        if ($gateway->gateway_key === 'payu') {
            $payu = new PayUService($gateway);
            if (!$payu->isConfigured()) return $this->error('PayU merchant key/salt are not configured in Super Admin → Payment Gateways.', 503);
        }
        if ($gateway->gateway_key === 'phonepe') {
            $phonepe = new PhonePeService($gateway);
            if (!$phonepe->isConfigured()) return $this->error('PhonePe merchant id/salt key/salt index are not configured in Super Admin → Payment Gateways.', 503);
        }
        if ($gateway->gateway_key === 'instamojo') {
            $instamojo = new InstamojoService($gateway);
            if (!$instamojo->isConfigured()) return $this->error('Instamojo API key/auth token are not configured in Super Admin → Payment Gateways.', 503);
        }

        $request->validate([
            'type' => 'nullable|in:renew,upgrade,topup',
            'plan_id' => 'nullable|integer|exists:subscriptions,id',
            'subscription_tier_id' => 'nullable|integer|exists:subscription_tiers,id',
            'subscription_tier_price_id' => 'nullable|integer|exists:subscription_tier_prices,id',
        ]);

        $type = $request->input('type', 'topup');
        $isTierFlow = $request->filled('subscription_tier_price_id');
        $tier = null;
        $tierPrice = null;
        $legacyPlan = null;
        $planId = 0;
        $tierId = null;
        $tierPriceId = null;
        $billingCycle = null;
        $durationMonths = null;

        if ($isTierFlow) {
            $tierPrice = SubscriptionTierPrice::with('tier')->find((int) $request->subscription_tier_price_id);
            if (!$tierPrice || !$tierPrice->tier) return $this->error('Invalid subscription duration selected.', 400);
            $tier = $tierPrice->tier;
            if (!$tier->is_active || $tier->is_coming_soon) return $this->error($tier->name . ' is coming soon.', 400);
            if (!$tierPrice->is_active) return $this->error('Selected duration is not active. Please choose another option.', 400);
            if ($request->filled('subscription_tier_id') && (int) $request->subscription_tier_id !== (int) $tier->id) {
                return $this->error('Plan and price selection mismatch.', 400);
            }

            $amount = round((float) $tierPrice->price, 2);
            $title = $tier->name . ' - ' . $tierPrice->duration_months . ' Month' . ($tierPrice->duration_months == 1 ? '' : 's');
            $tierId = $tier->id;
            $tierPriceId = $tierPrice->id;
            $billingCycle = $tierPrice->billing_cycle;
            $durationMonths = $tierPrice->duration_months;
        } else {
            if (!$request->filled('plan_id')) return $this->error('Please select a subscription plan.', 422);
            $legacyPlan = Subscription::find((int) $request->plan_id);
            if (!$legacyPlan || (float) $legacyPlan->package_amount <= 0) return $this->error('Invalid subscription plan', 400);
            $amount = round((float) $legacyPlan->package_amount, 2);
            $title = $legacyPlan->title ?? 'Plan';
            $planId = $legacyPlan->id;
            $billingCycle = $legacyPlan->interval ?? null;
            $durationMonths = $this->monthsForInterval($legacyPlan->interval ?? 'monthly');
        }

        if ($amount < 1) return $this->error('Invalid payment amount', 400);

        $existingQuery = SubscriptionOrder::where('parent_id', $user->id)
            ->where(function ($q) use ($gateway) {
                $q->where('gateway', $gateway->gateway_key);
                if ($gateway->gateway_key === 'cashfree') $q->orWhereNull('gateway');
            })
            ->whereIn('status', ['CREATED', 'ACTIVE'])
            ->where('created_at', '>', now()->subMinutes(30));

        if ($isTierFlow) $existingQuery->where('subscription_tier_price_id', $tierPriceId);
        else $existingQuery->where('plan_id', $planId);

        $existing = $existingQuery->latest()->first();
        if ($existing && $existing->link_url && $existing->link_id) {
            return $this->success([
                'order_id' => $existing->order_id,
                'link_id' => $existing->link_id,
                'payment_link' => $existing->link_url,
                'amount' => (float) $existing->amount,
                'gateway' => $existing->gateway ?: $gateway->gateway_key,
                'reused' => true,
                'expires_in_seconds' => max(60, 45 * 60 - now()->diffInSeconds($existing->created_at)),
            ], 'Existing payment link reused');
        }

        SubscriptionOrder::where('parent_id', $user->id)
            ->where(function ($q) use ($gateway) {
                $q->where('gateway', $gateway->gateway_key);
                if ($gateway->gateway_key === 'cashfree') $q->orWhereNull('gateway');
            })
            ->whereIn('status', ['CREATED', 'ACTIVE'])
            ->update(['status' => 'EXPIRED']);

        $itemIdForOrder = $isTierFlow ? ('T' . $tierId . 'P' . $tierPriceId) : ('P' . $planId);
        $orderId = 'GXB_' . $user->id . '_' . $itemIdForOrder . '_' . now('Asia/Kolkata')->format('YmdHis') . '_' . strtoupper(Str::random(6));
        $linkId = 'gxb_' . strtolower($orderId);
        $rawCustomerPhone = $user->phone_number
            ?: Setting::getValue('company_phone', (int) $user->id, '')
            ?: Setting::getValue('phone', (int) $user->id, '')
            ?: '';
        $customerPhone = $this->cleanPhone($rawCustomerPhone);
        $razorpayCustomerPhone = $this->razorpayPhone($customerPhone);
        $customerEmail = filter_var($user->email, FILTER_VALIDATE_EMAIL) ? $user->email : 'support@gymxbook.com';

        $createOrder = function (array $extra) use ($orderId, $user, $planId, $tierId, $tierPriceId, $type, $billingCycle, $durationMonths, $amount) {
            return SubscriptionOrder::create(array_merge([
                'order_id' => $orderId,
                'parent_id' => $user->id,
                'plan_id' => $planId,
                'subscription_tier_id' => $tierId,
                'subscription_tier_price_id' => $tierPriceId,
                'order_type' => $type,
                'billing_cycle' => $billingCycle,
                'duration_months' => $durationMonths,
                'amount' => $amount,
            ], $extra));
        };

        $returnPayload = function (SubscriptionOrder $created, string $gatewayKey, string $gatewayName, bool $sandbox = false) use ($amount) {
            return $this->success([
                'order_id' => $created->order_id,
                'link_id' => $created->link_id,
                'payment_link' => $created->link_url,
                'amount' => $amount,
                'gateway' => $gatewayKey,
                'gateway_name' => $gatewayName,
                'expires_in_seconds' => 45 * 60,
                'sandbox' => $sandbox,
            ], 'Payment link created');
        };

        $data = [];
        $result = [];

        if ($gateway->gateway_key === 'cashfree') {
            $linkData = [
                'link_id' => $linkId,
                'link_amount' => $amount,
                'link_currency' => 'INR',
                'link_purpose' => 'GymXBook Subscription - ' . $title,
                'link_expiry_time' => now('UTC')->addMinutes(45)->format('Y-m-d\TH:i:s\Z'),
                'customer_details' => ['customer_name' => $user->name ?: 'Gym Owner', 'customer_email' => $customerEmail, 'customer_phone' => $customerPhone],
                'link_notify' => ['send_email' => false, 'send_sms' => false],
                'link_meta' => ['return_url' => url('/subscription/return?order_id=' . urlencode($orderId)), 'notify_url' => url('/api/v1/subscription/webhook')],
                'link_notes' => ['order_id' => $orderId, 'plan_id' => (string) $planId, 'subscription_tier_id' => (string) ($tierId ?? ''), 'subscription_tier_price_id' => (string) ($tierPriceId ?? ''), 'user_id' => (string) $user->id, 'order_type' => $type, 'gateway' => 'cashfree', 'app' => 'gymxbook_flutter'],
            ];
            $result = $cashfree->createPaymentLink($linkData);
            $data = $result['data'] ?? [];
            if (($result['status'] ?? 500) >= 200 && ($result['status'] ?? 500) < 300 && !empty($data['link_url'])) {
                $created = $createOrder(['gateway' => 'cashfree', 'gateway_status' => $data['link_status'] ?? 'CREATED', 'status' => $data['link_status'] ?? 'CREATED', 'link_id' => $data['link_id'] ?? $linkId, 'link_url' => $data['link_url'], 'cf_order_id' => $data['cf_link_id'] ?? null]);
                return $returnPayload($created, 'cashfree', $gateway->name, $cashfree->isSandbox());
            }
        } elseif ($gateway->gateway_key === 'razorpay') {
            $result = $razorpay->createPaymentLink([
                'amount' => (int) round($amount * 100),
                'currency' => 'INR',
                'accept_partial' => false,
                'description' => 'GymXBook Subscription - ' . $title,
                'reference_id' => $orderId,
                'customer' => ['name' => $user->name ?: 'Gym Owner', 'email' => $customerEmail, 'contact' => $razorpayCustomerPhone],
                'notify' => ['sms' => false, 'email' => false],
                'reminder_enable' => false,
                'callback_url' => url('/api/v1/subscription/webhook/razorpay?order_id=' . urlencode($orderId)),
                'callback_method' => 'get',
                'notes' => ['order_id' => $orderId, 'plan_id' => (string) $planId, 'subscription_tier_id' => (string) ($tierId ?? ''), 'subscription_tier_price_id' => (string) ($tierPriceId ?? ''), 'user_id' => (string) $user->id, 'order_type' => $type, 'gateway' => 'razorpay', 'app' => 'gymxbook_flutter'],
            ]);
            $data = $result['data'] ?? [];
            if (($result['status'] ?? 500) >= 200 && ($result['status'] ?? 500) < 300 && !empty($data['short_url'])) {
                $created = $createOrder(['gateway' => 'razorpay', 'gateway_order_id' => $data['id'] ?? null, 'gateway_status' => strtoupper($data['status'] ?? 'CREATED'), 'status' => strtoupper($data['status'] ?? 'CREATED'), 'link_id' => $data['id'] ?? $linkId, 'link_url' => $data['short_url'], 'cf_order_id' => null]);
                return $returnPayload($created, 'razorpay', $gateway->name, $gateway->mode !== 'production');
            }
        } elseif ($gateway->gateway_key === 'payu') {
            $created = $createOrder(['gateway' => 'payu', 'gateway_order_id' => $orderId, 'gateway_status' => 'CREATED', 'status' => 'CREATED', 'link_id' => $orderId, 'link_url' => url('/payment/payu/redirect/' . urlencode($orderId)), 'cf_order_id' => null]);
            return $returnPayload($created, 'payu', $gateway->name, $gateway->mode !== 'production');
        } elseif ($gateway->gateway_key === 'phonepe') {
            $result = $phonepe->createPayPage(['transaction_id' => $orderId, 'user_id' => (string) $user->id, 'amount_paise' => (int) round($amount * 100), 'redirect_url' => url('/api/v1/subscription/webhook/phonepe?order_id=' . urlencode($orderId)), 'callback_url' => url('/api/v1/subscription/webhook/phonepe?order_id=' . urlencode($orderId)), 'phone' => $customerPhone]);
            $data = $result['data'] ?? [];
            $redirectUrl = $data['data']['instrumentResponse']['redirectInfo']['url'] ?? null;
            if (($result['status'] ?? 500) >= 200 && ($result['status'] ?? 500) < 300 && $redirectUrl) {
                $created = $createOrder(['gateway' => 'phonepe', 'gateway_order_id' => $orderId, 'gateway_status' => $data['code'] ?? 'CREATED', 'status' => 'CREATED', 'link_id' => $orderId, 'link_url' => $redirectUrl, 'cf_order_id' => null]);
                return $returnPayload($created, 'phonepe', $gateway->name, $gateway->mode !== 'production');
            }
        } elseif ($gateway->gateway_key === 'instamojo') {
            $result = $instamojo->createPaymentRequest(['purpose' => 'GymXBook Subscription - ' . $title, 'amount' => number_format($amount, 2, '.', ''), 'buyer_name' => $user->name ?: 'Gym Owner', 'email' => $customerEmail, 'phone' => $customerPhone, 'redirect_url' => url('/api/v1/subscription/webhook/instamojo?order_id=' . urlencode($orderId)), 'webhook' => url('/api/v1/subscription/webhook/instamojo?order_id=' . urlencode($orderId)), 'allow_repeated_payments' => 'False', 'send_email' => 'False', 'send_sms' => 'False']);
            $data = $result['data'] ?? [];
            $paymentRequest = $data['payment_request'] ?? [];
            $paymentRequestId = $paymentRequest['id'] ?? null;
            $longUrl = $paymentRequest['longurl'] ?? null;
            if (!empty($data['success']) && $paymentRequestId && $longUrl) {
                $created = $createOrder(['gateway' => 'instamojo', 'gateway_order_id' => $paymentRequestId, 'gateway_status' => strtoupper($paymentRequest['status'] ?? 'CREATED'), 'status' => 'CREATED', 'link_id' => $paymentRequestId, 'link_url' => $longUrl, 'cf_order_id' => null]);
                return $returnPayload($created, 'instamojo', $gateway->name, $gateway->mode !== 'production');
            }
        }

        Log::warning('Payment link create failed', ['gateway' => $gateway->gateway_key, 'status' => $result['status'] ?? null, 'data' => $data]);
        return $this->error($data['message'] ?? $data['error']['description'] ?? $data['error'] ?? 'Failed to create payment link', 400, $data);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (!$user) return $this->error('Unauthorized', 401);

        if (!$this->isSubscriptionUser($user)) {
            return $this->error('Only gym owners can verify subscription payments', 403);
        }


        $orderId = trim((string) $request->get('order_id', ''));
        if (!$orderId) return $this->error('Order ID required', 400);

        $order = SubscriptionOrder::where('order_id', $orderId)
            ->where('parent_id', $user->id)
            ->first();

        if (!$order) return $this->error('Order not found', 404);

        if ($order->status === 'PAID') {
            $user->refresh();
            $plan = Subscription::find($order->plan_id);
            return $this->success([
                'status' => 'PAID',
                'plan_title' => $plan?->title,
                'new_expiry' => optional($user->subscription_expire_date)->toDateString(),
                'message' => 'Payment already confirmed',
            ]);
        }

        if (in_array($order->status, ['FAILED', 'CANCELLED', 'EXPIRED', 'USER_DROPPED'])) {
            return $this->success([
                'status' => $order->status,
                'message' => 'Payment ' . strtolower(str_replace('_', ' ', $order->status)),
            ]);
        }

        $orderGatewayKey = $order->gateway ?: 'cashfree';
        if (!in_array($orderGatewayKey, ['cashfree', 'razorpay', 'payu', 'phonepe', 'instamojo'])) {
            return $this->error('Verification for this gateway is not integrated yet. Please contact support.', 400);
        }

        if ($orderGatewayKey === 'instamojo') {
            return $this->verifyInstamojoOrder($order);
        }

        if ($orderGatewayKey === 'phonepe') {
            return $this->verifyPhonePeOrder($order);
        }

        if ($orderGatewayKey === 'payu') {
            return $this->verifyPayUOrder($order);
        }

        if (!$order->link_id) {
            return $this->error('Payment link reference missing. Please create a new payment link.', 400);
        }

        if ($orderGatewayKey === 'razorpay') {
            return $this->verifyRazorpayOrder($order);
        }

        $cashfree = new CashfreeService(PaymentGatewayManager::find('cashfree'));
        $res = $cashfree->getPaymentLink($order->link_id);
        $data = $res['data'] ?? [];
        $linkStatus = strtoupper((string) ($data['link_status'] ?? $order->status));

        if (in_array($linkStatus, ['PAID', 'SUCCESS'])) {
            return $this->activateSubscription($order, $data);
        }

        if (in_array($linkStatus, ['EXPIRED', 'CANCELLED', 'FAILED', 'USER_DROPPED'])) {
            $order->update(['status' => $linkStatus]);
            return $this->success([
                'status' => $linkStatus,
                'message' => 'Payment ' . strtolower(str_replace('_', ' ', $linkStatus)),
            ]);
        }

        // Optional deeper check: Payment links may create order entries before link_status flips.
        $orders = $cashfree->getLinkOrders($order->link_id);
        $ordersData = $orders['data'] ?? [];
        if (is_array($ordersData)) {
            foreach ($ordersData as $cfOrder) {
                $cfOrderId = $cfOrder['order_id'] ?? $cfOrder['cf_order_id'] ?? null;
                if (!$cfOrderId) continue;
                $payments = $cashfree->getOrderPayments($cfOrderId);
                foreach (($payments['data'] ?? []) as $payment) {
                    $paymentStatus = strtoupper((string) ($payment['payment_status'] ?? ''));
                    if (in_array($paymentStatus, ['SUCCESS', 'PAID'])) {
                        $order->update(['cf_order_id' => $cfOrderId]);
                        return $this->activateSubscription($order, $data);
                    }
                }
            }
        }

        $order->update(['status' => in_array($linkStatus, ['ACTIVE', 'CREATED']) ? $linkStatus : 'ACTIVE']);

        return $this->success([
            'status' => $order->status,
            'message' => 'Payment pending',
            'link_status' => $linkStatus,
        ]);
    }

    private function verifyInstamojoOrder(SubscriptionOrder $order): JsonResponse
    {
        $instamojo = new InstamojoService(PaymentGatewayManager::find('instamojo'));
        if (!$instamojo->isConfigured()) {
            return $this->error('Instamojo credentials are not configured.', 503);
        }

        $requestId = $order->gateway_order_id ?: $order->link_id;
        if (!$requestId) {
            return $this->error('Instamojo payment request id missing.', 400);
        }

        $res = $instamojo->getPaymentRequest($requestId);
        $paymentRequest = $res['data']['payment_request'] ?? [];
        $paymentId = $instamojo->extractPaymentId($paymentRequest);

        $order->update([
            'gateway_status' => strtoupper($paymentRequest['status'] ?? 'ACTIVE'),
            'gateway_payment_id' => $paymentId ?: $order->gateway_payment_id,
        ]);

        if ($instamojo->isPaid($paymentRequest)) {
            return $this->activateSubscription($order, ['payment_id' => $paymentId]);
        }

        if ($instamojo->isFailed($paymentRequest)) {
            $order->update(['status' => 'FAILED']);
            return $this->success([
                'status' => 'FAILED',
                'message' => 'Payment failed',
                'link_status' => $order->gateway_status,
            ]);
        }

        return $this->success([
            'status' => $order->status ?: 'CREATED',
            'message' => 'Payment pending',
            'link_status' => $order->gateway_status ?: 'ACTIVE',
        ]);
    }

    private function verifyPhonePeOrder(SubscriptionOrder $order): JsonResponse
    {
        $phonepe = new PhonePeService(PaymentGatewayManager::find('phonepe'));
        if (!$phonepe->isConfigured()) {
            return $this->error('PhonePe credentials are not configured.', 503);
        }

        $transactionId = $order->gateway_order_id ?: $order->order_id;
        $res = $phonepe->status($transactionId);
        $data = $res['data'] ?? [];
        $paymentId = $data['data']['transactionId'] ?? $data['data']['providerReferenceId'] ?? null;

        $order->update([
            'gateway_status' => strtoupper($data['code'] ?? ($data['data']['state'] ?? 'ACTIVE')),
            'gateway_payment_id' => $paymentId ?: $order->gateway_payment_id,
        ]);

        if ($phonepe->isSuccessStatus($data)) {
            return $this->activateSubscription($order, ['payment_id' => $paymentId]);
        }

        if ($phonepe->isFailedStatus($data)) {
            $order->update(['status' => 'FAILED']);
            return $this->success([
                'status' => 'FAILED',
                'message' => 'Payment failed',
                'link_status' => $order->gateway_status,
            ]);
        }

        return $this->success([
            'status' => $order->status ?: 'CREATED',
            'message' => 'Payment pending',
            'link_status' => $order->gateway_status ?: 'ACTIVE',
        ]);
    }

    private function verifyPayUOrder(SubscriptionOrder $order): JsonResponse
    {
        if ($order->status === 'PAID') {
            $user = User::find($order->parent_id);
            $plan = Subscription::find($order->plan_id);
            return $this->success([
                'status' => 'PAID',
                'plan_title' => $plan?->title,
                'new_expiry' => optional($user?->subscription_expire_date)->toDateString(),
                'message' => 'Payment already confirmed',
            ]);
        }

        if (in_array($order->status, ['FAILED', 'CANCELLED', 'EXPIRED'])) {
            return $this->success([
                'status' => $order->status,
                'message' => 'Payment ' . strtolower($order->status),
            ]);
        }

        return $this->success([
            'status' => $order->status ?: 'CREATED',
            'message' => 'Payment pending. Complete PayU checkout in browser.',
            'link_status' => $order->gateway_status ?: 'CREATED',
        ]);
    }

    private function verifyRazorpayOrder(SubscriptionOrder $order): JsonResponse
    {
        $razorpay = new RazorpayService(PaymentGatewayManager::find('razorpay'));
        if (!$razorpay->isConfigured()) {
            return $this->error('Razorpay credentials are not configured.', 503);
        }

        $res = $razorpay->getPaymentLink($order->link_id);
        $data = $res['data'] ?? [];
        $status = strtolower((string) ($data['status'] ?? $order->status));

        $paymentId = null;
        if (!empty($data['payments']) && is_array($data['payments'])) {
            $firstPayment = $data['payments'][0] ?? [];
            $paymentId = $firstPayment['payment_id'] ?? $firstPayment['id'] ?? null;
        }

        $order->update([
            'gateway_status' => strtoupper($status ?: 'ACTIVE'),
            'gateway_payment_id' => $paymentId ?: $order->gateway_payment_id,
        ]);

        if ($status === 'paid') {
            return $this->activateSubscription($order, $data);
        }

        if (in_array($status, ['cancelled', 'expired'])) {
            $order->update(['status' => strtoupper($status)]);
            return $this->success([
                'status' => strtoupper($status),
                'message' => 'Payment ' . $status,
            ]);
        }

        return $this->success([
            'status' => strtoupper($status ?: 'ACTIVE'),
            'message' => 'Payment pending',
            'link_status' => strtoupper($status ?: 'ACTIVE'),
        ]);
    }

    private function activateSubscription(SubscriptionOrder $order, array $cashfreePayload = []): JsonResponse
    {
        return DB::transaction(function () use ($order, $cashfreePayload) {
            $order = SubscriptionOrder::where('id', $order->id)->lockForUpdate()->first();
            if (!$order) return $this->error('Order not found', 404);

            $user = User::where('id', $order->parent_id)->lockForUpdate()->first();
            if (!$user) return $this->error('User not found', 400);

            $isTierOrder = !empty($order->subscription_tier_id) && !empty($order->subscription_tier_price_id);
            $tier = null;
            $tierPrice = null;
            $plan = null;
            $title = '';
            $amountExpected = 0;
            $months = 1;

            if ($isTierOrder) {
                $tier = SubscriptionTier::find($order->subscription_tier_id);
                $tierPrice = SubscriptionTierPrice::find($order->subscription_tier_price_id);
                if (!$tier || !$tierPrice) return $this->error('Subscription tier or price not found', 400);
                $title = $tier->name;
                $amountExpected = (float) $tierPrice->price;
                $months = max(1, (int) $tierPrice->duration_months);
            } else {
                $plan = Subscription::find($order->plan_id);
                if (!$plan) return $this->error('Plan not found', 400);
                $title = $plan->title;
                $amountExpected = (float) $plan->package_amount;
                $months = $this->monthsForInterval($plan->interval);
            }

            if ($order->status === 'PAID') {
                return $this->success([
                    'status' => 'PAID',
                    'plan_title' => $title,
                    'new_expiry' => optional($user->subscription_ends_at ?: $user->subscription_expire_date)->toDateString(),
                    'message' => 'Payment already processed',
                ]);
            }

            // Security: only activate if amount matches the selected plan/price.
            if (round((float) $order->amount, 2) !== round($amountExpected, 2)) {
                $order->update(['status' => 'FAILED']);
                return $this->error('Payment amount mismatch. Please contact support.', 400);
            }

            $today = now('Asia/Kolkata')->startOfDay();
            $currentExpiry = $user->subscription_ends_at
                ? \Carbon\Carbon::parse($user->subscription_ends_at)->startOfDay()
                : ($user->subscription_expire_date ? $user->subscription_expire_date->copy()->startOfDay() : null);

            $baseDate = ($currentExpiry && $currentExpiry->greaterThan($today)) ? $currentExpiry : $today;
            $newExpiryDate = $baseDate->copy()->addMonthsNoOverflow($months);
            $newExpiry = $newExpiryDate->toDateString();

            $userUpdate = [
                'subscription_expire_date' => $newExpiry,
            ];

            if ($isTierOrder) {
                $userUpdate = array_merge($userUpdate, [
                    'subscription_tier_id' => $tier->id,
                    'subscription_price_id' => $tierPrice->id,
                    'subscription_status' => 'active',
                    'subscription_started_at' => now('Asia/Kolkata'),
                    'subscription_ends_at' => $newExpiryDate->endOfDay(),
                ]);
            } else {
                $userUpdate['subscription'] = $plan->id;
            }

            $user->update($userUpdate);

            $order->update([
                'status' => 'PAID',
                'gateway_status' => 'PAID',
                'gateway_payment_id' => $cashfreePayload['payment_id']
                    ?? ($cashfreePayload['payments'][0]['payment_id'] ?? null)
                    ?? $order->gateway_payment_id,
                'cf_order_id' => $cashfreePayload['order_id'] ?? $cashfreePayload['cf_order_id'] ?? $order->cf_order_id,
                'starts_at' => $order->starts_at ?: now('Asia/Kolkata'),
                'ends_at' => $order->ends_at ?: $newExpiryDate->endOfDay(),
            ]);

            $this->logActivity('subscription', 'activated', 'subscription_orders', $order->id, 'Activated subscription: ' . $title, null, ['new_expiry' => $newExpiry, 'tier_id' => $tier?->id]);

            return $this->success([
                'status' => 'PAID',
                'plan_title' => $title,
                'new_expiry' => $newExpiry,
                'message' => 'Subscription activated successfully',
            ]);
        });
    }

    public function webhook(Request $request): JsonResponse
    {
        // Webhook is intentionally conservative: it never trusts amount/user from
        // request alone. If CASHFREE_WEBHOOK_SECRET is configured, verify the
        // Cashfree signature first, then verify payment server-to-server.
        if (!$this->verifyWebhookSignature($request)) {
            Log::warning('Cashfree subscription webhook signature failed');
            return response()->json(['status' => 'invalid signature'], 401);
        }

        $payload = $request->all();
        Log::info('Cashfree subscription webhook received', ['payload' => $payload]);

        $orderId = $payload['data']['link']['link_notes']['order_id']
            ?? $payload['data']['order']['order_id']
            ?? $payload['order_id']
            ?? null;

        $linkId = $payload['data']['link']['link_id']
            ?? $payload['link_id']
            ?? null;

        $order = null;
        if ($orderId) {
            $order = SubscriptionOrder::where('order_id', $orderId)->first();
        }
        if (!$order && $linkId) {
            $order = SubscriptionOrder::where('link_id', $linkId)->first();
        }

        if ($order && !empty($order->link_id)) {
            $cashfree = new CashfreeService(PaymentGatewayManager::find('cashfree'));
            $res = $cashfree->getPaymentLink($order->link_id);
            $status = strtoupper((string) ($res['data']['link_status'] ?? ''));
            if (in_array($status, ['PAID', 'SUCCESS'])) {
                $this->activateSubscription($order, $res['data'] ?? []);
            } elseif (in_array($status, ['EXPIRED', 'CANCELLED', 'FAILED', 'USER_DROPPED'])) {
                $order->update(['status' => $status]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function instamojoCallback(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $orderId = $request->get('order_id') ?: $request->input('order_id');
        $payload = $request->all();
        $paymentRequestId = $payload['payment_request_id'] ?? $payload['payment_request'] ?? null;

        $order = null;
        if ($orderId) {
            $order = SubscriptionOrder::where('order_id', $orderId)->where('gateway', 'instamojo')->first();
        }
        if (!$order && $paymentRequestId) {
            $order = SubscriptionOrder::where('link_id', $paymentRequestId)->where('gateway', 'instamojo')->first();
        }

        if (!$order) {
            return response()->view('payments.gateway-result', [
                'title' => 'Order not found',
                'message' => 'Unable to locate Instamojo subscription order.',
                'success' => false,
            ]);
        }

        $instamojo = new InstamojoService(PaymentGatewayManager::find('instamojo'));
        $webhookVerified = $instamojo->verifyWebhook($payload);

        // Always do server-to-server verification before activation.
        $result = $this->verifyInstamojoOrder($order);
        $json = $result->getData(true);
        $paid = (($json['status'] ?? null) === 'PAID') || (($json['data']['status'] ?? null) === 'PAID');

        return response()->view('payments.gateway-result', [
            'title' => $paid ? 'Payment successful' : 'Payment pending',
            'message' => $paid
                ? 'Your GymXBook subscription has been activated. Please return to the app.'
                : ($webhookVerified ? 'Payment is being verified. Return to the app and tap Check Now.' : 'Return to the app and tap Check Now to confirm payment.'),
            'success' => $paid,
        ]);
    }

    public function phonepeCallback(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $orderId = $request->get('order_id') ?: ($request->input('order_id') ?? null);
        $payload = $request->all();

        if (!$orderId && isset($payload['data']['merchantTransactionId'])) {
            $orderId = $payload['data']['merchantTransactionId'];
        }

        if (!$orderId) {
            return response()->view('payments.gateway-result', [
                'title' => 'Payment response invalid',
                'message' => 'Missing PhonePe order id.',
                'success' => false,
            ]);
        }

        $order = SubscriptionOrder::where('order_id', $orderId)->where('gateway', 'phonepe')->first();
        if (!$order) {
            return response()->view('payments.gateway-result', [
                'title' => 'Order not found',
                'message' => 'Unable to locate PhonePe subscription order.',
                'success' => false,
            ]);
        }

        $result = $this->verifyPhonePeOrder($order);
        $json = $result->getData(true);
        $paid = (($json['status'] ?? null) === 'PAID') || (($json['data']['status'] ?? null) === 'PAID');

        return response()->view('payments.gateway-result', [
            'title' => $paid ? 'Payment successful' : 'Payment pending',
            'message' => $paid ? 'Your GymXBook subscription has been activated. Please return to the app.' : 'Return to the app and tap Check Now to confirm payment.',
            'success' => $paid,
        ]);
    }

    public function payuRedirect(string $orderId)
    {
        $order = SubscriptionOrder::where('order_id', $orderId)
            ->where('gateway', 'payu')
            ->firstOrFail();

        if (!in_array($order->status, ['CREATED', 'ACTIVE'])) {
            return view('payments.gateway-result', [
                'title' => 'Payment not available',
                'message' => 'This PayU payment link is no longer active.',
                'success' => false,
            ]);
        }

        if ($order->created_at && $order->created_at->lt(now()->subMinutes(45))) {
            $order->update(['status' => 'EXPIRED', 'gateway_status' => 'EXPIRED']);
            return view('payments.gateway-result', [
                'title' => 'Payment expired',
                'message' => 'This PayU payment link has expired. Please create a new top-up request from the app.',
                'success' => false,
            ]);
        }

        $user = User::findOrFail($order->parent_id);
        $plan = Subscription::findOrFail($order->plan_id);
        $payu = new PayUService(PaymentGatewayManager::find('payu'));
        if (!$payu->isConfigured()) {
            return view('payments.gateway-result', [
                'title' => 'PayU not configured',
                'message' => 'Please contact support.',
                'success' => false,
            ]);
        }

        $successUrl = url('/api/v1/subscription/webhook/payu?result=success');
        $failureUrl = url('/api/v1/subscription/webhook/payu?result=failure');
        $fields = $payu->buildPaymentFields($order, $user, $plan, $successUrl, $failureUrl);

        return view('payments.payu-redirect', [
            'action' => $payu->paymentUrl(),
            'fields' => $fields,
            'order' => $order,
            'plan' => $plan,
        ]);
    }

    public function payuCallback(Request $request): JsonResponse|\Illuminate\Http\Response
    {
        $data = $request->all();
        $txnid = $data['txnid'] ?? null;
        if (!$txnid) {
            return response()->view('payments.gateway-result', [
                'title' => 'Payment response invalid',
                'message' => 'Missing transaction id from PayU.',
                'success' => false,
            ]);
        }

        $order = SubscriptionOrder::where('order_id', $txnid)->where('gateway', 'payu')->first();
        if (!$order) {
            return response()->view('payments.gateway-result', [
                'title' => 'Order not found',
                'message' => 'Unable to locate subscription order.',
                'success' => false,
            ]);
        }

        $payu = new PayUService(PaymentGatewayManager::find('payu'));
        $verified = $payu->verifyResponse($data);
        $status = strtolower((string)($data['status'] ?? 'failed'));
        $paymentId = $data['mihpayid'] ?? $data['payuMoneyId'] ?? null;

        $order->update([
            'gateway_status' => strtoupper($status),
            'gateway_payment_id' => $paymentId ?: $order->gateway_payment_id,
        ]);

        if ($verified && $status === 'success') {
            $this->activateSubscription($order, ['payment_id' => $paymentId]);
            return response()->view('payments.gateway-result', [
                'title' => 'Payment successful',
                'message' => 'Your GymXBook subscription has been activated. Please return to the app.',
                'success' => true,
            ]);
        }

        $order->update(['status' => 'FAILED']);
        return response()->view('payments.gateway-result', [
            'title' => 'Payment failed',
            'message' => $verified ? 'PayU reported payment as failed.' : 'Payment verification failed.',
            'success' => false,
        ]);
    }

    public function razorpayWebhook(Request $request): JsonResponse
    {
        $gateway = PaymentGatewayManager::find('razorpay');
        $razorpay = new RazorpayService($gateway);
        $rawBody = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        if (!$razorpay->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Razorpay webhook signature failed');
            return response()->json(['status' => 'invalid signature'], 401);
        }

        $payload = $request->all();
        $paymentLink = $payload['payload']['payment_link']['entity'] ?? [];
        $payment = $payload['payload']['payment']['entity'] ?? [];
        $linkId = $paymentLink['id'] ?? null;
        $referenceId = $paymentLink['reference_id'] ?? null;

        $order = null;
        if ($referenceId) {
            $order = SubscriptionOrder::where('order_id', $referenceId)->first();
        }
        if (!$order && $linkId) {
            $order = SubscriptionOrder::where('link_id', $linkId)->first();
        }

        if ($order) {
            $order->update([
                'gateway_status' => strtoupper($paymentLink['status'] ?? ''),
                'gateway_payment_id' => $payment['id'] ?? $order->gateway_payment_id,
            ]);

            if (strtolower($paymentLink['status'] ?? '') === 'paid') {
                $this->activateSubscription($order, ['payment_id' => $payment['id'] ?? null]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    public function razorpayReturn(Request $request)
    {
        // Browser callback from Razorpay payment link. Show a clean mobile-first
        // result page instead of raw JSON, and try to verify/activate once here.
        $orderId = trim((string) ($request->get('order_id') ?: $request->get('razorpay_payment_link_reference_id') ?: ''));
        $linkId = trim((string) ($request->get('razorpay_payment_link_id') ?: ''));
        $paymentId = trim((string) ($request->get('razorpay_payment_id') ?: ''));
        $linkStatus = strtolower((string) ($request->get('razorpay_payment_link_status') ?: ''));

        $order = null;
        if ($orderId !== '') {
            $order = SubscriptionOrder::where('order_id', $orderId)->first();
        }
        if (!$order && $linkId !== '') {
            $order = SubscriptionOrder::where('link_id', $linkId)->first();
        }

        $success = false;
        $status = 'pending';
        $title = 'Payment processing';
        $message = 'We received your payment response. Please return to the GymXBook app to confirm your subscription.';
        $newExpiry = null;

        if ($order) {
            if ($paymentId !== '') {
                $order->update(['gateway_payment_id' => $paymentId]);
            }
            if ($linkStatus !== '') {
                $order->update(['gateway_status' => strtoupper($linkStatus)]);
            }

            if ($order->status !== 'PAID') {
                try {
                    $verifyResponse = $this->verifyRazorpayOrder($order);
                    $payload = $verifyResponse->getData(true);
                    $status = strtolower((string) ($payload['status'] ?? $payload['data']['status'] ?? $order->status ?? 'pending'));
                    $newExpiry = $payload['new_expiry'] ?? $payload['data']['new_expiry'] ?? null;
                } catch (\Throwable $e) {
                    Log::warning('Razorpay return verification warning', ['order_id' => $order->order_id, 'error' => $e->getMessage()]);
                    $status = strtolower((string) ($linkStatus ?: $order->status ?: 'pending'));
                }
            } else {
                $status = 'paid';
                $newExpiry = optional($order->parent?->subscription_ends_at ?: $order->parent?->subscription_expire_date)->toDateString();
            }

            $order->refresh();
            if ($order->status === 'PAID' || $status === 'paid') {
                $success = true;
                $status = 'paid';
                $title = 'Payment successful';
                $message = 'Your GymXBook subscription has been activated. Tap the button below to return to the app.';
                $newExpiry = $newExpiry ?: optional($order->parent?->subscription_ends_at ?: $order->parent?->subscription_expire_date)->toDateString();
            } elseif (in_array(strtoupper($order->status), ['FAILED', 'CANCELLED', 'EXPIRED'], true)) {
                $success = false;
                $status = strtolower($order->status);
                $title = 'Payment not completed';
                $message = 'Your payment was not completed. You can return to the app and try again.';
            }
        } elseif ($linkStatus === 'paid') {
            $success = true;
            $status = 'paid';
            $title = 'Payment received';
            $message = 'Payment was received by Razorpay. Return to the GymXBook app and tap Check Now.';
        }

        $finalOrderId = $order?->order_id ?: $orderId;
        $deepLink = 'gymxbook://subscription/result?status=' . urlencode($status) . ($finalOrderId ? '&order_id=' . urlencode($finalOrderId) : '');
        $intentLink = 'intent://subscription/result?status=' . urlencode($status) . ($finalOrderId ? '&order_id=' . urlencode($finalOrderId) : '') . '#Intent;scheme=gymxbook;package=com.gymxbook.app;end';

        return response()->view('payments.gateway-result', [
            'title' => $title,
            'message' => $message,
            'success' => $success,
            'status' => $status,
            'statusText' => strtoupper($status),
            'gateway' => 'Razorpay',
            'orderId' => $finalOrderId,
            'amount' => $order?->amount,
            'newExpiry' => $newExpiry,
            'deepLink' => $deepLink,
            'intentLink' => $intentLink,
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (!$user) return $this->error('Unauthorized', 401);

        SubscriptionOrder::where('parent_id', $user->id)
            ->whereIn('status', ['CREATED', 'ACTIVE'])
            ->update(['status' => 'CANCELLED']);

        return $this->success([], 'Pending payment cancelled');
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        $gateway = PaymentGatewayManager::find('cashfree');
        $credentials = $gateway ? $gateway->getCredentialsArray() : [];
        $secret = $credentials['webhook_secret'] ?? env('CASHFREE_WEBHOOK_SECRET', '');
        if (empty($secret)) {
            // Signature verification is optional for older setups, but server-to-server
            // Cashfree verification still happens before activation.
            return true;
        }

        $signature = $request->header('x-webhook-signature')
            ?: $request->header('X-Webhook-Signature')
            ?: $request->header('x-cf-signature')
            ?: $request->header('X-Cf-Signature');
        $timestamp = $request->header('x-webhook-timestamp')
            ?: $request->header('X-Webhook-Timestamp')
            ?: $request->header('x-cf-timestamp')
            ?: $request->header('X-Cf-Timestamp');

        if (!$signature || !$timestamp) return false;

        $rawBody = $request->getContent();
        $signedPayload = $timestamp . $rawBody;
        $expected = base64_encode(hash_hmac('sha256', $signedPayload, $secret, true));

        return hash_equals($expected, $signature);
    }

    private function defaultGateway(): ?PaymentGatewaySetting
    {
        PaymentGatewayManager::ensureDefaults();
        return PaymentGatewayManager::default();
    }

    private function isSubscriptionUser(User $user): bool
    {
        return in_array($user->type, ['admin', 'owner']);
    }

    private function cleanPhone(?string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if (strlen($digits) === 10) return $digits;
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) return substr($digits, 2);
        if (strlen($digits) >= 10) return substr($digits, -10);
        return '9999999999';
    }

    private function razorpayPhone(string $phone): string
    {
        $digits = $this->cleanPhone($phone);
        // Razorpay hosted checkout pre-fills contact more reliably with country code.
        return '+91' . $digits;
    }

    private function monthsForInterval(?string $interval): int
    {
        $value = strtolower(trim((string) $interval));
        if ($value === '') return 1;
        if (is_numeric($value)) return max(1, (int) $value);
        if (str_contains($value, 'year') || str_contains($value, 'annual') || str_contains($value, '12')) return 12;
        if (str_contains($value, 'half') || str_contains($value, '6')) return 6;
        if (str_contains($value, 'quarter') || str_contains($value, '3')) return 3;
        if (str_contains($value, 'week')) return 1;
        return 1;
    }
}
