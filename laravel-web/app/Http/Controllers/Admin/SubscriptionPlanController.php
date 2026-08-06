<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionPlanController extends BaseController
{
    /**
     * List subscription plans
     */
    public function index()
    {
        $plans = Subscription::withCount('users as gym_count')
            ->orderBy('package_amount')
            ->get();

        return view('admin.plans.index', compact('plans'));
    }

    /**
     * Create plan
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'package_amount' => 'required|numeric|min:0',
            'interval' => 'required|string',
        ]);

        Subscription::create([
            'title' => $request->title,
            'package_amount' => $request->package_amount,
            'interval' => $request->interval,
            'user_limit' => $request->user_limit ?? 0,
            'trainer_limit' => $request->trainer_limit ?? 0,
            'trainee_limit' => $request->trainee_limit ?? 0,
            'enabled_logged_history' => $request->has('enabled_logged_history'),
        ]);

        return redirect()->route('admin.plans.index')->with('success', 'Plan created successfully');
    }

    /**
     * Update plan
     */
    public function update(Request $request, int $id)
    {
        $plan = Subscription::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'package_amount' => 'required|numeric|min:0',
            'interval' => 'required|string',
        ]);

        $plan->update([
            'title' => $request->title,
            'package_amount' => $request->package_amount,
            'interval' => $request->interval,
            'user_limit' => $request->user_limit ?? $plan->user_limit,
            'trainer_limit' => $request->trainer_limit ?? $plan->trainer_limit,
            'trainee_limit' => $request->trainee_limit ?? $plan->trainee_limit,
            'enabled_logged_history' => $request->has('enabled_logged_history'),
        ]);

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated successfully');
    }

    /**
     * Delete plan
     */
    public function destroy(int $id)
    {
        $plan = Subscription::findOrFail($id);

        // Check if any gyms are using this plan
        if ($plan->users()->count() > 0) {
            return redirect()->route('admin.plans.index')->with('error', 'Cannot delete plan - gyms are still using it');
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('success', 'Plan deleted successfully');
    }
}
