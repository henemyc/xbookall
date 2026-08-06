import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import '../providers/members_provider.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';
import 'member_detail_screen.dart';
import 'add_member_screen.dart';

class MembersListScreen extends ConsumerStatefulWidget {
  const MembersListScreen({super.key});
  @override
  ConsumerState<MembersListScreen> createState() => _MembersListScreenState();
}

class _MembersListScreenState extends ConsumerState<MembersListScreen> {
  final _searchCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollCtrl.addListener(_onScroll);
    Future.microtask(() => ref.read(membersProvider.notifier).load());
  }

  void _onScroll() {
    if (!_scrollCtrl.hasClients) return;
    final position = _scrollCtrl.position;
    if (position.pixels >= position.maxScrollExtent - 280) {
      ref.read(membersProvider.notifier).loadMore();
    }
  }

  @override
  void dispose() {
    _scrollCtrl.removeListener(_onScroll);
    _scrollCtrl.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(membersProvider);
    final perms = ref.watch(permissionProvider);
    final canCreate = perms.can('members.create');
    return Scaffold(
      body: Column(
        children: [
          // Search + filter button
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: Row(children: [
              Expanded(
                child: Container(
                  height: 50,
                  decoration: BoxDecoration(
                    color: context.tokens.surface,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: context.tokens.border),
                    boxShadow: context.subtleShadow,
                  ),
                  child: TextField(
                    controller: _searchCtrl,
                    onChanged: (v) => ref.read(membersProvider.notifier).load(search: v, status: state.statusFilter),
                    decoration: InputDecoration(
                      filled: false,
                      border: InputBorder.none,
                      enabledBorder: InputBorder.none,
                      focusedBorder: InputBorder.none,
                      contentPadding: const EdgeInsets.symmetric(vertical: 14),
                      hintText: 'Search name, phone or email…',
                      prefixIcon: const Icon(Icons.search_rounded, size: 21),
                      suffixIcon: _searchCtrl.text.isNotEmpty
                          ? IconButton(icon: const Icon(Icons.close_rounded, size: 18), onPressed: () { _searchCtrl.clear(); ref.read(membersProvider.notifier).load(status: state.statusFilter); setState(() {}); })
                          : null,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              _filterButton(state.statusFilter),
            ]),
          ),
          // Count + active filter strip
          if (!state.isLoading && state.error == null)
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
              child: Row(children: [
                Text('${state.members.length}${state.total > state.members.length ? ' of ${state.total}' : ''} members', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontWeight: FontWeight.w600)),
                const Spacer(),
                if (state.statusFilter != 'all')
                  GestureDetector(
                    onTap: () => ref.read(membersProvider.notifier).load(search: _searchCtrl.text, status: 'all'),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(color: AppTheme.brand.withOpacity(0.12), borderRadius: BorderRadius.circular(20)),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Text(_filterLabel(state.statusFilter), style: context.typo.labelMedium?.copyWith(color: AppTheme.brand, fontWeight: FontWeight.w700)),
                        const SizedBox(width: 4),
                        const Icon(Icons.close_rounded, size: 13, color: AppTheme.brand),
                      ]),
                    ),
                  ),
              ]),
            ),
          Expanded(
            child: state.isLoading
                ? const SkeletonList()
                : state.error != null
                    ? ErrorRetry(message: 'Failed to load members.', onRetry: () => ref.read(membersProvider.notifier).load(status: state.statusFilter))
                    : state.members.isEmpty
                        ? EmptyState(icon: Icons.people_outline_rounded, title: 'No members found', subtitle: canCreate ? 'Add your first member to get started' : 'No members available for your role', actionLabel: canCreate ? 'Add Member' : null, onAction: canCreate ? _addMember : null)
                        : RefreshIndicator(
                            color: AppTheme.brand,
                            onRefresh: () => ref.read(membersProvider.notifier).load(search: _searchCtrl.text, status: state.statusFilter),
                            child: ListView.separated(
                              controller: _scrollCtrl,
                              padding: EdgeInsets.fromLTRB(16, 4, 16, context.navSpace + 16),
                              itemCount: state.members.length + (state.hasMore || state.isLoadingMore ? 1 : 0),
                              separatorBuilder: (_, __) => const SizedBox(height: 10),
                              itemBuilder: (ctx, i) {
                                if (i >= state.members.length) {
                                  return Padding(
                                    padding: const EdgeInsets.symmetric(vertical: 18),
                                    child: Center(
                                      child: state.isLoadingMore
                                          ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.4))
                                          : Text('Scroll to load more', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                                    ),
                                  );
                                }
                                final m = state.members[i];
                                final c = AppTheme.expiryColors(m.daysLeft, Theme.of(context).brightness);
                                return FadeInUp(
                                  delayMs: (i * 22).clamp(0, 260),
                                  offset: 10,
                                  child: SurfaceCard(
                                    padding: const EdgeInsets.all(12),
                                    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MemberDetailScreen(memberId: m.id, memberName: m.name))),
                                    child: Row(children: [
                                      GxAvatar(name: m.name, size: 50),
                                      const SizedBox(width: 13),
                                      Expanded(
                                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                          Row(children: [
                                            Flexible(child: Text(m.name, style: context.typo.titleSmall?.copyWith(fontSize: 15), overflow: TextOverflow.ellipsis)),
                                            const SizedBox(width: 6),
                                            Icon(m.isActive ? Icons.verified_rounded : Icons.block_rounded, size: 15, color: m.isActive ? AppTheme.success : AppTheme.danger),
                                            if (m.isFrozen) ...[
                                              const SizedBox(width: 4),
                                              Icon(Icons.ac_unit_rounded, size: 13, color: AppTheme.info),
                                            ],
                                          ]),
                                          const SizedBox(height: 3),
                                          Text(m.phone.isNotEmpty ? m.phone : m.email, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                                          const SizedBox(height: 8),
                                          Wrap(spacing: 6, runSpacing: 4, children: [
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
                                              decoration: BoxDecoration(color: c.bg, borderRadius: BorderRadius.circular(8)),
                                              child: Row(mainAxisSize: MainAxisSize.min, children: [
                                                Icon(Icons.event_rounded, size: 12, color: c.fg),
                                                const SizedBox(width: 5),
                                                Text(m.expiryDate != null ? '${DateFormatter.formatDate(m.expiryDate)} • ${m.expiryLabel}' : 'No expiry', style: context.typo.bodySmall?.copyWith(fontSize: 11.5, fontWeight: FontWeight.w700, color: c.fg)),
                                              ]),
                                            ),
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                              decoration: BoxDecoration(color: m.statusColor.withOpacity(0.12), borderRadius: BorderRadius.circular(8)),
                                              child: Text(m.statusLabel, style: context.typo.bodySmall?.copyWith(fontSize: 10.5, fontWeight: FontWeight.w700, color: m.statusColor)),
                                            ),
                                          ]),
                                        ]),
                                      ),
                                      Icon(Icons.chevron_right_rounded, size: 20, color: context.tokens.textTertiary),
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
              onPressed: _addMember,
              icon: const Icon(Icons.person_add_rounded),
              label: const Text('Add Member', style: TextStyle(fontWeight: FontWeight.w700)),
              backgroundColor: AppTheme.brand,
            )
          : null,
    );
  }

  Future<void> _addMember() async {
    final created = await Navigator.push(context, MaterialPageRoute(builder: (_) => const AddMemberScreen()));
    if (created == true) ref.read(membersProvider.notifier).load(status: ref.read(membersProvider).statusFilter);
  }

  Widget _filterButton(String current) {
    final active = current != 'all';
    return Pressable(
      radius: 16,
      onTap: _showFilterSheet,
      child: Container(
        width: 50, height: 50,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          gradient: active ? AppTheme.fireGradient : null,
          color: active ? null : context.tokens.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: active ? Colors.transparent : context.tokens.border),
          boxShadow: context.subtleShadow,
        ),
        child: Icon(Icons.tune_rounded, size: 22, color: active ? Colors.white : context.tokens.textSecondary),
      ),
    );
  }

  void _showFilterSheet() {
    final current = ref.read(membersProvider).statusFilter;
    showAppSheet(context, child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [IconBadge(Icons.tune_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text('Filter Members', style: context.typo.titleLarge)]),
        const SizedBox(height: 18),
        _filterOption('All Members', 'all', Icons.groups_rounded, current),
        _filterOption('Active Only', 'active', Icons.verified_rounded, current),
        _filterOption('Expired Only', 'expired', Icons.error_outline_rounded, current),
        _filterOption('Frozen', 'frozen', Icons.ac_unit_rounded, current),
        _filterOption('Expiring in 7 Days', 'expiring_7', Icons.warning_amber_rounded, current),
        _filterOption('Expiring in 14 Days', 'expiring_14', Icons.schedule_rounded, current),
      ]),
    ));
  }

  Widget _filterOption(String label, String value, IconData icon, String current) {
    final active = value == current;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Pressable(
        radius: 16,
        onTap: () { Navigator.pop(context); ref.read(membersProvider.notifier).load(search: _searchCtrl.text, status: value); },
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: active ? AppTheme.brand.withOpacity(0.10) : context.tokens.surfaceAlt,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: active ? AppTheme.brand.withOpacity(0.4) : Colors.transparent),
          ),
          child: Row(children: [
            Icon(icon, size: 20, color: active ? AppTheme.brand : context.tokens.textSecondary),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: context.typo.titleSmall?.copyWith(color: active ? AppTheme.brand : context.tokens.text))),
            if (active) const Icon(Icons.check_circle_rounded, color: AppTheme.brand, size: 22),
          ]),
        ),
      ),
    );
  }

  String _filterLabel(String filter) {
    switch (filter) {
      case 'active':
        return 'Active Only';
      case 'expired':
        return 'Expired Only';
      case 'frozen':
        return 'Frozen';
      case 'expiring_7':
        return 'Expiring in 7 Days';
      case 'expiring_14':
        return 'Expiring in 14 Days';
      default:
        return 'All Members';
    }
  }
}
