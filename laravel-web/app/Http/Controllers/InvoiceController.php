<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InvoiceController extends BaseController
{
    /**
     * List invoices
     */
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $status = $request->get('status', '');

        $query = Invoice::whereIn('parent_id', $parentIds)
            ->with(['user', 'items', 'payments']);

        $invoices = $query->orderBy('created_at', 'desc')->get();

        $invoices->each(function ($invoice) {
            $total = (float) $invoice->items->sum('amount');
            $paid = (float) $invoice->payments->sum('amount');
            $due = max(0, $total - $paid);
            $computedStatus = ($paid >= $total && $total > 0) ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

            $invoice->member_name = $invoice->user ? $invoice->user->name : '';
            $invoice->total_amount = $total;
            $invoice->paid_amount = $paid;
            $invoice->due_amount = $due;
            $invoice->status = $computedStatus;
            $invoice->makeHidden(['user', 'items', 'payments']);
        });

        if (in_array($status, ['paid', 'partial', 'unpaid'], true)) {
            $invoices = $invoices->filter(fn($invoice) => $invoice->status === $status)->values();
        }

        return $this->success(['invoices' => $invoices]);
    }

    /**
     * Create invoice
     */
    public function store(Request $request): JsonResponse
    {
        $pid = $this->getParentId();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Get next invoice number (scoped to gym)
            $maxInvoiceId = Invoice::whereIn('parent_id', $this->getGymParentIds())->max('invoice_id') ?? 0;
            $invoiceId = $maxInvoiceId + 1;

            // Calculate total
            $total = 0;
            foreach ($request->items as $item) {
                $total += floatval($item['amount']);
            }

            $paidAmount = min(floatval($request->paid_amount ?? 0), $total);
            $invoiceStatus = ($paidAmount >= $total && $total > 0) ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');

            $invoice = Invoice::create([
                'invoice_id' => $invoiceId,
                'user_id' => $request->user_id,
                'invoice_date' => $request->invoice_date ?? now()->toDateString(),
                'invoice_due_date' => $request->invoice_due_date,
                'status' => $invoiceStatus,
                'notes' => $request->notes ?? '',
                'parent_id' => $pid,
            ]);

            // Add items
            foreach ($request->items as $item) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'type_id' => $item['type_id'] ?? 0,
                    'title' => $item['title'],
                    'amount' => floatval($item['amount']),
                    'description' => $item['description'] ?? '',
                ]);
            }

            // Add initial payment if any
            if ($paidAmount > 0) {
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'transaction_id' => $request->transaction_id ?? '',
                    'payment_type' => $request->payment_method ?? 'cash',
                    'amount' => $paidAmount,
                    'payment_date' => $request->payment_date ?? now()->toDateString(),
                    'parent_id' => $pid,
                    'notes' => 'Initial payment - new invoice',
                ]);
            }

            DB::commit();

            // Reload for response
            $invoice->load(['user', 'items', 'payments']);

            return $this->success([
                'id' => $invoice->id,
                'invoice_id' => $invoiceId,
                'invoice' => $invoice,
            ], 'Invoice created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create invoice: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get invoice details
     */
    public function show(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $user = $this->currentUser();

        $query = Invoice::where('id', $id)->whereIn('parent_id', $parentIds);

        if ($user->type === 'trainee') {
            $query->where('user_id', $user->id);
        }

        $invoice = $query->with(['user', 'items', 'payments'])->first();

        if (!$invoice) {
            return $this->error('Invoice not found', 404);
        }

        $invoice->member_name = $invoice->user ? $invoice->user->name : '';
        $invoice->member_email = $invoice->user ? $invoice->user->email : '';
        $invoice->member_phone = $invoice->user ? $invoice->user->phone_number : '';
        $invoice->total_amount = $invoice->items->sum('amount');
        $invoice->paid_amount = $invoice->payments->sum('amount');
        $invoice->due_amount = $invoice->total_amount - $invoice->paid_amount;

        return $this->success(['invoice' => $invoice]);
    }

    /**
     * Add payment to invoice (Invoice Detail screen)
     */
    public function addPayment(Request $request, int $id): JsonResponse
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_type' => 'nullable|string|max:30',
            'payment_date' => 'nullable|date',
        ]);

        // Load invoice with relations for calculations
        $invoice = Invoice::where('id', $id)
            ->whereIn('parent_id', $parentIds)
            ->with(['items', 'payments'])
            ->first();

        if (!$invoice) {
            return $this->error('Invoice not found', 404);
        }

        $paymentAmount = floatval($request->amount);
        $paymentType = $request->payment_type ?? 'cash';

        // Duplicate prevention (same amount + type in last 60s)
        $duplicate = InvoicePayment::where('invoice_id', $id)
            ->where('amount', $paymentAmount)
            ->where('payment_type', $paymentType)
            ->whereIn('parent_id', $parentIds)
            ->where('created_at', '>', now()->subSeconds(60))
            ->exists();

        if ($duplicate) {
            return $this->error('Duplicate payment detected. Please wait a moment.', 400);
        }

        $totalAmount = $invoice->items->sum('amount');
        $alreadyPaid = $invoice->payments->sum('amount');
        $dueAmount = $totalAmount - $alreadyPaid;

        if ($dueAmount <= 0) {
            return $this->error('Invoice is already fully paid', 400);
        }

        if ($paymentAmount > $dueAmount) {
            return $this->error("Payment (₹{$paymentAmount}) exceeds due amount (₹{$dueAmount})", 400);
        }

        DB::beginTransaction();
        try {
            InvoicePayment::create([
                'invoice_id' => $id,
                'transaction_id' => $request->transaction_id ?? '',
                'payment_type' => $paymentType,
                'amount' => $paymentAmount,
                'payment_date' => $request->payment_date ?? now()->toDateString(),
                'parent_id' => $pid,
                'notes' => $request->notes ?? 'Payment added',
            ]);

            $newPaid = $alreadyPaid + $paymentAmount;
            $newStatus = $newPaid >= $totalAmount ? 'paid' : 'partial';

            $invoice->update(['status' => $newStatus]);

            DB::commit();

            // Return fresh data
            $invoice->refresh()->load(['items', 'payments']);
            $invoice->total_amount = $invoice->items->sum('amount');
            $invoice->paid_amount = $invoice->payments->sum('amount');
            $invoice->due_amount = $invoice->total_amount - $invoice->paid_amount;

            return $this->success([
                'invoice' => $invoice,
            ], 'Payment added successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to add payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete invoice
     */
    public function destroy(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $invoice = Invoice::where('id', $id)->whereIn('parent_id', $parentIds)->first();

        if (!$invoice) {
            return $this->error('Invoice not found', 404);
        }

        DB::beginTransaction();
        try {
            InvoiceItem::where('invoice_id', $id)->delete();
            InvoicePayment::where('invoice_id', $id)->delete();
            $invoice->delete();

            DB::commit();
            return $this->success([], 'Invoice deleted');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete invoice: ' . $e->getMessage(), 500);
        }
    }
}
