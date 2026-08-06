import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';

class ExpensesScreen extends ConsumerStatefulWidget {
  const ExpensesScreen({super.key});
  @override
  ConsumerState<ExpensesScreen> createState() => _ExpensesScreenState();
}

class _ExpensesScreenState extends ConsumerState<ExpensesScreen> {
  List expenses = [];
  double total = 0;
  bool loading = true;
  DateTime month = DateTime.now();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await ref.read(apiClientProvider).getExpenses(month: month.month, year: month.year);
      if (mounted) {
        setState(() {
          expenses = res['expenses'] ?? [];
          total = double.tryParse((res['total'] ?? 0).toString()) ?? 0;
          loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => loading = false);
    }
  }

  String _friendly(dynamic e) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) {
        final msg = data['error'] ?? data['message'];
        if (msg != null) return msg.toString();
      }
    } catch (_) {}
    return e.toString().contains('connection') ? 'No internet connection' : 'Failed. Please try again.';
  }

  @override
  Widget build(BuildContext context) {
    final perms = ref.watch(permissionProvider);
    final canCreate = perms.can('expenses.create');
    final canEdit = perms.can('expenses.edit');
    final canDelete = perms.can('expenses.delete');
    return Scaffold(
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)),
              child: Column(children: [
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  _navBtn(Icons.chevron_left_rounded, () {
                    setState(() => month = DateTime(month.year, month.month - 1));
                    _load();
                  }),
                  Column(children: [
                    Text('${_monthName(month.month)} ${month.year}', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 19)),
                    Text('Expense month', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.62), fontSize: 11)),
                  ]),
                  _navBtn(Icons.chevron_right_rounded, () {
                    setState(() => month = DateTime(month.year, month.month + 1));
                    _load();
                  }),
                ]),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.16), borderRadius: BorderRadius.circular(16)),
                  child: Row(children: [
                    IconBadge(Icons.account_balance_wallet_rounded, color: AppTheme.danger, size: 40),
                    const SizedBox(width: 12),
                    Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('₹${total.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontSize: 22, fontWeight: FontWeight.w800, color: Colors.white)),
                      Text('Total Expenses', style: GoogleFonts.poppins(fontSize: 11.5, color: Colors.white.withOpacity(0.7))),
                    ]),
                  ]),
                ),
              ]),
            ),
          ),
          Expanded(
            child: loading
                ? const SkeletonList()
                : expenses.isEmpty
                    ? const EmptyState(icon: Icons.receipt_rounded, title: 'No expenses this month', subtitle: 'Track your gym expenses here')
                    : RefreshIndicator(
                        color: AppTheme.brand,
                        onRefresh: _load,
                        child: ListView.separated(
                          padding: EdgeInsets.fromLTRB(16, 4, 16, context.navSpace + 16),
                          itemCount: expenses.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 10),
                          itemBuilder: (ctx, i) {
                            final e = Map<String, dynamic>.from(expenses[i] as Map);
                            final amount = double.tryParse((e['amount'] ?? 0).toString()) ?? 0;
                            return FadeInUp(
                              delayMs: (i * 20).clamp(0, 240),
                              offset: 10,
                              child: SurfaceCard(
                                padding: const EdgeInsets.all(12),
                                child: Row(children: [
                                  IconBadge(Icons.trending_down_rounded, color: AppTheme.danger, size: 42),
                                  const SizedBox(width: 12),
                                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Text(e['title']?.toString() ?? '', style: context.typo.titleSmall),
                                    const SizedBox(height: 2),
                                    Text('${DateFormatter.formatDate(e['date']?.toString())}${(e['type_name'] ?? '').toString().isNotEmpty ? ' • ${e['type_name']}' : ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                                  ])),
                                  Text('-₹${amount.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontSize: 15, fontWeight: FontWeight.w800, color: AppTheme.danger)),
                                  const SizedBox(width: 4),
                                  if (canEdit || canDelete)
                                    PopupMenuButton<String>(
                                      icon: Icon(Icons.more_vert_rounded, size: 21, color: context.tokens.textTertiary),
                                      onSelected: (value) {
                                        if (value == 'edit') _showEdit(e);
                                        if (value == 'delete') _deleteExpense(e);
                                      },
                                      itemBuilder: (_) => [
                                        if (canEdit) const PopupMenuItem(value: 'edit', child: Row(children: [Icon(Icons.edit_rounded, size: 18, color: AppTheme.info), SizedBox(width: 8), Text('Edit')])) ,
                                        if (canDelete) const PopupMenuItem(value: 'delete', child: Row(children: [Icon(Icons.delete_outline_rounded, size: 18, color: AppTheme.danger), SizedBox(width: 8), Text('Delete', style: TextStyle(color: AppTheme.danger))])),
                                      ],
                                    ),
                                ]),
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(
              onPressed: _showAdd,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Add Expense', style: TextStyle(fontWeight: FontWeight.w700)),
              backgroundColor: AppTheme.brand,
            )
          : null,
    );
  }

  Widget _navBtn(IconData icon, VoidCallback onTap) => Material(
        color: Colors.white.withOpacity(0.12),
        shape: const CircleBorder(),
        child: InkWell(customBorder: const CircleBorder(), onTap: onTap, child: Padding(padding: const EdgeInsets.all(8), child: Icon(icon, color: Colors.white, size: 22))),
      );

  void _showAdd() => _showExpenseSheet();

  void _showEdit(Map<String, dynamic> e) => _showExpenseSheet(expense: e);

  Future<void> _deleteExpense(Map e) async {
    final id = int.tryParse((e['id'] ?? '').toString());
    if (id == null) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Expense?'),
        content: Text('Delete "${e['title'] ?? 'this expense'}"? This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(apiClientProvider).deleteExpense(id);
      await _load();
      if (mounted) Toast.success(context, 'Deleted');
    } catch (err) {
      if (mounted) Toast.error(context, _friendly(err));
    }
  }

  void _showExpenseSheet({Map<String, dynamic>? expense}) {
    final editing = expense != null;
    final titleCtrl = TextEditingController(text: expense?['title']?.toString() ?? '');
    final amountCtrl = TextEditingController(text: expense != null ? (expense['amount'] ?? '').toString() : '');
    final notesCtrl = TextEditingController(text: expense?['notes']?.toString() ?? '');

    DateTime selected = _parseDate(expense?['date']) ?? _defaultExpenseDate();

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            IconBadge(editing ? Icons.edit_rounded : Icons.account_balance_wallet_rounded, color: AppTheme.danger),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(editing ? 'Edit Expense' : 'Add Expense', style: context.typo.titleLarge),
              Text('Date format: ${DateFormatter.formatDate(_dateValue(selected))}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
            ])),
          ]),
          const SizedBox(height: 18),
          TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: 'Title*', prefixIcon: Icon(Icons.receipt_long_rounded))),
          const SizedBox(height: 12),
          TextField(
            controller: amountCtrl,
            decoration: const InputDecoration(labelText: 'Amount*', prefixText: '₹ ', prefixIcon: Icon(Icons.currency_rupee_rounded)),
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
          ),
          const SizedBox(height: 12),
          _dateSelector('Expense Date', selected, () async {
            final picked = await showDatePicker(
              context: ctx,
              initialDate: selected,
              firstDate: DateTime(2020),
              lastDate: DateTime.now().add(const Duration(days: 1)),
            );
            if (picked != null) setSheet(() => selected = picked);
          }),
          const SizedBox(height: 12),
          TextField(controller: notesCtrl, decoration: const InputDecoration(labelText: 'Notes (optional)', prefixIcon: Icon(Icons.notes_rounded)), minLines: 1, maxLines: 3),
          const SizedBox(height: 20),
          FireButton(label: editing ? 'Save Changes' : 'Add Expense', icon: editing ? Icons.save_rounded : Icons.add_rounded, onPressed: () async {
            final title = titleCtrl.text.trim();
            final amount = double.tryParse(amountCtrl.text.trim()) ?? 0;
            if (title.isEmpty) { Toast.error(ctx, 'Title required'); return; }
            if (amount <= 0) { Toast.error(ctx, 'Enter valid amount'); return; }

            final data = {
              'title': title,
              'amount': amount,
              'date': _dateValue(selected),
              'notes': notesCtrl.text.trim(),
            };

            try {
              final api = ref.read(apiClientProvider);
              if (editing) {
                await api.updateExpense(int.parse(expense['id'].toString()), data);
              } else {
                await api.createExpense(data);
              }
              if (mounted) Navigator.pop(ctx);
              setState(() => month = DateTime(selected.year, selected.month));
              await _load();
              if (mounted) Toast.success(context, editing ? 'Expense updated' : 'Expense added');
            } catch (e) {
              Toast.error(ctx, _friendly(e));
            }
          }),
        ]),
      );
    }));
  }

  Widget _dateSelector(String label, DateTime value, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: InputDecorator(
        decoration: const InputDecoration(labelText: 'Expense Date', prefixIcon: Icon(Icons.calendar_today_rounded)),
        child: Row(children: [
          Expanded(child: Text(DateFormatter.formatDate(_dateValue(value)), style: context.typo.titleSmall)),
          Icon(Icons.edit_calendar_rounded, size: 19, color: context.tokens.textTertiary),
        ]),
      ),
    );
  }

  DateTime _defaultExpenseDate() {
    final now = DateTime.now();
    if (month.year == now.year && month.month == now.month) return now;
    return DateTime(month.year, month.month, 1);
  }

  DateTime? _parseDate(dynamic raw) {
    if (raw == null || raw.toString().trim().isEmpty) return null;
    try {
      final value = raw.toString().split('T').first.split(' ').first;
      return DateTime.parse(value);
    } catch (_) {
      return null;
    }
  }

  String _dateValue(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _monthName(int m) {
    const names = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    return names[m];
  }
}
