<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscription_tier_card_features')) {
            Schema::create('subscription_tier_card_features', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('subscription_tier_id');
                $table->string('feature_label', 180);
                $table->boolean('is_included')->default(true);
                $table->string('tooltip_text', 255)->nullable();
                $table->boolean('is_visible')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index('subscription_tier_id');
                $table->index(['is_visible', 'sort_order']);
            });
        }

        $this->seedDefaultCardFeatures();
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_tier_card_features');
    }

    private function seedDefaultCardFeatures(): void
    {
        if (!Schema::hasTable('subscription_tiers') || !Schema::hasTable('subscription_tier_card_features')) {
            return;
        }

        $now = now();
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
            $tierId = DB::table('subscription_tiers')->where('code', $tierCode)->value('id');
            if (!$tierId) continue;
            if (DB::table('subscription_tier_card_features')->where('subscription_tier_id', $tierId)->exists()) continue;

            foreach ($features as [$label, $included, $tooltip, $sort]) {
                DB::table('subscription_tier_card_features')->updateOrInsert(
                    ['subscription_tier_id' => $tierId, 'feature_label' => $label],
                    [
                        'is_included' => $included ? 1 : 0,
                        'tooltip_text' => $tooltip,
                        'is_visible' => 1,
                        'sort_order' => $sort,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }
};
