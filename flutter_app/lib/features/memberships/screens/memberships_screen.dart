import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';

class MembershipsScreen extends ConsumerStatefulWidget {
  const MembershipsScreen({super.key});
  @override
  ConsumerState<MembershipsScreen> createState() => _MembershipsScreenState();
}

class _MembershipsScreenState extends ConsumerState<MembershipsScreen> {
  List plans = [];
  bool loading = true;
  String? error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getMemberships();
      List all = res['memberships'] ?? [];
      if (mounted) setState(() { plans = all; loading = false; });
    } catch (e) { if (mounted) setState(() { error = _friendly(e); loading = false; }); }
  }

  String _friendly(dynamic e) {
    final m = e.toString();
    if (m.contains('connection') || m.contains('SocketException')) return 'No internet';
    if (m.contains('401')) return 'Session expired';
    return 'Failed to load';
  }

  @override
  Widget build(BuildContext context) {
    final perms = ref.watch(permissionProvider);
    final canCreate = perms.can('plans.create');
    final canEdit = perms.can('plans.edit');
    final canDelete = perms.can('plans.delete');
    return Scaffold(
      body: loading
          ? const SkeletonList()
          : error != null
              ? ErrorRetry(message: error!, onRetry: _load)
              : plans.isEmpty
                  ? EmptyState(icon: Icons.card_membership_rounded, title: 'No plans yet', subtitle: canCreate ? 'Create membership plans for your gym' : 'No plans available for your role', actionLabel: canCreate ? 'Add Plan' : null, onAction: canCreate ? _showAdd : null)
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                        itemCount: plans.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (ctx, i) {
                          final p = plans[i];
                          final color = AppTheme.categoryColors[i % AppTheme.categoryColors.length];
                          return FadeInUp(delayMs: (i * 24).clamp(0, 240), offset: 10, child: SurfaceCard(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Row(children: [
                                IconBadge(Icons.card_membership_rounded, color: color, size: 42),
                                const SizedBox(width: 12),
                                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                  Text(p['title'] ?? '', style: context.typo.titleMedium),
                                  Text('${p['package'] ?? ''} • ${p['member_count'] ?? 0} members', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                                ])),
                                Text('₹${p['amount']}', style: GoogleFonts.spaceGrotesk(fontSize: 18, fontWeight: FontWeight.w700, color: color)),
                              ]),
                              if (p['notes'] != null && p['notes'].toString().isNotEmpty) ...[const SizedBox(height: 10), Text(p['notes'], style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))],
                              if (canEdit || canDelete) ...[
                                const SizedBox(height: 14),
                                Row(children: [
                                  if (canEdit) Expanded(child: OutlinedButton.icon(onPressed: () => _showEdit(p), icon: const Icon(Icons.edit_rounded, size: 16), label: const Text('Edit'))),
                                  if (canEdit && canDelete) const SizedBox(width: 10),
                                  if (canDelete) Expanded(child: OutlinedButton.icon(style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger, side: const BorderSide(color: AppTheme.danger)), onPressed: () => _delete(p), icon: const Icon(Icons.delete_rounded, size: 16), label: const Text('Delete'))),
                                ]),
                              ],
                            ]),
                          ));
                        },
                      ),
                    ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: canCreate ? FloatingActionButton.extended(onPressed: _showAdd, icon: const Icon(Icons.add_rounded), label: const Text('Add Plan', style: TextStyle(fontWeight: FontWeight.w700)), backgroundColor: AppTheme.brand) : null,
    );
  }

  void _showAdd() {
    final titleCtrl = TextEditingController();
    final amountCtrl = TextEditingController();
    final packageCtrl = TextEditingController(text: 'monthly');
    _planSheet('Add Plan', titleCtrl, amountCtrl, packageCtrl, 'Add', () async {
      await ref.read(apiClientProvider).createMembership({
        'title': titleCtrl.text.trim(),
        'package': packageCtrl.text.trim(),
        'amount': double.tryParse(amountCtrl.text) ?? 0,
      });
    });
  }

  void _showEdit(Map p) {
    final titleCtrl = TextEditingController(text: p['title']);
    final amountCtrl = TextEditingController(text: p['amount'].toString());
    final packageCtrl = TextEditingController(text: p['package']);
    _planSheet('Edit Plan', titleCtrl, amountCtrl, packageCtrl, 'Update', () async {
      await ref.read(apiClientProvider).updateMembership(p['id'], {
        'title': titleCtrl.text.trim(),
        'package': packageCtrl.text.trim(),
        'amount': double.tryParse(amountCtrl.text) ?? 0,
      });
    });
  }

  // Package options → stored string value. Number = months for auto-expiry.
  static const _packageOptions = [
    ('weekly', 'Weekly'),
    ('monthly', 'Monthly (1 month)'),
    ('2', 'Bi-Monthly (2 months)'),
    ('quarterly', 'Quarterly (3 months)'),
    ('half yearly', 'Half-Yearly (6 months)'),
    ('yearly', 'Yearly (12 months)'),
  ];

  void _planSheet(String title, TextEditingController t, TextEditingController a, TextEditingController pk, String btn, Future<void> Function() action) {
    // Normalise the incoming package value to one of the known options.
    String pkg = pk.text.trim().toLowerCase();
    final known = _packageOptions.map((e) => e.$1).toSet();
    if (!known.contains(pkg)) {
      if (pkg.contains('year') && !pkg.contains('half')) pkg = 'yearly';
      else if (pkg.contains('half') || pkg == '6') pkg = 'half yearly';
      else if (pkg.contains('quarter') || pkg == '3') pkg = 'quarterly';
      else if (pkg == '2') pkg = '2';
      else if (pkg.contains('week')) pkg = 'weekly';
      else pkg = 'monthly';
    }
    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.card_membership_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text(title, style: context.typo.titleLarge)]),
          const SizedBox(height: 18),
          TextField(controller: t, decoration: const InputDecoration(labelText: 'Title*')),
          const SizedBox(height: 12),
          TextField(controller: a, decoration: const InputDecoration(labelText: 'Amount*', prefixText: '₹ '), keyboardType: TextInputType.number),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: pkg,
            decoration: const InputDecoration(labelText: 'Package', prefixIcon: Icon(Icons.event_repeat_rounded)),
            items: _packageOptions.map((o) => DropdownMenuItem(value: o.$1, child: Text(o.$2))).toList(),
            onChanged: (v) => setSheet(() { pkg = v ?? 'monthly'; pk.text = pkg; }),
          ),
          const SizedBox(height: 20),
          FireButton(label: btn, onPressed: () async {
            pk.text = pkg; // ensure latest selection is committed
            try { await action(); if (mounted) Navigator.pop(context); _load(); if (mounted) Toast.success(context, 'Saved'); } catch (e) { Toast.error(context, _friendly(e)); }
          }),
        ]),
      );
    }));
  }

  Future<void> _delete(Map p) async {
    final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(title: const Text('Delete Plan?'), content: const Text('If members are on this plan, deletion will fail.'), actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete'))]));
    if (ok != true) return;
    try {
      await ref.read(apiClientProvider).deleteMembership(p['id']);
      _load();
      if (mounted) Toast.success(context, 'Deleted');
    } catch (e) {
      String msg = _friendly(e);
      try { msg = (e as dynamic).response?.data['error'] ?? msg; } catch (_) {}
      if (mounted) Toast.error(context, msg);
    }
  }
}
