import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';
import '../providers/notices_provider.dart';

// Phase 3: staff notice actions use the shared permission provider.
class NoticesListScreen extends ConsumerStatefulWidget {
  final bool standalone;
  const NoticesListScreen({super.key, this.standalone = false});
  @override
  ConsumerState<NoticesListScreen> createState() => _NoticesListScreenState();
}

class _NoticesListScreenState extends ConsumerState<NoticesListScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(noticesProvider.notifier).load());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(noticesProvider);
    final auth = ref.watch(authProvider);
    final permissions = ref.watch(permissionProvider);
    final canCreate = permissions.can('notices.create');
    final canEdit = permissions.can('notices.edit');
    final canDelete = permissions.can('notices.delete');

    return Scaffold(
      appBar: widget.standalone ? AppBar(title: const Text('Notices')) : null,
      bottomNavigationBar: widget.standalone ? const AppBottomNav() : null,
      body: state.isLoading
          ? const SkeletonList()
          : state.error != null && state.notices.isEmpty
              ? ErrorRetry(message: 'Failed to load notices.', onRetry: () => ref.read(noticesProvider.notifier).load())
              : state.notices.isEmpty
                  ? EmptyState(
                      icon: Icons.campaign_rounded,
                      title: 'No notices',
                      subtitle: canCreate ? 'Post announcements for your members' : 'No announcements yet',
                      actionLabel: canCreate ? 'Add Notice' : null,
                      onAction: canCreate ? _showAddSheet : null,
                    )
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: () => ref.read(noticesProvider.notifier).load(),
                      child: ListView.separated(
                        padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                        itemCount: state.notices.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (ctx, i) {
                          final n = state.notices[i];
                          final color = AppTheme.categoryColors[i % AppTheme.categoryColors.length];
                          return FadeInUp(delayMs: (i * 22).clamp(0, 240), offset: 10, child: SurfaceCard(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Row(children: [
                                IconBadge(Icons.campaign_rounded, color: color, size: 38, iconSize: 18),
                                const SizedBox(width: 10),
                                Expanded(child: Text(n.title, style: context.typo.titleMedium)),
                                // Only show edit/delete for admins
                                if (canEdit || canDelete)
                                  PopupMenuButton<String>(
                                    icon: Icon(Icons.more_vert_rounded, size: 20, color: context.tokens.textTertiary),
                                    onSelected: (v) { if (v == 'edit' && canEdit) _showEditSheet(n); if (v == 'delete' && canDelete) _confirmDelete(n.id); },
                                    itemBuilder: (_) => [if (canEdit) const PopupMenuItem(value: 'edit', child: Text('Edit')), if (canDelete) const PopupMenuItem(value: 'delete', child: Text('Delete', style: TextStyle(color: AppTheme.danger)))],
                                  ),
                              ]),
                              if (n.description.isNotEmpty) ...[const SizedBox(height: 10), Text(n.description, style: context.typo.bodyMedium?.copyWith(height: 1.5))],
                              const SizedBox(height: 10),
                              Row(children: [Icon(Icons.schedule_rounded, size: 12, color: context.tokens.textTertiary), const SizedBox(width: 4), Text(n.formattedDate, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11))]),
                            ]),
                          ));
                        },
                      ),
                    ),
      // Only show FAB for admins
      floatingActionButtonLocation: canCreate ? const AboveNavFabLocation() : null,
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(onPressed: _showAddSheet, icon: const Icon(Icons.add_rounded), label: const Text('Add Notice', style: TextStyle(fontWeight: FontWeight.w700)), backgroundColor: AppTheme.brand)
          : null,
    );
  }

  void _showAddSheet() {
    final titleCtrl = TextEditingController();
    final descCtrl = TextEditingController();
    _noticeSheet('Add Notice', titleCtrl, descCtrl, 'Add', () async => ref.read(noticesProvider.notifier).add(title: titleCtrl.text.trim(), description: descCtrl.text.trim()));
  }

  void _showEditSheet(dynamic notice) {
    final titleCtrl = TextEditingController(text: notice.title);
    final descCtrl = TextEditingController(text: notice.description);
    _noticeSheet('Edit Notice', titleCtrl, descCtrl, 'Update', () async => ref.read(noticesProvider.notifier).update(id: notice.id, title: titleCtrl.text.trim(), description: descCtrl.text.trim()));
  }

  void _noticeSheet(String title, TextEditingController t, TextEditingController d, String btn, Future<bool> Function() action) {
    showAppSheet(context, child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [IconBadge(Icons.campaign_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text(title, style: context.typo.titleLarge)]),
        const SizedBox(height: 18),
        TextField(controller: t, decoration: const InputDecoration(labelText: 'Title*')),
        const SizedBox(height: 12),
        TextField(controller: d, decoration: const InputDecoration(labelText: 'Description'), maxLines: 4),
        const SizedBox(height: 20),
        FireButton(label: btn, onPressed: () async {
          if (t.text.trim().isEmpty) { Toast.error(context, 'Title required'); return; }
          final ok = await action();
          if (ok && mounted) { Navigator.pop(context); Toast.success(context, 'Saved'); }
        }),
      ]),
    ));
  }

  Future<void> _confirmDelete(int id) async {
    final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(title: const Text('Delete Notice?'), content: const Text('This cannot be undone.'), actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete'))]));
    if (ok == true) {
      final success = await ref.read(noticesProvider.notifier).delete(id);
      if (success && mounted) Toast.success(context, 'Notice deleted');
    }
  }
}
