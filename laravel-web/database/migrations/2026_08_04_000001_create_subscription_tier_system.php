<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscription_tiers')) {
            Schema::create('subscription_tiers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique(); // bronze, silver, gold - stable internal key
                $table->string('name', 120); // editable display name
                $table->text('description')->nullable();
                $table->string('badge_text', 80)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_coming_soon')->default(false);
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
                $table->index('is_coming_soon');
            });
        }

        if (!Schema::hasTable('subscription_tier_features')) {
            Schema::create('subscription_tier_features', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('subscription_tier_id');
                $table->string('feature_key', 100);
                $table->string('feature_label', 180);
                $table->string('value_type', 20)->default('bool'); // bool, number, text
                $table->string('value', 255)->default('0');
                $table->boolean('is_highlighted')->default(false);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['subscription_tier_id', 'feature_key'], 'tier_features_tier_key_unique');
                $table->index('subscription_tier_id');
                $table->index('feature_key');
                $table->index('sort_order');
            });
        }

        if (!Schema::hasTable('subscription_tier_prices')) {
            Schema::create('subscription_tier_prices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('subscription_tier_id');
                $table->string('billing_cycle', 40); // monthly, quarterly, yearly, custom
                $table->integer('duration_months')->default(1);
                $table->decimal('price', 10, 2)->default(0);
                $table->decimal('strike_price', 10, 2)->nullable();
                $table->string('discount_text', 80)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index('subscription_tier_id');
                $table->index(['is_active', 'sort_order']);
                $table->index(['billing_cycle', 'duration_months']);
            });
        }

        $this->addUserColumns();
        $this->addSubscriptionOrderColumns();
        $this->seedDefaultTiers();
    }

    public function down(): void
    {
        // Keep existing subscriptions safe. Only drop the new tier system tables
        // and nullable compatibility columns if you intentionally roll back this phase.
        if (Schema::hasTable('subscription_orders')) {
            Schema::table('subscription_orders', function (Blueprint $table) {
                foreach (['ends_at', 'starts_at', 'duration_months', 'billing_cycle', 'subscription_tier_price_id', 'subscription_tier_id'] as $column) {
                    if (Schema::hasColumn('subscription_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                foreach (['subscription_ends_at', 'subscription_started_at', 'subscription_status', 'subscription_price_id', 'subscription_tier_id'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('subscription_tier_prices');
        Schema::dropIfExists('subscription_tier_features');
        Schema::dropIfExists('subscription_tiers');
    }

    private function addUserColumns(): void
    {
        if (!Schema::hasTable('users')) return;

        foreach ([
            'subscription_tier_id' => fn(Blueprint $table) => $table->unsignedBigInteger('subscription_tier_id')->nullable()->after('subscription')->index(),
            'subscription_price_id' => fn(Blueprint $table) => $table->unsignedBigInteger('subscription_price_id')->nullable()->after('subscription_tier_id')->index(),
            'subscription_status' => fn(Blueprint $table) => $table->string('subscription_status', 30)->nullable()->after('subscription_price_id')->index(),
            'subscription_started_at' => fn(Blueprint $table) => $table->timestamp('subscription_started_at')->nullable()->after('subscription_status'),
            'subscription_ends_at' => fn(Blueprint $table) => $table->timestamp('subscription_ends_at')->nullable()->after('subscription_started_at')->index(),
        ] as $column => $callback) {
            if (!Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($callback) {
                    $callback($table);
                });
            }
        }
    }

    private function addSubscriptionOrderColumns(): void
    {
        if (!Schema::hasTable('subscription_orders')) return;

        foreach ([
            'subscription_tier_id' => fn(Blueprint $table) => $table->unsignedBigInteger('subscription_tier_id')->nullable()->after('plan_id')->index(),
            'subscription_tier_price_id' => fn(Blueprint $table) => $table->unsignedBigInteger('subscription_tier_price_id')->nullable()->after('subscription_tier_id')->index(),
            'billing_cycle' => fn(Blueprint $table) => $table->string('billing_cycle', 40)->nullable()->after('order_type'),
            'duration_months' => fn(Blueprint $table) => $table->integer('duration_months')->nullable()->after('billing_cycle'),
            'starts_at' => fn(Blueprint $table) => $table->timestamp('starts_at')->nullable()->after('gateway_status'),
            'ends_at' => fn(Blueprint $table) => $table->timestamp('ends_at')->nullable()->after('starts_at')->index(),
        ] as $column => $callback) {
            if (!Schema::hasColumn('subscription_orders', $column)) {
                Schema::table('subscription_orders', function (Blueprint $table) use ($callback) {
                    $callback($table);
                });
            }
        }
    }

    private function seedDefaultTiers(): void
    {
        $now = now();

        $tiers = [
            'bronze' => [
                'name' => 'Bronze',
                'description' => 'Start managing your gym with essential member, attendance and billing tools.',
                'badge_text' => 'Starter',
                'sort_order' => 10,
                'is_active' => 1,
                'is_coming_soon' => 0,
                'features' => [
                    ['members_limit', 'Up to 150 members', 'number', '150', true, 10],
                    ['trainers_enabled', 'Trainer management', 'bool', '0', false, 20],
                    ['trainers_limit', 'Trainer limit', 'number', '0', false, 21],
                    ['staff_enabled', 'Staff users', 'bool', '0', false, 30],
                    ['staff_limit', 'No staff users', 'number', '0', false, 31],
                    ['lockers_enabled', 'Locker management', 'bool', '0', false, 40],
                    ['classes_enabled', 'Classes', 'bool', '1', true, 50],
                    ['products_enabled', 'Products', 'bool', '0', false, 60],
                    ['expenses_enabled', 'Expenses', 'bool', '1', true, 70],
                    ['advanced_reports_enabled', 'Advanced reports', 'bool', '0', false, 80],
                    ['web_qr_login_enabled', 'Web QR login', 'bool', '1', true, 90],
                    ['bulk_import_enabled', 'Bulk member import', 'bool', '1', true, 100],
                    ['bulk_import_limit', 'Bulk import row limit', 'number', '150', true, 101],
                    ['payment_gateway_enabled', 'Online payment gateway', 'bool', '0', false, 110],
                    ['notifications_enabled', 'Notifications', 'bool', '1', true, 120],
                    ['biometric_attendance_enabled', 'Biometric attendance', 'text', 'coming_soon', false, 130],
                    ['multi_branch_enabled', 'Multi branch', 'text', 'coming_soon', false, 140],
                ],
                'prices' => [
                    ['monthly', 1, 499, null, null, 1, 10],
                    ['quarterly', 3, 1299, 1497, 'Save ₹198', 1, 20],
                    ['yearly', 12, 4999, 5988, 'Save ₹989', 1, 30],
                ],
            ],
            'silver' => [
                'name' => 'Silver',
                'description' => 'Unlock trainers, lockers, staff, bulk import and growth features.',
                'badge_text' => 'Popular',
                'sort_order' => 20,
                'is_active' => 1,
                'is_coming_soon' => 0,
                'features' => [
                    ['members_limit', 'Up to 300 members', 'number', '300', true, 10],
                    ['trainers_enabled', 'Trainer management', 'bool', '1', true, 20],
                    ['trainers_limit', 'Up to 5 trainers', 'number', '5', true, 21],
                    ['staff_enabled', 'Staff users', 'bool', '1', true, 30],
                    ['staff_limit', '3 staff users', 'number', '3', true, 31],
                    ['lockers_enabled', 'Locker management', 'bool', '1', true, 40],
                    ['classes_enabled', 'Classes', 'bool', '1', true, 50],
                    ['products_enabled', 'Products', 'bool', '1', true, 60],
                    ['expenses_enabled', 'Expenses', 'bool', '1', true, 70],
                    ['advanced_reports_enabled', 'Advanced reports', 'bool', '1', true, 80],
                    ['web_qr_login_enabled', 'Web QR login', 'bool', '1', true, 90],
                    ['bulk_import_enabled', 'Bulk member import', 'bool', '1', true, 100],
                    ['bulk_import_limit', 'Bulk import row limit', 'number', '500', true, 101],
                    ['payment_gateway_enabled', 'Online payment gateway', 'bool', '1', true, 110],
                    ['notifications_enabled', 'Notifications', 'bool', '1', true, 120],
                    ['biometric_attendance_enabled', 'Biometric attendance', 'text', 'coming_soon', false, 130],
                    ['multi_branch_enabled', 'Multi branch', 'text', 'coming_soon', false, 140],
                ],
                'prices' => [
                    ['monthly', 1, 999, null, null, 1, 10],
                    ['quarterly', 3, 2699, 2997, 'Save ₹298', 1, 20],
                    ['yearly', 12, 9999, 11988, 'Save ₹1989', 1, 30],
                ],
            ],
            'gold' => [
                'name' => 'Gold',
                'description' => 'Future plan for biometric attendance, multi-branch operations and premium automation.',
                'badge_text' => 'Coming Soon',
                'sort_order' => 30,
                'is_active' => 1,
                'is_coming_soon' => 1,
                'features' => [
                    ['members_limit', 'Up to 1000 members', 'number', '1000', true, 10],
                    ['trainers_enabled', 'Trainer management', 'bool', '1', true, 20],
                    ['trainers_limit', 'Up to 15 trainers', 'number', '15', true, 21],
                    ['staff_enabled', 'Staff users', 'bool', '1', true, 30],
                    ['staff_limit', '10 staff users', 'number', '10', true, 31],
                    ['lockers_enabled', 'Locker management', 'bool', '1', true, 40],
                    ['classes_enabled', 'Classes', 'bool', '1', true, 50],
                    ['products_enabled', 'Products', 'bool', '1', true, 60],
                    ['expenses_enabled', 'Expenses', 'bool', '1', true, 70],
                    ['advanced_reports_enabled', 'Advanced reports', 'bool', '1', true, 80],
                    ['web_qr_login_enabled', 'Web QR login', 'bool', '1', true, 90],
                    ['bulk_import_enabled', 'Bulk member import', 'bool', '1', true, 100],
                    ['bulk_import_limit', 'Bulk import row limit', 'number', '2000', true, 101],
                    ['payment_gateway_enabled', 'Online payment gateway', 'bool', '1', true, 110],
                    ['notifications_enabled', 'Notifications', 'bool', '1', true, 120],
                    ['biometric_attendance_enabled', 'Biometric attendance', 'text', 'coming_soon', true, 130],
                    ['multi_branch_enabled', 'Multi branch', 'text', 'coming_soon', true, 140],
                ],
                'prices' => [
                    ['monthly', 1, 0, null, 'Coming Soon', 0, 10],
                    ['quarterly', 3, 0, null, 'Coming Soon', 0, 20],
                    ['yearly', 12, 0, null, 'Coming Soon', 0, 30],
                ],
            ],
        ];

        foreach ($tiers as $code => $tier) {
            $tierId = DB::table('subscription_tiers')->where('code', $code)->value('id');
            $tierData = [
                'code' => $code,
                'name' => $tier['name'],
                'description' => $tier['description'],
                'badge_text' => $tier['badge_text'],
                'sort_order' => $tier['sort_order'],
                'is_active' => $tier['is_active'],
                'is_coming_soon' => $tier['is_coming_soon'],
                'updated_at' => $now,
            ];

            if ($tierId) {
                DB::table('subscription_tiers')->where('id', $tierId)->update($tierData);
            } else {
                $tierData['created_at'] = $now;
                $tierId = DB::table('subscription_tiers')->insertGetId($tierData);
            }

            foreach ($tier['features'] as [$key, $label, $valueType, $value, $highlighted, $sortOrder]) {
                DB::table('subscription_tier_features')->updateOrInsert(
                    ['subscription_tier_id' => $tierId, 'feature_key' => $key],
                    [
                        'feature_label' => $label,
                        'value_type' => $valueType,
                        'value' => (string) $value,
                        'is_highlighted' => $highlighted ? 1 : 0,
                        'sort_order' => $sortOrder,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            foreach ($tier['prices'] as [$cycle, $duration, $price, $strikePrice, $discountText, $active, $sortOrder]) {
                DB::table('subscription_tier_prices')->updateOrInsert(
                    ['subscription_tier_id' => $tierId, 'billing_cycle' => $cycle, 'duration_months' => $duration],
                    [
                        'price' => $price,
                        'strike_price' => $strikePrice,
                        'discount_text' => $discountText,
                        'is_active' => $active ? 1 : 0,
                        'sort_order' => $sortOrder,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }
        }
    }
};
