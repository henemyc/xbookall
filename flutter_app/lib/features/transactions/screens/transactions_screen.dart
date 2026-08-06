import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/features/invoices/screens/invoice_detail_screen.dart';

class TransactionsScreen extends ConsumerStatefulWidget {
  const TransactionsScreen({super.key});
  @override
  ConsumerState<TransactionsScreen> createState() => _TransactionsScreenState();
}

class _TransactionsScreenState extends ConsumerState<TransactionsScreen> {
  DateTime selectedMonth = DateTime.now();
  List transactions = [];
  double totalIncome = 0;
  double totalExpense = 0;
  bool loading = true;
  String? error;
  bool _headerExpanded = true; // net-balance row collapses on scroll

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getTransactions(month: selectedMonth.month, year: selectedMonth.year);
      List txns = res['transactions'] ?? [];
      txns.sort((a, b) {
        final dateCmp = (b['date'] ?? '').compareTo(a['date'] ?? '');
        if (dateCmp != 0) return dateCmp;
        final timeCmp = (b['sort_time'] ?? '').compareTo(a['sort_time'] ?? '');
        if (timeCmp != 0) return timeCmp;
        return (b['invoice_id'] ?? 0).toString().compareTo((a['invoice_id'] ?? 0).toString());
      });
      if (mounted) setState(() {
        transactions = txns;
        totalIncome = double.tryParse(res['total_income'].toString()) ?? 0;
        totalExpense = double.tryParse(res['total_expense'].toString()) ?? 0;
        loading = false;
      });
    } catch (e) { if (mounted) setState(() { error = e.toString(); loading = false; }); }
  }

  /// Group transactions into ordered date sections (already sorted desc).
  List<MapEntry<String, List>> _grouped() {
    final map = <String, List>{};
    for (final t in transactions) {
      final key = (t['date'] ?? '').toString();
      map.putIfAbsent(key, () => []).add(t);
    }
    return map.entries.toList();
  }

  bool _onScroll(ScrollNotification n) {
    if (n is ScrollUpdateNotification && n.scrollDelta != null) {
      final scrollingDown = n.scrollDelta! > 2;
      final scrollingUp = n.scrollDelta! < -2;
      if (scrollingDown && _headerExpanded) setState(() => _headerExpanded = false);
      if ((scrollingUp || n.metrics.pixels <= 0) && !_headerExpanded) setState(() => _headerExpanded = true);
    }
    return false;
  }

  @override
  Widget build(BuildContext context) {
    final isCurrentMonth = selectedMonth.month == DateTime.now().month && selectedMonth.year == DateTime.now().year;
    final net = totalIncome - totalExpense;
    final groups = _grouped();
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
                  _navBtn(Icons.chevron_left_rounded, () { setState(() => selectedMonth = DateTime(selectedMonth.year, selectedMonth.month - 1)); _load(); }),
                  Text('${_monthName(selectedMonth.month)} ${selectedMonth.year}', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 18)),
                  _navBtn(Icons.chevron_right_rounded, isCurrentMonth ? null : () { setState(() => selectedMonth = DateTime(selectedMonth.year, selectedMonth.month + 1)); _load(); }),
                ]),
                const SizedBox(height: 16),
                // Income/Expense + Net balance collapse together on scroll.
                AnimatedSize(
                  duration: const Duration(milliseconds: 260),
                  curve: Curves.easeInOut,
                  child: _headerExpanded
                      ? Column(children: [
                          Row(children: [
                            Expanded(child: _glassCell('Income', totalIncome, AppTheme.success, Icons.arrow_downward_rounded)),
                            const SizedBox(width: 12),
                            Expanded(child: _glassCell('Expense', totalExpense, AppTheme.danger, Icons.arrow_upward_rounded)),
                          ]),
                          const SizedBox(height: 12),
                          Container(
                            padding: const EdgeInsets.all(13),
                            decoration: BoxDecoration(color: (net >= 0 ? AppTheme.success : AppTheme.danger).withOpacity(0.18), borderRadius: BorderRadius.circular(14)),
                            child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                              Text('Net Balance', style: GoogleFonts.poppins(fontWeight: FontWeight.w700, color: Colors.white, fontSize: 13.5)),
                              Text('₹${net.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w700, fontSize: 18, color: net >= 0 ? AppTheme.success : AppTheme.danger)),
                            ]),
                          ),
                        ])
                      : Row(children: [
                          Expanded(child: Text('Income: ₹${totalIncome.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(color: AppTheme.success, fontWeight: FontWeight.w600, fontSize: 13))),
                          Expanded(child: Text('Expense: ₹${totalExpense.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(color: AppTheme.danger, fontWeight: FontWeight.w600, fontSize: 13))),
                          Text('Net: ₹${net.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(color: net >= 0 ? AppTheme.success : AppTheme.danger, fontWeight: FontWeight.w700, fontSize: 13)),
                        ]),
                ),
              ]),
            ),
          ),
          Expanded(
            child: loading
                ? const SkeletonList()
                : error != null
                    ? ErrorRetry(message: 'Failed to load transactions.', onRetry: _load)
                    : transactions.isEmpty
                        ? const EmptyState(icon: Icons.receipt_long_rounded, title: 'No transactions this month', subtitle: 'Income and expenses will appear here')
                        : RefreshIndicator(
                            color: AppTheme.brand,
                            onRefresh: _load,
                            child: NotificationListener<ScrollNotification>(
                              onNotification: _onScroll,
                              child: ListView.builder(
                                padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                                itemCount: groups.length,
                                itemBuilder: (ctx, gi) {
                                  final group = groups[gi];
                                  final dayTotal = group.value.fold<double>(0, (s, t) => s + ((t['type'] == 'income' ? 1 : -1) * (double.tryParse(t['amount'].toString()) ?? 0)));
                                  return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    // Date section header
                                    Padding(
                                      padding: EdgeInsets.only(top: gi == 0 ? 0 : 18, bottom: 8),
                                      child: Row(children: [
                                        Text(DateFormatter.formatDate(group.key), style: context.typo.titleSmall?.copyWith(fontSize: 13)),
                                        const SizedBox(width: 8),
                                        Expanded(child: Container(height: 1, color: context.tokens.border)),
                                        const SizedBox(width: 8),
                                        Text('${dayTotal >= 0 ? '+' : '-'}₹${dayTotal.abs().toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontSize: 12.5, fontWeight: FontWeight.w700, color: dayTotal >= 0 ? AppTheme.success : AppTheme.danger)),
                                      ]),
                                    ),
                                    ...group.value.map((t) => Padding(padding: const EdgeInsets.only(bottom: 10), child: _txnCard(t))),
                                  ]);
                                },
                              ),
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  Widget _txnCard(Map t) {
    final isIncome = t['type'] == 'income';
    final color = isIncome ? AppTheme.success : AppTheme.danger;
    // Expense = up arrow, Income = down arrow (per request).
    final icon = isIncome ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded;
    final invoiceDbId = int.tryParse((t['invoice_db_id'] ?? 0).toString()) ?? 0;
    return SurfaceCard(
      padding: const EdgeInsets.all(12),
      onTap: invoiceDbId > 0 ? () => Navigator.push(context, MaterialPageRoute(builder: (_) => InvoiceDetailScreen(invoiceDbId: invoiceDbId))) : null,
      child: Row(children: [
        IconBadge(icon, color: color, size: 42),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(t['description'] ?? '', style: context.typo.titleSmall, maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 2),
          Text('${(t['member_name'] ?? '') != '' ? '${t['member_name']} • ' : ''}${(t['method'] ?? '') != '' ? t['method'].toString().toUpperCase() : (isIncome ? 'Income' : 'Expense')}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5), maxLines: 1, overflow: TextOverflow.ellipsis),
        ])),
        const SizedBox(width: 8),
        Text('${isIncome ? '+' : '-'}₹${double.tryParse(t['amount'].toString())?.toStringAsFixed(0) ?? t['amount']}', style: GoogleFonts.spaceGrotesk(fontSize: 15, fontWeight: FontWeight.w700, color: color)),
      ]),
    );
  }

  Widget _navBtn(IconData icon, VoidCallback? onTap) => Material(color: Colors.white.withOpacity(onTap == null ? 0.04 : 0.12), shape: const CircleBorder(), child: InkWell(customBorder: const CircleBorder(), onTap: onTap, child: Padding(padding: const EdgeInsets.all(8), child: Icon(icon, color: Colors.white.withOpacity(onTap == null ? 0.3 : 1), size: 22))));

  Widget _glassCell(String label, double amount, Color color, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: Colors.white.withOpacity(0.08), borderRadius: BorderRadius.circular(16), border: Border.all(color: Colors.white.withOpacity(0.10))),
      child: Row(children: [
        IconBadge(icon, color: color, size: 38, iconSize: 18),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('₹${amount.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white), overflow: TextOverflow.ellipsis),
          Text(label, style: GoogleFonts.poppins(fontSize: 11, color: Colors.white.withOpacity(0.6))),
        ])),
      ]),
    );
  }

  String _monthName(int month) {
    const months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    return months[month];
  }
}
