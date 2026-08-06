import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import '../providers/notifications_provider.dart';

class NotificationsScreen extends ConsumerStatefulWidget {
  final bool standalone;
  const NotificationsScreen({super.key, this.standalone = false});
  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    // Refresh when the page opens, but provider will not show the
    // blocking skeleton after the first load. This prevents flickering when
    // there are zero notifications.
    Future.microtask(() => ref.read(notificationsProvider.notifier).load(force: true));
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(notificationsProvider);
    return Scaffold(
      appBar: widget.standalone ? AppBar(title: const Text('Notifications')) : null,
      bottomNavigationBar: widget.standalone ? const AppBottomNav() : null,
      body: Column(
        children: [
          if (state.notifications.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 12, 4),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('${state.notifications.length} notifications', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontWeight: FontWeight.w600)),
                TextButton.icon(
                  onPressed: () async {
                    final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(title: const Text('Clear All?'), content: const Text('Delete all notifications?'), actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Clear All'))]));
                    if (ok == true) {
                      final success = await ref.read(notificationsProvider.notifier).clearAll();
                      if (success && mounted) Toast.success(context, 'All cleared');
                    }
                  },
                  icon: const Icon(Icons.delete_sweep_rounded, size: 18, color: AppTheme.danger),
                  label: const Text('Clear All', style: TextStyle(color: AppTheme.danger)),
                ),
              ]),
            ),
          Expanded(
            child: state.isLoading && !state.hasLoaded
                ? const SkeletonList()
                : state.error != null && state.notifications.isEmpty
                    ? ErrorRetry(message: 'Failed to load notifications.', onRetry: () => ref.read(notificationsProvider.notifier).load())
                    : state.notifications.isEmpty
                        ? const EmptyState(icon: Icons.notifications_none_rounded, title: 'No new notifications', subtitle: "You're all caught up!")
                        : RefreshIndicator(
                            color: AppTheme.brand,
                            onRefresh: () => ref.read(notificationsProvider.notifier).load(force: true),
                            child: ListView.separated(
                              padding: EdgeInsets.fromLTRB(16, 4, 16, context.navSpace + 16),
                              itemCount: state.notifications.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 10),
                              itemBuilder: (ctx, i) {
                                final n = state.notifications[i];
                                return Dismissible(
                                  key: ValueKey(n.id),
                                  direction: DismissDirection.endToStart,
                                  background: Container(padding: const EdgeInsets.only(right: 22), alignment: Alignment.centerRight, decoration: BoxDecoration(color: AppTheme.danger, borderRadius: BorderRadius.circular(20)), child: const Icon(Icons.delete_rounded, color: Colors.white)),
                                  onDismissed: (_) => ref.read(notificationsProvider.notifier).deleteOne(n.id),
                                  child: FadeInUp(delayMs: (i * 18).clamp(0, 200), offset: 10, child: SurfaceCard(
                                    padding: const EdgeInsets.all(12),
                                    onTap: () async {
                                      await ref.read(notificationsProvider.notifier).markAsRead(n.id);
                                      if (mounted) Toast.success(context, 'Marked as read');
                                    },
                                    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                      IconBadge(n.icon, color: n.color, size: 42),
                                      const SizedBox(width: 12),
                                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                        Text(n.title, style: context.typo.titleSmall),
                                        const SizedBox(height: 3),
                                        Text(n.message, style: context.typo.bodyMedium?.copyWith(height: 1.4)),
                                        const SizedBox(height: 6),
                                        Text(n.formattedDate, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 10.5)),
                                      ])),
                                    ]),
                                  )),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}
