<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelInvoiceController extends BaseController
{
    /**
     * List invoices
     */
    public function index(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $status = $request->get('status', '');

        $query = Invoice::whereIn('parent_id', $parentIds)
            ->with(['user', 'items', 'payments']);

        if ($status) {
            $query->where('status', $status);
        }

        $invoices = $query->orderBy('created_at', 'desc')->paginate(20);

        // AJAX Load More support
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $html = '';
            foreach ($invoices as $invoice) {
                $total = $invoice->items->sum('amount');
                $paid = $invoice->payments->sum('amount');
                $due = max(0, $total - $paid);

                $statusHtml = match($invoice->status) {
                    'paid' => '<span class="badge bg-success">PAID</span>',
                    'partial' => '<span class="badge bg-warning">PARTIAL</span>',
                    default => '<span class="badge bg-danger">UNPAID</span>',
                };

                $html .= view('panel.invoices._row', [
                    'invoice' => $invoice,
                    'total' => $total,
                    'paid' => $paid,
                    'due' => $due,
                    'statusHtml' => $statusHtml,
                ])->render();
            }

            return response()->json([
                'success' => true,
                'html' => $html,
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'has_more' => $invoices->hasMorePages(),
                'next_page' => $invoices->currentPage() + 1,
            ]);
        }

        // Get members for create invoice modal
        $members = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get products and classes for quick add
        $products = Product::whereIn('parent_id', $parentIds)
            ->orderBy('title')
            ->get();

        $classes = \App\Models\GymClass::whereIn('parent_id', $parentIds)
            ->orderBy('title')
            ->get();

        return view('panel.invoices.index', compact('invoices', 'status', 'members', 'products', 'classes'));
    }

    /**
     * Show invoice
     */
    public function show(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $invoice = Invoice::where('id', $id)
            ->whereIn('parent_id', $parentIds)
            ->with(['user', 'items', 'payments'])
            ->firstOrFail();

        return view('panel.invoices.show', compact('invoice'));
    }

    /**
     * Create invoice with items
     */
    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'user_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Get next invoice number
            $maxInvoiceId = Invoice::whereIn('parent_id', $parentIds)->max('invoice_id') ?? 0;
            $invoiceId = $maxInvoiceId + 1;

            // Calculate total
            $total = 0;
            foreach ($request->items as $item) {
                $total += floatval($item['amount']);
            }

            $paidAmount = min(floatval($request->paid_amount ?? 0), $total);
            $invoiceStatus = $paidAmount >= $total && $total > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');

            // Create invoice
            $invoice = Invoice::create([
                'invoice_id' => $invoiceId,
                'user_id' => $request->user_id,
                'invoice_date' => $request->invoice_date ?? date('Y-m-d'),
                'invoice_due_date' => $request->invoice_due_date,
                'status' => $invoiceStatus,
                'notes' => $request->notes ?? '',
                'parent_id' => $pid,
            ]);

            // Add items (support product/class selection)
            foreach ($request->items as $item) {
                if (empty($item['title'])) continue;

                $typeId = $item['type_id'] ?? 0;
                if (!empty($item['product_id'])) $typeId = $item['product_id'];
                if (!empty($item['class_id'])) $typeId = $item['class_id'];

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'type_id' => $typeId,
                    'title' => $item['title'],
                    'amount' => $item['amount'],
                    'description' => $item['description'] ?? '',
                ]);
            }

            // Add payment if provided
            if ($paidAmount > 0) {
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'transaction_id' => '',
                    'payment_type' => $request->payment_method ?? 'cash',
                    'amount' => $paidAmount,
                    'payment_date' => $request->invoice_date ?? date('Y-m-d'),
                    'parent_id' => $pid,
                    'notes' => 'Initial payment',
                ]);
            }

            DB::commit();

            // Support AJAX modal submission (no page reload)
            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => true,
                    'message' => 'Invoice created successfully',
                    'invoice' => [
                        'id' => $invoice->id,
                        'invoice_id' => $invoice->invoice_id,
                        'member_name' => $invoice->user->name ?? 'N/A',
                        'member_phone' => $invoice->user->phone_number ?? '',
                        'total' => $total,
                        'paid' => $paidAmount,
                        'status' => $invoiceStatus,
                        'invoice_date' => \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y'),
                    ]
                ]);
            }

            return redirect()->route('panel.invoices.index')->with('success', 'Invoice created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'error' => 'Failed to create invoice: ' . $e->getMessage()], 500);
            }
            return back()->with('error', 'Failed to create invoice: ' . $e->getMessage());
        }
    }

    /**
     * Add payment to invoice
     */
    public function addPayment(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
        ]);

        $invoice = Invoice::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        $paymentAmount = floatval($request->amount);
        $paymentType = $request->payment_type ?? 'cash';

        // Calculate due
        $total = $invoice->items()->sum('amount');
        $alreadyPaid = $invoice->payments()->sum('amount');
        $due = $total - $alreadyPaid;

        if ($due <= 0) {
            return back()->with('error', 'Invoice is already fully paid');
        }

        if ($paymentAmount > $due) {
            return back()->with('error', "Payment amount (₹{$paymentAmount}) exceeds due amount (₹{$due})");
        }

        DB::beginTransaction();
        try {
            InvoicePayment::create([
                'invoice_id' => $id,
                'transaction_id' => '',
                'payment_type' => $paymentType,
                'amount' => $paymentAmount,
                'payment_date' => $request->payment_date ?? date('Y-m-d'),
                'parent_id' => $pid,
                'notes' => $request->notes ?? '',
            ]);

            // Update invoice status
            $newPaid = $alreadyPaid + $paymentAmount;
            $newStatus = $newPaid >= $total ? 'paid' : ($newPaid > 0 ? 'partial' : 'unpaid');
            $invoice->update(['status' => $newStatus]);

            DB::commit();

            return back()->with('success', "Payment of ₹{$paymentAmount} added successfully");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to add payment');
        }
    }

    /**
     * Delete invoice (AJAX supported)
     * TEMPORARILY DISABLED - Delete option commented out in UI
     */
    public function destroy(int $id)
    {
        // TEMPORARILY COMMENTED / DISABLED
        // Delete functionality is temporarily turned off for invoices.

        $isAjax = request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => false,
                'error' => 'Delete option is temporarily disabled for invoices.'
            ], 403);
        }

        return back()->with('error', 'Delete option is temporarily disabled for invoices.');

        /*
        // Original delete logic (commented out)
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $invoice = Invoice::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        DB::beginTransaction();
        try {
            InvoiceItem::where('invoice_id', $id)->delete();
            InvoicePayment::where('invoice_id', $id)->delete();
            $invoice->delete();

            DB::commit();

            if ($isAjax) {
                return response()->json(['success' => true, 'message' => 'Invoice deleted successfully']);
            }

            return redirect()->route('panel.invoices.index')->with('success', 'Invoice deleted');
        } catch (\Exception $e) {
            DB::rollBack();
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Failed to delete invoice'], 500);
            }
            return back()->with('error', 'Failed to delete invoice');
        }
        */
    }
}
