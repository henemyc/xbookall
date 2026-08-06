<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\SubscriptionTier;
use App\Models\SubscriptionTierCardFeature;
use App\Models\SubscriptionTierFeature;
use App\Models\SubscriptionTierPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SaasPlanController extends BaseController
{
    public function index()
    {
        if (!Schema::hasTable('subscription_tiers') || !Schema::hasTable('subscription_tier_features') || !Schema::hasTable('subscription_tier_prices') || !Schema::hasTable('subscription_tier_card_features')) {
            return view('admin.saas-plans.index', [
                'tiers' => collect(),
                'missingSchema' => true,
                'featureTypes' => $this->featureTypes(),
                'billingCycles' => $this->billingCycles(),
            ]);
        }

        if (SubscriptionTier::count() === 0) {
            $this->seedDefaults();
        }
        if (SubscriptionTierCardFeature::count() === 0) {
            $this->seedDefaultCardFeatures();
        }

        $tiers = SubscriptionTier::with(['features', 'prices', 'cardFeatures'])->orderBy('sort_order')->get();

        return view('admin.saas-plans.index', [
            'tiers' => $tiers,
            'missingSchema' => false,
            'featureTypes' => $this->featureTypes(),
            'billingCycles' => $this->billingCycles(),
        ]);
    }

    public function updateTier(Request $request, int $id)
    {
        $tier = SubscriptionTier::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'badge_text' => 'nullable|string|max:80',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|in:0,1',
            'is_coming_soon' => 'nullable|in:0,1',
        ]);

        $before = $tier->toArray();
        $tier->update([
            'name' => trim($data['name']),
            'description' => $data['description'] ?? '',
            'badge_text' => $data['badge_text'] ?? '',
            'sort_order' => (int) ($data['sort_order'] ?? $tier->sort_order),
            'is_active' => (int) ($data['is_active'] ?? 0) === 1,
            'is_coming_soon' => (int) ($data['is_coming_soon'] ?? 0) === 1,
        ]);

        $this->logActivity('saas_plans', 'tier_updated', 'subscription_tiers', $tier->id, 'Updated SaaS tier ' . $tier->name, $before, $tier->fresh());

        return redirect()->route('admin.saas-plans.index')->with('success', 'Plan tier updated successfully');
    }

    public function updateFeature(Request $request, int $id)
    {
        $feature = SubscriptionTierFeature::with('tier')->findOrFail($id);

        $data = $request->validate([
            'feature_label' => 'required|string|max:180',
            'value_type' => 'required|in:bool,number,text',
            'value' => 'nullable|string|max:255',
            'is_highlighted' => 'nullable|in:0,1',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);

        $before = $feature->toArray();
        $value = $data['value'] ?? '';
        if ($data['value_type'] === 'bool') {
            $value = in_array((string) $value, ['1', 'true', 'yes', 'enabled'], true) ? '1' : '0';
        }
        if ($data['value_type'] === 'number') {
            $value = (string) max(0, (int) $value);
        }

        $feature->update([
            'feature_label' => trim($data['feature_label']),
            'value_type' => $data['value_type'],
            'value' => $value,
            'is_highlighted' => (int) ($data['is_highlighted'] ?? 0) === 1,
            'sort_order' => (int) ($data['sort_order'] ?? $feature->sort_order),
        ]);

        $this->logActivity('saas_plans', 'feature_updated', 'subscription_tier_features', $feature->id, 'Updated feature ' . $feature->feature_key . ' for ' . ($feature->tier->name ?? 'tier'), $before, $feature->fresh());

        return redirect()->route('admin.saas-plans.index')->with('success', 'Feature updated successfully');
    }

    public function storeCardFeature(Request $request, int $tierId)
    {
        $tier = SubscriptionTier::findOrFail($tierId);
        $data = $this->validateCardFeature($request);

        $feature = SubscriptionTierCardFeature::create($this->cardFeaturePayload($tier->id, $data));
        $this->logActivity('saas_plans', 'card_feature_created', 'subscription_tier_card_features', $feature->id, 'Created card feature for ' . $tier->name, null, $feature);

        return redirect()->route('admin.saas-plans.index')->with('success', 'Card feature added successfully');
    }

    public function updateCardFeature(Request $request, int $id)
    {
        $feature = SubscriptionTierCardFeature::with('tier')->findOrFail($id);
        $data = $this->validateCardFeature($request);

        $before = $feature->toArray();
        $feature->update($this->cardFeaturePayload((int) $feature->subscription_tier_id, $data));
        $this->logActivity('saas_plans', 'card_feature_updated', 'subscription_tier_card_features', $feature->id, 'Updated card feature for ' . ($feature->tier->name ?? 'tier'), $before, $feature->fresh());

        return redirect()->route('admin.saas-plans.index')->with('success', 'Card feature updated successfully');
    }

    public function destroyCardFeature(int $id)
    {
        $feature = SubscriptionTierCardFeature::with('tier')->findOrFail($id);
        $before = $feature->toArray();
        $tierName = $feature->tier->name ?? 'tier';
        $feature->delete();

        $this->logActivity('saas_plans', 'card_feature_deleted', 'subscription_tier_card_features', $id, 'Deleted card feature for ' . $tierName, $before, null);

        return redirect()->route('admin.saas-plans.index')->with('success', 'Card feature deleted successfully');
    }

    public function storePrice(Request $request, int $tierId)
    {
        $tier = SubscriptionTier::findOrFail($tierId);
        $data = $this->validatePrice($request);

        $exists = SubscriptionTierPrice::where('subscription_tier_id', $tier->id)
            ->where('billing_cycle', $data['billing_cycle'])
            ->where('duration_months', (int) $data['duration_months'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'A price for this billing cycle and duration already exists for this tier.')->withInput();
        }

        $price = SubscriptionTierPrice::create($this->pricePayload($tier->id, $data));
        $this->logActivity('saas_plans', 'price_created', 'subscription_tier_prices', $price->id, 'Created price for ' . $tier->name, null, $price);

        return redirect()->route('admin.saas-plans.index')->with('success', 'Price created successfully');
    }

    public function updatePrice(Request $request, int $id)
    {
        $price = SubscriptionTierPrice::with('tier')->findOrFail($id);
        $data = $this->validatePrice($request);

        $exists = SubscriptionTierPrice::where('subscription_tier_id', $price->subscription_tier_id)
            ->where('billing_cycle', $data['billing_cycle'])
            ->where('duration_months', (int) $data['duration_months'])
            ->where('id', '!=', $price->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Another price with this billing cycle and duration already exists.')->withInput();
        }

        $before = $price->toArray();
        $price->update($this->pricePayload($price->subscription_tier_id, $data));
        $this->logActivity('saas_plans', 'price_updated', 'subscription_tier_prices', $price->id, 'Updated price for ' . ($price->tier->name ?? 'tier'), $before, $price->fresh());

        return redirect()->route('admin.saas-plans.index')->with('success', 'Price updated successfully');
    }

    public function destroyPrice(int $id)
    {
        $price = SubscriptionTierPrice::with('tier')->findOrFail($id);
        $before = $price->toArray();
        $tierName = $price->tier->name ?? 'tier';
        $price->delete();

        $this->logActivity('saas_plans', 'price_deleted', 'subscription_tier_prices', $id, 'Deleted price for ' . $tierName, $before, null);

        return redirect()->route('admin.saas-plans.index')->with('success', 'Price deleted successfully');
    }

    public function seedDefaultsNow()
    {
        $this->seedDefaults(true);
        return redirect()->route('admin.saas-plans.index')->with('success', 'Default Bronze/Silver/Gold data synced successfully');
    }

    private function validatePrice(Request $request): array
    {
        return $request->validate([
            'billing_cycle' => 'required|string|max:40',
            'duration_months' => 'required|integer|min:1|max:120',
            'price' => 'required|numeric|min:0',
            'strike_price' => 'nullable|numeric|min:0',
            'discount_text' => 'nullable|string|max:80',
            'is_active' => 'nullable|in:0,1',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);
    }

    private function pricePayload(int $tierId, array $data): array
    {
        return [
            'subscription_tier_id' => $tierId,
            'billing_cycle' => trim($data['billing_cycle']),
            'duration_months' => (int) $data['duration_months'],
            'price' => round((float) $data['price'], 2),
            'strike_price' => isset($data['strike_price']) && $data['strike_price'] !== '' ? round((float) $data['strike_price'], 2) : null,
            'discount_text' => $data['discount_text'] ?? '',
            'is_active' => (int) ($data['is_active'] ?? 0) === 1,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function validateCardFeature(Request $request): array
    {
        return $request->validate([
            'feature_label' => 'required|string|max:180',
            'is_included' => 'nullable|in:0,1',
            'tooltip_text' => 'nullable|string|max:255',
            'is_visible' => 'nullable|in:0,1',
            'sort_order' => 'nullable|integer|min:0|max:999',
        ]);
    }

    private function cardFeaturePayload(int $tierId, array $data): array
    {
        return [
            'subscription_tier_id' => $tierId,
            'feature_label' => trim($data['feature_label']),
            'is_included' => (int) ($data['is_included'] ?? 0) === 1,
            'tooltip_text' => trim((string) ($data['tooltip_text'] ?? '')) ?: null,
            'is_visible' => (int) ($data['is_visible'] ?? 0) === 1,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    private function featureTypes(): array
    {
        return ['bool' => 'Yes / No', 'number' => 'Number / Limit', 'text' => 'Text / Coming Soon'];
    }

    private function billingCycles(): array
    {
        return ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'custom' => 'Custom'];
    }

    private function seedDefaults(bool $forceUpdate = false): void
    {
        $now = now();
        $tiers = [
            ['code' => 'bronze', 'name' => 'Bronze', 'description' => 'Essential member, attendance and billing tools.', 'badge_text' => 'Starter', 'sort_order' => 10, 'coming' => 0],
            ['code' => 'silver', 'name' => 'Silver', 'description' => 'Trainers, lockers, staff, bulk import and growth features.', 'badge_text' => 'Popular', 'sort_order' => 20, 'coming' => 0],
            ['code' => 'gold', 'name' => 'Gold', 'description' => 'Future plan for biometric attendance, multi branch and premium automation.', 'badge_text' => 'Coming Soon', 'sort_order' => 30, 'coming' => 1],
        ];

        $featureRows = [
            'bronze' => [
                ['members_limit', 'Up to 150 members', 'number', '150', 1, 10],
                ['trainers_enabled', 'Trainer management', 'bool', '0', 0, 20],
                ['trainers_limit', 'Trainer limit', 'number', '0', 0, 21],
                ['staff_enabled', 'Staff users', 'bool', '0', 0, 30],
                ['staff_limit', 'No staff users', 'number', '0', 0, 31],
                ['lockers_enabled', 'Locker management', 'bool', '0', 0, 40],
                ['classes_enabled', 'Classes', 'bool', '1', 1, 50],
                ['products_enabled', 'Products', 'bool', '0', 0, 60],
                ['expenses_enabled', 'Expenses', 'bool', '1', 1, 70],
                ['advanced_reports_enabled', 'Advanced reports', 'bool', '0', 0, 80],
                ['web_qr_login_enabled', 'Web QR login', 'bool', '1', 1, 90],
                ['bulk_import_enabled', 'Bulk member import', 'bool', '1', 1, 100],
                ['bulk_import_limit', 'Bulk import row limit', 'number', '150', 1, 101],
                ['payment_gateway_enabled', 'Online payment gateway', 'bool', '0', 0, 110],
                ['notifications_enabled', 'Notifications', 'bool', '1', 1, 120],
                ['biometric_attendance_enabled', 'Biometric attendance', 'text', 'coming_soon', 0, 130],
                ['multi_branch_enabled', 'Multi branch', 'text', 'coming_soon', 0, 140],
            ],
            'silver' => [
                ['members_limit', 'Up to 300 members', 'number', '300', 1, 10],
                ['trainers_enabled', 'Trainer management', 'bool', '1', 1, 20],
                ['trainers_limit', 'Up to 5 trainers', 'number', '5', 1, 21],
                ['staff_enabled', 'Staff users', 'bool', '1', 1, 30],
                ['staff_limit', '3 staff users', 'number', '3', 1, 31],
                ['lockers_enabled', 'Locker management', 'bool', '1', 1, 40],
                ['classes_enabled', 'Classes', 'bool', '1', 1, 50],
                ['products_enabled', 'Products', 'bool', '1', 1, 60],
                ['expenses_enabled', 'Expenses', 'bool', '1', 1, 70],
                ['advanced_reports_enabled', 'Advanced reports', 'bool', '1', 1, 80],
                ['web_qr_login_enabled', 'Web QR login', 'bool', '1', 1, 90],
                ['bulk_import_enabled', 'Bulk member import', 'bool', '1', 1, 100],
                ['bulk_import_limit', 'Bulk import row limit', 'number', '500', 1, 101],
                ['payment_gateway_enabled', 'Online payment gateway', 'bool', '1', 1, 110],
                ['notifications_enabled', 'Notifications', 'bool', '1', 1, 120],
                ['biometric_attendance_enabled', 'Biometric attendance', 'text', 'coming_soon', 0, 130],
                ['multi_branch_enabled', 'Multi branch', 'text', 'coming_soon', 0, 140],
            ],
            'gold' => [
                ['members_limit', 'Up to 1000 members', 'number', '1000', 1, 10],
                ['trainers_enabled', 'Trainer management', 'bool', '1', 1, 20],
                ['trainers_limit', 'Up to 15 trainers', 'number', '15', 1, 21],
                ['staff_enabled', 'Staff users', 'bool', '1', 1, 30],
                ['staff_limit', '10 staff users', 'number', '10', 1, 31],
                ['lockers_enabled', 'Locker management', 'bool', '1', 1, 40],
                ['classes_enabled', 'Classes', 'bool', '1', 1, 50],
                ['products_enabled', 'Products', 'bool', '1', 1, 60],
                ['expenses_enabled', 'Expenses', 'bool', '1', 1, 70],
                ['advanced_reports_enabled', 'Advanced reports', 'bool', '1', 1, 80],
                ['web_qr_login_enabled', 'Web QR login', 'bool', '1', 1, 90],
                ['bulk_import_enabled', 'Bulk member import', 'bool', '1', 1, 100],
                ['bulk_import_limit', 'Bulk import row limit', 'number', '2000', 1, 101],
                ['payment_gateway_enabled', 'Online payment gateway', 'bool', '1', 1, 110],
                ['notifications_enabled', 'Notifications', 'bool', '1', 1, 120],
                ['biometric_attendance_enabled', 'Biometric attendance', 'text', 'coming_soon', 1, 130],
                ['multi_branch_enabled', 'Multi branch', 'text', 'coming_soon', 1, 140],
            ],
        ];

        $prices = [
            'bronze' => [['monthly', 1, 499, null, null, 1, 10], ['quarterly', 3, 1299, 1497, 'Save ₹198', 1, 20], ['yearly', 12, 4999, 5988, 'Save ₹989', 1, 30]],
            'silver' => [['monthly', 1, 999, null, null, 1, 10], ['quarterly', 3, 2699, 2997, 'Save ₹298', 1, 20], ['yearly', 12, 9999, 11988, 'Save ₹1989', 1, 30]],
            'gold' => [['monthly', 1, 0, null, 'Coming Soon', 0, 10], ['quarterly', 3, 0, null, 'Coming Soon', 0, 20], ['yearly', 12, 0, null, 'Coming Soon', 0, 30]],
        ];

        DB::beginTransaction();
        try {
            foreach ($tiers as $tierData) {
                $tier = SubscriptionTier::firstOrCreate(
                    ['code' => $tierData['code']],
                    [
                        'name' => $tierData['name'],
                        'description' => $tierData['description'],
                        'badge_text' => $tierData['badge_text'],
                        'sort_order' => $tierData['sort_order'],
                        'is_active' => true,
                        'is_coming_soon' => (bool) $tierData['coming'],
                    ]
                );

                if ($forceUpdate) {
                    $tier->update([
                        'description' => $tier->description ?: $tierData['description'],
                        'badge_text' => $tier->badge_text ?: $tierData['badge_text'],
                    ]);
                }

                foreach ($featureRows[$tierData['code']] as [$key, $label, $type, $value, $highlight, $sort]) {
                    SubscriptionTierFeature::updateOrCreate(
                        ['subscription_tier_id' => $tier->id, 'feature_key' => $key],
                        ['feature_label' => $label, 'value_type' => $type, 'value' => (string) $value, 'is_highlighted' => (bool) $highlight, 'sort_order' => $sort]
                    );
                }

                foreach ($prices[$tierData['code']] as [$cycle, $months, $price, $strike, $discount, $active, $sort]) {
                    SubscriptionTierPrice::updateOrCreate(
                        ['subscription_tier_id' => $tier->id, 'billing_cycle' => $cycle, 'duration_months' => $months],
                        ['price' => $price, 'strike_price' => $strike, 'discount_text' => $discount, 'is_active' => (bool) $active, 'sort_order' => $sort]
                    );
                }
            }

            $this->seedDefaultCardFeatures();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function seedDefaultCardFeatures(): void
    {
        if (!Schema::hasTable('subscription_tier_card_features')) {
            return;
        }

        $rows = [
            'bronze' => [
                ['Up to 150 members', true, 'Editable from Super Admin SaaS feature limits.', 10],
                ['Bulk import up to 150 rows', true, 'CSV import limit is controlled from Bronze system features.', 20],
                ['Web QR login', true, 'Gym owner can login to web panel using app QR approval.', 30],
                ['Free QR Sticker', true, '3 stickers included.', 40],
                ['Priority support', true, 'Standard priority support during business hours.', 50],
                ['Trainer management', false, 'Available from Silver plan.', 60],
                ['Staff & roles', false, 'Available from Silver plan.', 70],
                ['Locker management', false, 'Available from Silver plan.', 80],
            ],
            'silver' => [
                ['Up to 300 members', true, 'Editable from Super Admin SaaS feature limits.', 10],
                ['Bulk import up to 500 rows', true, 'CSV import limit is controlled from Silver system features.', 20],
                ['Trainer management', true, 'Up to 5 trainers by default.', 30],
                ['Staff & roles', true, 'Up to 3 staff users by default.', 40],
                ['Locker management', true, 'Included in Silver plan.', 50],
                ['Free QR Sticker', true, '10 stickers included.', 60],
                ['Priority support', true, 'Faster response than Bronze.', 70],
                ['Biometric attendance', false, 'Coming soon in Gold plan.', 80],
            ],
            'gold' => [
                ['Up to 1000 members', true, 'Editable from Super Admin SaaS feature limits.', 10],
                ['Trainer management', true, 'Up to 15 trainers by default.', 20],
                ['Staff & roles', true, 'Up to 10 staff users by default.', 30],
                ['Locker management', true, 'Included in Gold plan.', 40],
                ['Free QR Sticker', true, '25 stickers included.', 50],
                ['Biometric attendance', true, 'Coming soon. Enable when hardware module is ready.', 60],
                ['Multi branch', true, 'Coming soon. Enable when branch module is ready.', 70],
                ['Premium support', true, 'Highest support priority.', 80],
            ],
        ];

        foreach ($rows as $tierCode => $features) {
            $tier = SubscriptionTier::where('code', $tierCode)->first();
            if (!$tier) continue;
            if (SubscriptionTierCardFeature::where('subscription_tier_id', $tier->id)->exists()) continue;

            foreach ($features as [$label, $included, $tooltip, $sort]) {
                SubscriptionTierCardFeature::updateOrCreate(
                    ['subscription_tier_id' => $tier->id, 'feature_label' => $label],
                    [
                        'is_included' => (bool) $included,
                        'tooltip_text' => $tooltip,
                        'is_visible' => true,
                        'sort_order' => $sort,
                    ]
                );
            }
        }
    }
}
