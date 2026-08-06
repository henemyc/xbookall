<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PanelSettingsController extends BaseController
{
    /**
     * Settings page
     */
    public function index()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $user = auth()->user();

        $gymProfile = [
            'company_name' => Setting::getValue('company_name', $pid, ''),
            'company_phone' => Setting::getValue('company_phone', $pid, ''),
            'company_email' => Setting::getValue('company_email', $pid, ''),
            'company_address' => Setting::getValue('company_address', $pid, ''),
            'company_website' => Setting::getValue('company_website', $pid, ''),
        ];

        return view('panel.settings.index', compact('gymProfile', 'user'));
    }

    /**
     * Update gym / business profile (AJAX supported)
     */
    public function updateProfile(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'company_name' => 'nullable|string|max:255',
            'company_phone' => 'nullable|string|max:20',
            'company_email' => 'nullable|email|max:255',
            'company_address' => 'nullable|string|max:500',
            'company_website' => 'nullable|url|max:255',
        ]);

        $fields = ['company_name', 'company_phone', 'company_email', 'company_address', 'company_website'];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::setValue($field, $request->$field ?? '', $pid);
            }
        }

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Gym profile updated successfully',
                'profile' => [
                    'company_name' => Setting::getValue('company_name', $pid, ''),
                    'company_phone' => Setting::getValue('company_phone', $pid, ''),
                    'company_email' => Setting::getValue('company_email', $pid, ''),
                    'company_address' => Setting::getValue('company_address', $pid, ''),
                    'company_website' => Setting::getValue('company_website', $pid, ''),
                ]
            ]);
        }

        return redirect()->route('panel.settings.index')->with('success', 'Gym profile updated');
    }

    /**
     * Update personal profile (name, email, phone) - AJAX supported
     */
    public function updatePersonalProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone_number' => 'nullable|string|max:20',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Personal profile updated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                ]
            ]);
        }

        return redirect()->route('panel.settings.index')->with('success', 'Personal profile updated');
    }

    /**
     * Update password (AJAX supported)
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'error' => 'Current password is incorrect'
                ], 422);
            }

            return back()->with('error', 'Current password is incorrect');
        }

        $user->update(['password' => Hash::make($request->new_password)]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        }

        return back()->with('success', 'Password changed successfully');
    }
}
