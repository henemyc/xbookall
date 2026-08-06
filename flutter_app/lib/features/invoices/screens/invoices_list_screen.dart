import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';
import '../models/invoice.dart';
import 'invoice_detail_screen.dart';
import 'new_invoice_screen.dart';

class InvoicesListScreen extends ConsumerStatefulWidget {
  const InvoicesListScreen({super.key});
  @override
  ConsumerState<InvoicesListScreen> createState() => _InvoicesListScreenState();
}

class _InvoicesListScreenState extends ConsumerState<InvoicesListScreen> {
  List<Invoice> invoices = [];
  List<Invoice> filtered = [];
  bool loading = true;
  String? error;
  String statusFilter = 'all';
  String searchQuery = '';
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getInvoices(status: statusFilter == 'all' ? '' : statusFilter);
      final list = (res['invoices'] as List).map((e) => Invoice.fromJson(e)).toList();
      list.sort((a, b) => b.invoiceId.compareTo(a.invoiceId));
      if (mounted) {
        setState(() {
          invoices = list;
          filtered = _filterInvoices(list, searchQuery);
          loading = false;
        });
      }
    } catch (e) { if (mounted) setState(() { error = e.toString(); loading = false; }); }
  }

  List<Invoice> _filterInvoices(List<Invoice> source, String query) {
    final q = query.trim().toLowerCase();
    if (q.isEmpty) return List<Invoice>.from(source);
    return source.where((inv) {
      return inv.invoiceId.toString().contains(q) ||
          inv.id.toString().contains(q) ||
          inv.memberName.toLowerCase().contains(q) ||
          inv.status.toLowerCase().contains(q) ||
          inv.notes.toLowerCase().contains(q) ||
          inv.formattedDate.toLowerCase().contains(q);
    }).toList();
  }

  void _onSearch(String value) {
    setState(() {
      searchQuery = value;
      filtered = _filterInvoices(invoices, searchQuery);
    });
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final canCreate = ref.watch(permissionProvider).can('invoices.create');
    return Scaffold(
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: SizedBox(
              height: 36,
              child: ListView(scrollDirection: Axis.horizontal, children: [
                _chip('All', 'all', Icons.grid_view_rounded, AppTheme.brand),
                _chip('Paid', 'paid', Icons.check_circle_rounded, AppTheme.success),
                _chip('Partial', 'partial', Icons.timelapse_rounded, AppTheme.warning),
                _chip('Unpaid', 'unpaid', Icons.error_outline_rounded, AppTheme.danger),
              ]),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 6, 16, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: _onSearch,
              decoration: InputDecoration(
                hintText: 'Search invoice, member, status...',
                prefixIcon: const Icon(Icons.search_rounded, size: 20),
                suffixIcon: searchQuery.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.close_rounded, size: 18),
                        onPressed: () {
                          _searchCtrl.clear();
                          _onSearch('');
                        },
                      ),
              ),
            ),
          ),
          Padding(padding: const EdgeInsets.fromLTRB(20, 0, 20, 6), child: Row(children: [Text('${filtered.length} of ${invoices.length} invoices', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontWeight: FontWeight.w600))])),
          Expanded(
            child: loading
                ? const SkeletonList()
                : error != null
                    ? ErrorRetry(message: 'Failed to load invoices.', onRetry: _load)
                    : filtered.isEmpty
                        ? EmptyState(icon: Icons.receipt_long_rounded, title: 'No invoices', subtitle: canCreate ? 'Create your first invoice' : 'No invoices available for your role', actionLabel: canCreate ? 'New Invoice' : null, onAction: canCreate ? _newInvoice : null)
                        : RefreshIndicator(
                            color: AppTheme.brand,
                            onRefresh: _load,
                            child: ListView.separated(
                              padding: EdgeInsets.fromLTRB(16, 4, 16, context.navSpace + 16),
                              itemCount: filtered.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 10),
                              itemBuilder: (ctx, i) {
                                final inv = filtered[i];
                                final c = inv.statusColors;
                                final color = Color(c['text']);
                                return FadeInUp(delayMs: (i * 20).clamp(0, 240), offset: 10, child: SurfaceCard(
                                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InvoiceDetailScreen(invoiceDbId: inv.id))),
                                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Row(children: [
                                      Container(width: 4, height: 34, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(4))),
                                      const SizedBox(width: 12),
                                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                        Text('INV #${inv.invoiceId}', style: context.typo.titleMedium),
                                        Text(inv.formattedDate, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                                      ])),
                                      StatusBadge(c['label'], color: color),
                                    ]),
                                    const SizedBox(height: 12),
                                    Row(children: [
                                      Icon(Icons.person_rounded, size: 15, color: context.tokens.textTertiary),
                                      const SizedBox(width: 6),
                                      Expanded(child: Text(inv.memberName, style: context.typo.titleSmall?.copyWith(fontSize: 13.5), overflow: TextOverflow.ellipsis)),
                                      Icon(Icons.chevron_right_rounded, size: 18, color: context.tokens.textTertiary),
                                    ]),
                                    if (inv.notes.isNotEmpty) ...[const SizedBox(height: 6), Text(inv.notes, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5), maxLines: 1, overflow: TextOverflow.ellipsis)],
                                  ]),
                                ));
                              },
                            ),
                          ),
          ),
        ],
      ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: canCreate ? FloatingActionButton.extended(onPressed: _newInvoice, icon: const Icon(Icons.add_rounded), label: const Text('New Invoice', style: TextStyle(fontWeight: FontWeight.w700)), backgroundColor: AppTheme.brand) : null,
    );
  }

  Future<void> _newInvoice() async {
    final created = await Navigator.push(context, MaterialPageRoute(builder: (_) => const NewInvoiceScreen()));
    if (created == true) _load();
  }

  Widget _chip(String label, String value, IconData icon, Color color) {
    final active = statusFilter == value;
    return Padding(
      padding: const EdgeInsets.only(right: 9),
      child: Pressable(radius: 30, onTap: () { setState(() => statusFilter = value); _load(); }, child: AnimatedContainer(
        duration: const Duration(milliseconds: 220),
        padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 8),
        decoration: BoxDecoration(gradient: active ? AppTheme.fireGradient : null, color: active ? null : context.tokens.surface, borderRadius: BorderRadius.circular(30), border: Border.all(color: active ? Colors.transparent : context.tokens.border)),
        child: Row(children: [Icon(icon, size: 15, color: active ? Colors.white : context.tokens.textSecondary), const SizedBox(width: 6), Text(label, style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w700, color: active ? Colors.white : context.tokens.textSecondary))]),
      )),
    );
  }
}
