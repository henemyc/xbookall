import 'package:flutter/material.dart';
import 'dart:ui';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';
import '../models/invoice.dart';

class InvoiceDetailScreen extends ConsumerStatefulWidget {
  final int invoiceDbId;
  const InvoiceDetailScreen({super.key, required this.invoiceDbId});

  @override
  ConsumerState<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends ConsumerState<InvoiceDetailScreen> {
  Invoice? invoice;
  bool loading = true;
  String? error;
  String? gymName = 'GymXBook';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final api = ref.read(apiClientProvider);
      final res = await api.getInvoice(widget.invoiceDbId);
      final data = (res is Map) ? Map<String, dynamic>.from(res) : <String, dynamic>{};
      final inv = Invoice.fromDetailJson(data['invoice'] ?? data);
      try {
        final me = await api.me();
        gymName = me['gym_info']?['name'] ?? me['user']?['name'] ?? 'GymXBook';
      } catch (_) {}
      if (mounted) setState(() { invoice = inv; loading = false; });
    } catch (e) { if (mounted) setState(() { error = e.toString(); loading = false; }); }
  }

  @override
  Widget build(BuildContext context) {
    final tt = context.tokens;
    final permissions = ref.watch(permissionProvider);
    final canDelete = permissions.can('invoices.delete');
    final canAddPayment = permissions.can('invoices.payment');
    return Scaffold(
      appBar: AppBar(
        title: Text(invoice != null ? 'INV #${invoice!.invoiceId}' : 'Invoice'),
        actions: [
          if (invoice != null && canDelete)
            IconButton(
              tooltip: 'Delete invoice',
              onPressed: _confirmDeleteInvoice,
              icon: const Icon(Icons.delete_outline_rounded, color: AppTheme.danger),
            ),
        ],
      ),
      body: loading
          ? const SkeletonList(count: 3)
          : error != null
              ? ErrorRetry(message: 'Could not load invoice.', onRetry: _load)
              : invoice == null
                  ? const EmptyState(icon: Icons.receipt_long_rounded, title: 'Invoice not found')
                  : SingleChildScrollView(
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
                      child: Column(children: [
                        FadeInUp(child: _paper(tt)),
                        const SizedBox(height: 20),
                        if (invoice!.dueAmount > 0 && canAddPayment)
                          FadeInUp(delayMs: 80, child: FireButton(label: 'Add Payment  •  Due ₹${invoice!.dueAmount.toStringAsFixed(0)}', icon: Icons.payments_rounded, onPressed: () => _showPaymentSheet(invoice!))),
                      ]),
                    ),
    );
  }

  Future<void> _confirmDeleteInvoice() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete invoice?'),
        content: const Text('This invoice, its line items, and all payment transactions recorded from this invoice will be permanently deleted.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(apiClientProvider).deleteInvoice(widget.invoiceDbId);
      if (!mounted) return;
      Toast.success(context, 'Invoice and related transactions deleted');
      Navigator.pop(context, true);
    } catch (_) {
      if (mounted) Toast.error(context, 'Could not delete invoice');
    }
  }

  Widget _paper(dynamic tt) {
    final c = invoice!.statusColors;
    final statusColor = Color(c['text']);
    return Container(
      decoration: BoxDecoration(color: tt.surface, borderRadius: BorderRadius.circular(24), border: Border.all(color: tt.border), boxShadow: context.softShadow),
      child: Stack(children: [
        Positioned.fill(child: Center(child: Opacity(opacity: 0.035, child: Text(gymName ?? 'GYM', style: GoogleFonts.spaceGrotesk(fontSize: 62, fontWeight: FontWeight.w900, letterSpacing: 4))))),
        Padding(
          padding: const EdgeInsets.all(24),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(gymName ?? 'GymXBook', style: GoogleFonts.spaceGrotesk(fontSize: 21, fontWeight: FontWeight.w700, letterSpacing: -0.5, color: tt.text)),
                const SizedBox(height: 3),
                Text('INVOICE', style: context.typo.labelSmall?.copyWith(letterSpacing: 2)),
              ])),
              StatusBadge(c['label'], color: statusColor, soft: false),
            ]),
            const SizedBox(height: 24),
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('BILL TO', style: context.typo.labelSmall),
                const SizedBox(height: 6),
                Text(invoice!.memberName, style: context.typo.titleSmall),
              ])),
              Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                _metaRow('Invoice #', '#${invoice!.invoiceId}'),
                const SizedBox(height: 4),
                _metaRow('Date', invoice!.formattedDate),
                if (invoice!.dueDate != null) ...[const SizedBox(height: 4), _metaRow('Due', invoice!.formattedDueDate)],
              ]),
            ]),
            const SizedBox(height: 20),
            Divider(color: tt.border),
            const SizedBox(height: 14),
            Row(children: [Expanded(flex: 3, child: Text('DESCRIPTION', style: context.typo.labelSmall)), Expanded(child: Text('AMOUNT', textAlign: TextAlign.right, style: context.typo.labelSmall))]),
            const SizedBox(height: 12),
            ...invoice!.items.map((item) => Padding(padding: const EdgeInsets.only(bottom: 12), child: Row(children: [
              Expanded(flex: 3, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: context.typo.titleSmall?.copyWith(fontSize: 13.5)), if (item.description.isNotEmpty) Text(item.description, style: context.typo.bodySmall?.copyWith(color: tt.textTertiary))])),
              Expanded(child: Text('₹${item.amount.toStringAsFixed(0)}', textAlign: TextAlign.right, style: GoogleFonts.spaceGrotesk(fontSize: 13.5, fontWeight: FontWeight.w600, color: tt.text))),
            ]))),
            const SizedBox(height: 6),
            Divider(color: tt.border),
            const SizedBox(height: 12),
            _totalRow('Subtotal', invoice!.totalAmount),
            const SizedBox(height: 8),
            _totalRow('Paid', invoice!.paidAmount, color: AppTheme.success),
            const SizedBox(height: 10),
            Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: (invoice!.dueAmount > 0 ? AppTheme.danger : AppTheme.success).withOpacity(0.10), borderRadius: BorderRadius.circular(12)), child: _totalRow('Amount Due', invoice!.dueAmount, isBold: true, color: invoice!.dueAmount > 0 ? AppTheme.danger : AppTheme.success)),
            const SizedBox(height: 22),
            if (invoice!.payments.isNotEmpty) ...[
              Text('PAYMENT HISTORY', style: context.typo.labelSmall?.copyWith(letterSpacing: 1)),
              const SizedBox(height: 10),
              ..._sortedPayments().map((p) => Padding(padding: const EdgeInsets.only(bottom: 8), child: Container(
                padding: const EdgeInsets.all(11),
                decoration: BoxDecoration(color: tt.surfaceAlt, borderRadius: BorderRadius.circular(12)),
                child: Row(children: [
                  IconBadge(Icons.check_rounded, color: AppTheme.success, size: 32, iconSize: 16),
                  const SizedBox(width: 10),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('₹${p.amount.toStringAsFixed(0)} • ${p.paymentType.toUpperCase()}', style: context.typo.titleSmall?.copyWith(fontSize: 13)),
                    Text(p.formattedDate, style: context.typo.bodySmall?.copyWith(color: tt.textTertiary, fontSize: 11)),
                  ])),
                ]),
              ))),
              const SizedBox(height: 12),
            ],
            Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: tt.surfaceAlt, borderRadius: BorderRadius.circular(12)), child: Text('Thank you for your business! This is a computer generated invoice. For any queries contact gym administration.', style: context.typo.bodySmall?.copyWith(color: tt.textTertiary, fontSize: 10.5, height: 1.4), textAlign: TextAlign.center)),
            const SizedBox(height: 12),
            Center(child: Text('Powered by GymXBook', style: context.typo.bodySmall?.copyWith(color: tt.textTertiary, fontSize: 10))),
          ]),
        ),
      ]),
    );
  }

  List<dynamic> _sortedPayments() {
    final list = List.from(invoice!.payments);
    list.sort((a, b) {
      final dateCmp = b.paymentDate.compareTo(a.paymentDate);
      if (dateCmp != 0) return dateCmp;
      return b.id.compareTo(a.id);
    });
    return list;
  }

  Widget _metaRow(String label, String value) => Row(mainAxisSize: MainAxisSize.min, children: [Text('$label ', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)), Text(value, style: context.typo.titleSmall?.copyWith(fontSize: 12.5))]);

  Widget _totalRow(String label, double amount, {bool isBold = false, Color? color}) => Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(label, style: (isBold ? context.typo.titleMedium : context.typo.bodyMedium)?.copyWith(color: color ?? context.tokens.text, fontWeight: isBold ? FontWeight.w700 : FontWeight.w600)),
        Text('₹${amount.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontSize: isBold ? 17 : 14, fontWeight: FontWeight.w700, color: color ?? context.tokens.text)),
      ]);

  void _showPaymentSheet(Invoice inv) {
    final amountCtrl = TextEditingController();
    String method = 'cash';
    DateTime paymentDate = DateTime.now();
    String dateYmd(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.payments_rounded, color: AppTheme.brand), const SizedBox(width: 12), Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Add Payment', style: context.typo.titleLarge), Text('Due: ₹${inv.dueAmount.toStringAsFixed(0)}', style: context.typo.bodySmall?.copyWith(color: AppTheme.danger, fontWeight: FontWeight.w600))])]),
          const SizedBox(height: 18),
          TextField(controller: amountCtrl, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Amount', prefixText: '₹ ')),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(value: method, decoration: const InputDecoration(labelText: 'Payment Method'), items: const [DropdownMenuItem(value: 'cash', child: Text('Cash')), DropdownMenuItem(value: 'upi', child: Text('UPI')), DropdownMenuItem(value: 'card', child: Text('Card')), DropdownMenuItem(value: 'online', child: Text('Online'))], onChanged: (v) => setSheet(() => method = v ?? 'cash')),
          const SizedBox(height: 12),
          InkWell(
            onTap: () async {
              final picked = await showDatePicker(context: ctx, initialDate: paymentDate, firstDate: DateTime(2020), lastDate: DateTime(2035));
              if (picked != null) setSheet(() => paymentDate = picked);
            },
            child: InputDecorator(
              decoration: const InputDecoration(labelText: 'Payment Date', prefixIcon: Icon(Icons.calendar_today_rounded)),
              child: Text(DateFormatter.formatDate(dateYmd(paymentDate)), style: context.typo.bodyLarge),
            ),
          ),
          const SizedBox(height: 20),
          FireButton(label: 'Submit Payment', onPressed: () async {
            final amt = double.tryParse(amountCtrl.text.trim()) ?? 0;
            if (amt <= 0) { Toast.error(ctx, 'Amount must be > 0'); return; }
            if (amt > inv.dueAmount) { Toast.error(ctx, 'Exceeds due: ₹${inv.dueAmount.toStringAsFixed(0)}'); return; }
            try {
              await ref.read(apiClientProvider).addInvoicePayment(inv.id, {
                'amount': amt,
                'payment_type': method,
                'payment_date': dateYmd(paymentDate),
              });
              if (mounted) Navigator.pop(ctx);
              _load();
              if (mounted) Toast.success(context, 'Payment added');
            } catch (e) { Toast.error(ctx, 'Failed'); }
          }),
        ]),
      );
    }));
  }
}
