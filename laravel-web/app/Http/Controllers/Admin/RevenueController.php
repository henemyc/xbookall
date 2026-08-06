<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\SubscriptionOrder;
use App\Models\User;
use Illuminate\Http\Request;

class RevenueController extends BaseController
{
    /**
     * Revenue dashboard
     */
    public function index()
    {
        // Total revenue
        $totalRevenue = SubscriptionOrder::where('status', 'PAID')->sum('amount');

        // Monthly revenue (last 12 months)
        $monthlyRevenue = SubscriptionOrder::where('status', 'PAID')
            ->where('updated_at', '>=', now()->subMonths(12))
            ->selectRaw('YEAR(updated_at) as year, MONTH(updated_at) as month, SUM(amount) as total')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        // This month
        $thisMonth = SubscriptionOrder::where('status', 'PAID')
            ->whereMonth('updated_at', now()->month)
            ->whereYear('updated_at', now()->year)
            ->sum('amount');

        // Last month
        $lastMonth = SubscriptionOrder::where('status', 'PAID')
            ->whereMonth('updated_at', now()->subMonth()->month)
            ->whereYear('updated_at', now()->subMonth()->year)
            ->sum('amount');

        // Total orders by status
        $ordersByStatus = SubscriptionOrder::selectRaw('status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('status')
            ->get();

        // Recent payments
        $recentPayments = SubscriptionOrder::where('status', 'PAID')
            ->with(['parent', 'plan'])
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();

        return view('admin.revenue.index', compact(
            'totalRevenue', 'monthlyRevenue', 'thisMonth', 'lastMonth',
            'ordersByStatus', 'recentPayments'
        ));
    }

    /**
     * All payments list
     */
    public function payments(Request $request)
    {
        $status = $request->get('status', '');
        $search = $request->get('search', '');

        $query = SubscriptionOrder::with(['parent', 'plan']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->whereHas('parent', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate(30);

        return view('admin.revenue.payments', compact('payments', 'status', 'search'));
    }
}
