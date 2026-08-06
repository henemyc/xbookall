<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\CashfreeService;
use App\Services\RazorpayService;
use App\Services\PayUService;
use App\Services\PhonePeService;
use App\Services\InstamojoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentGatewayController extends BaseController
{
    public function index()
    {
        PaymentGatewayManager::ensureDefaults();
        $gateways = PaymentGatewaySetting::orderBy('id')->get();
        $fields = $this->credentialFields();

        return view('admin.payment-gateways.index', compact('gateways', 'fields'));
    }

    public function update(Request $request, string $gatewayKey)
    {
        PaymentGatewayManager::ensureDefaults();
        $gateway = PaymentGatewaySetting::where('gateway_key', $gatewayKey)->firstOrFail();
        $fields = $this->credentialFields()[$gatewayKey] ?? [];

        $request->validate([
            'mode' => 'required|in:sandbox,production',
            'enabled' => 'nullable|in:0,1',
            'set_default' => 'nullable|in:0,1',
        ]);

        $credentials = $gateway->getCredentialsArray();
        $maskedCredentials = $gateway->maskedCredentials();
        foreach ($fields as $field => $meta) {
            $value = $request->input('credentials.' . $field);
            $value = $value === null ? null : trim((string) $value);

            // Masked saved values are displayed inside the input. If the admin
            // submits without changing them, keep the original encrypted value.
            if ($value === null || $value === '' || $value === ($maskedCredentials[$field] ?? null) || str_contains($value, '•')) {
                continue;
            }

            $credentials[$field] = $value;
        }

        DB::beginTransaction();
        try {
            $gateway->mode = $request->input('mode', 'sandbox');
            $gateway->enabled = $request->boolean('enabled');
            $gateway->settings = [
                'updated_by' => auth()->id(),
                'updated_from' => 'super_admin_panel',
            ];
            $gateway->setCredentialsArray($credentials);
            $gateway->save();

            if ($request->boolean('set_default')) {
                PaymentGatewaySetting::where('id', '!=', $gateway->id)->update(['is_default' => false]);
                $gateway->update(['enabled' => true, 'is_default' => true]);
            } elseif ($gateway->is_default && !$gateway->enabled) {
                $next = PaymentGatewaySetting::where('enabled', true)->where('id', '!=', $gateway->id)->first();
                if ($next) {
                    $gateway->update(['is_default' => false]);
                    $next->update(['is_default' => true]);
                } else {
                    $gateway->update(['enabled' => true]);
                }
            }

            DB::commit();
            return redirect()->route('admin.payment-gateways.index')->with('success', $gateway->name . ' settings saved');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to save gateway: ' . $e->getMessage());
        }
    }

    public function setDefault(string $gatewayKey)
    {
        PaymentGatewayManager::ensureDefaults();
        $gateway = PaymentGatewaySetting::where('gateway_key', $gatewayKey)->firstOrFail();

        PaymentGatewaySetting::where('id', '!=', $gateway->id)->update(['is_default' => false]);
        $gateway->update(['enabled' => true, 'is_default' => true]);

        return redirect()->route('admin.payment-gateways.index')->with('success', $gateway->name . ' is now default gateway');
    }

    public function test(string $gatewayKey)
    {
        PaymentGatewayManager::ensureDefaults();
        $gateway = PaymentGatewaySetting::where('gateway_key', $gatewayKey)->firstOrFail();

        $result = match ($gateway->gateway_key) {
            'cashfree' => (new CashfreeService($gateway))->testConnection(),
            'razorpay' => (new RazorpayService($gateway))->testConnection(),
            'payu' => (new PayUService($gateway))->testConnection(),
            'phonepe' => (new PhonePeService($gateway))->testConnection(),
            'instamojo' => (new InstamojoService($gateway))->testConnection(),
            default => ['success' => false, 'message' => $gateway->name . ' test will be available in its integration phase.'],
        };

        return redirect()->route('admin.payment-gateways.index')
            ->with(!empty($result['success']) ? 'success' : 'error', $result['message'] ?? 'Gateway test completed');
    }

    private function credentialFields(): array
    {
        return [
            'cashfree' => [
                'app_id' => ['label' => 'App ID', 'type' => 'text'],
                'secret_key' => ['label' => 'Secret Key', 'type' => 'password'],
                'api_version' => ['label' => 'API Version', 'type' => 'text', 'placeholder' => '2023-08-01'],
                'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password'],
            ],
            'razorpay' => [
                'key_id' => ['label' => 'Key ID', 'type' => 'text'],
                'key_secret' => ['label' => 'Key Secret', 'type' => 'password'],
                'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password'],
            ],
            'payu' => [
                'merchant_key' => ['label' => 'Merchant Key', 'type' => 'text'],
                'merchant_salt' => ['label' => 'Merchant Salt', 'type' => 'password'],
                'auth_header' => ['label' => 'Auth Header / Bearer Token', 'type' => 'password'],
                'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password'],
            ],
            'phonepe' => [
                'merchant_id' => ['label' => 'Merchant ID', 'type' => 'text'],
                'salt_key' => ['label' => 'Salt Key', 'type' => 'password'],
                'salt_index' => ['label' => 'Salt Index', 'type' => 'text'],
                'client_id' => ['label' => 'Client ID', 'type' => 'text'],
                'client_secret' => ['label' => 'Client Secret', 'type' => 'password'],
            ],
            'instamojo' => [
                'api_key' => ['label' => 'API Key', 'type' => 'text'],
                'auth_token' => ['label' => 'Auth Token', 'type' => 'password'],
                'salt' => ['label' => 'Salt', 'type' => 'password'],
                'webhook_secret' => ['label' => 'Webhook Secret', 'type' => 'password'],
            ],
        ];
    }
}
