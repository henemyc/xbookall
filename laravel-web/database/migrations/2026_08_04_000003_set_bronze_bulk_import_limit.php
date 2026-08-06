<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscription_tiers') || !Schema::hasTable('subscription_tier_features')) {
            return;
        }

        $tierId = DB::table('subscription_tiers')->where('code', 'bronze')->value('id');
        if (!$tierId) {
            return;
        }

        $now = now();
        $features = [
            ['members_limit', 'Up to 150 members', 'number', '150', true, 10],
            ['bulk_import_enabled', 'Bulk member import', 'bool', '1', true, 100],
            ['bulk_import_limit', 'Bulk import row limit', 'number', '150', true, 101],
            ['trainers_enabled', 'Trainer management', 'bool', '0', false, 20],
            ['trainers_limit', 'Trainer limit', 'number', '0', false, 21],
            ['staff_enabled', 'Staff users', 'bool', '0', false, 30],
            ['staff_limit', 'No staff users', 'number', '0', false, 31],
            ['lockers_enabled', 'Locker management', 'bool', '0', false, 40],
        ];

        foreach ($features as [$key, $label, $type, $value, $highlighted, $sort]) {
            DB::table('subscription_tier_features')->updateOrInsert(
                ['subscription_tier_id' => $tierId, 'feature_key' => $key],
                [
                    'feature_label' => $label,
                    'value_type' => $type,
                    'value' => (string) $value,
                    'is_highlighted' => $highlighted ? 1 : 0,
                    'sort_order' => $sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Keep production plan settings safe. Edit from Super Admin if rollback is needed.
    }
};
