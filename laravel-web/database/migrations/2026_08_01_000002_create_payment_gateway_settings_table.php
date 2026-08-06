<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_gateway_settings')) {
            Schema::create('payment_gateway_settings', function (Blueprint $table) {
                $table->id();
                $table->string('gateway_key', 50)->unique();
                $table->string('name', 100);
                $table->boolean('enabled')->default(false);
                $table->boolean('is_default')->default(false);
                $table->string('mode', 20)->default('sandbox'); // sandbox, production
                $table->longText('credentials')->nullable(); // encrypted JSON
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index(['enabled', 'is_default']);
            });
        }

        $gateways = [
            ['gateway_key' => 'cashfree', 'name' => 'Cashfree', 'enabled' => true, 'is_default' => true, 'mode' => 'sandbox'],
            ['gateway_key' => 'razorpay', 'name' => 'Razorpay', 'enabled' => false, 'is_default' => false, 'mode' => 'sandbox'],
            ['gateway_key' => 'payu', 'name' => 'PayU', 'enabled' => false, 'is_default' => false, 'mode' => 'sandbox'],
            ['gateway_key' => 'phonepe', 'name' => 'PhonePe', 'enabled' => false, 'is_default' => false, 'mode' => 'sandbox'],
            ['gateway_key' => 'instamojo', 'name' => 'Instamojo', 'enabled' => false, 'is_default' => false, 'mode' => 'sandbox'],
        ];

        foreach ($gateways as $gateway) {
            DB::table('payment_gateway_settings')->updateOrInsert(
                ['gateway_key' => $gateway['gateway_key']],
                array_merge($gateway, [
                    'settings' => json_encode([]),
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
