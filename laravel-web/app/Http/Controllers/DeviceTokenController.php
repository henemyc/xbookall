<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends BaseController
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|min:40|max:4096',
            'installation_id' => 'required|string|min:16|max:80',
            'platform' => 'nullable|in:android,ios,web',
            'app_version' => 'nullable|string|max:40',
            'device_name' => 'nullable|string|max:120',
        ]);

        $user = $request->user();
        $hash = hash('sha256', $data['token']);

        // If Firebase rotates a token on this exact app installation, remove
        // the old token before storing the replacement. Other real devices are
        // intentionally preserved.
        DeviceToken::where('user_id', $user->id)
            ->where('installation_id', $data['installation_id'])
            ->where('token_hash', '!=', $hash)
            ->delete();

        // A device token can only belong to the latest authenticated account
        // on that device. This safely handles shared phones/account switching.
        DeviceToken::updateOrCreate(
            ['token_hash' => $hash],
            [
                'user_id' => $user->id,
                'token' => $data['token'],
                'installation_id' => $data['installation_id'],
                'platform' => $data['platform'] ?? 'android',
                'app_version' => $data['app_version'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'last_seen_at' => now('Asia/Kolkata'),
            ]
        );

        return $this->success([], 'Device registered for notifications');
    }

    public function unregister(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|min:40|max:4096']);
        DeviceToken::where('user_id', $request->user()->id)
            ->where('token_hash', hash('sha256', $data['token']))
            ->delete();

        return $this->success([], 'Device notification registration removed');
    }
}
