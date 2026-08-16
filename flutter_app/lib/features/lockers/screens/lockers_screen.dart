import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';

class LockersScreen extends ConsumerStatefulWidget {
  const LockersScreen({super.key});
  @override
  ConsumerState<LockersScreen> createState() => _LockersScreenState();
}

class _LockersScreenState extends ConsumerState<LockersScreen> {
  List lockers = [];
  bool loading = true;
  String? error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getLockers();
      if (mounted) setState(() { lockers = res['lockers'] ?? []; loading = false; });
    } catch (e) { if (mounted) setState(() { error = _friendly(e); loading = false; }); }
  }

  String _friendly(dynamic e) {
    final m = e.toString();
    if (m.contains('connection') || m.contains('SocketException')) return 'No internet';
    return 'Failed to load';
  }

  int get _available => lockers.where((l) => l['available'] == 1).length;
  int get _occupied => lockers.length - _available;

  @override
  Widget build(BuildContext context) {
    final permissions = ref.watch(permissionProvider);
    final canCreate = permissions.can('lockers.create');
    final canAssign = permissions.can('lockers.assign');
    final canDelete = permissions.can('lockers.delete');
    return Scaffold(
      appBar: AppBar(title: const Text('Lockers')),
      body: loading
          ? const SkeletonList()
          : error != null
              ? ErrorRetry(message: error!, onRetry: _load)
              : lockers.isEmpty
                  ? EmptyState(icon: Icons.lock_outline_rounded, title: 'No lockers', subtitle: canCreate ? 'Add lockers for your gym' : 'No lockers available for your role', actionLabel: canCreate ? 'Add Lockers' : null, onAction: canCreate ? _showAddSheet : null)
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: _load,
                      child: Column(
                        children: [
                          // Stats bar
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                            child: Row(children: [
                              _statChip(Icons.lock_open_rounded, '$_available Available', AppTheme.success),
                              const SizedBox(width: 10),
                              _statChip(Icons.lock_rounded, '$_occupied Occupied', AppTheme.warning),
                              const Spacer(),
                              Text('${lockers.length} total', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                            ]),
                          ),
                          Expanded(
                            child: ListView.separated(
                              padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
                              itemCount: lockers.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 8),
                              itemBuilder: (ctx, i) {
                                final l = lockers[i];
                                final available = l['available'] == 1;
                                final assignedTo = l['assigned_user']?.toString() ?? '';
                                return FadeInUp(
                                  delayMs: (i * 18).clamp(0, 200),
                                  offset: 8,
                                  child: SurfaceCard(
                                    padding: const EdgeInsets.all(14),
                                    child: Row(children: [
                                      Container(
                                        width: 42, height: 42,
                                        decoration: BoxDecoration(
                                          color: (available ? AppTheme.success : AppTheme.warning).withOpacity(0.12),
                                          borderRadius: BorderRadius.circular(12),
                                        ),
                                        child: Icon(available ? Icons.lock_open_rounded : Icons.lock_rounded, size: 20, color: available ? AppTheme.success : AppTheme.warning),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                        Text('Locker #${l['id']}', style: context.typo.titleSmall),
                                        if (!available && assignedTo.isNotEmpty)
                                          Text('Assigned to $assignedTo', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                                      ])),
                                      StatusBadge(available ? 'FREE' : 'OCCUPIED', color: available ? AppTheme.success : AppTheme.warning),
                                      if (!available && canAssign) ...[
                                        const SizedBox(width: 8),
                                        IconButton(
                                          icon: const Icon(Icons.lock_open_rounded, size: 18),
                                          onPressed: () => _unassign(l),
                                          tooltip: 'Unassign',
                                        ),
                                      ] else if (available && canAssign) ...[
                                        const SizedBox(width: 8),
                                        IconButton(
                                          icon: Icon(Icons.person_add_rounded, size: 18, color: AppTheme.brand),
                                          onPressed: () => _assignToMember(l),
                                          tooltip: 'Assign to member',
                                        ),
                                      ],
                                    ]),
                                  ),
                                );
                              },
                            ),
                          ),
                        ],
                      ),
                    ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: canCreate ? FloatingActionButton.extended(
        onPressed: _showAddSheet,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Add Lockers', style: TextStyle(fontWeight: FontWeight.w700)),
        backgroundColor: AppTheme.brand,
      ) : null,
    );
  }

  Widget _statChip(IconData icon, String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      decoration: BoxDecoration(color: color.withOpacity(0.12), borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 5),
        Text(label, style: context.typo.labelMedium?.copyWith(color: color, fontWeight: FontWeight.w700)),
      ]),
    );
  }

  void _showAddSheet() {
    final countCtrl = TextEditingController(text: '1');
    showAppSheet(context, child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [IconBadge(Icons.lock_outline_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text('Add Lockers', style: context.typo.titleLarge)]),
        const SizedBox(height: 18),
        TextField(controller: countCtrl, decoration: const InputDecoration(labelText: 'Number of lockers to add', prefixIcon: Icon(Icons.add_rounded)), keyboardType: TextInputType.number),
        const SizedBox(height: 20),
        FireButton(label: 'Add', onPressed: () async {
          final count = int.tryParse(countCtrl.text) ?? 0;
          if (count <= 0) { Toast.error(context, 'Enter a valid number'); return; }
          try {
            await ref.read(apiClientProvider).createLocker({'count': count});
            if (mounted) Navigator.pop(context);
            _load();
            if (mounted) Toast.success(context, '$count locker(s) added');
          } catch (e) { Toast.error(context, 'Failed'); }
        }),
      ]),
    ));
  }

  Future<void> _assignToMember(Map l) async {
    List members = [];
    bool loadingMembers = true;
    int? selectedMember;
    String searchQuery = '';

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      Future<void> doSearch(String q) async {
        if (q.isEmpty) { setSheet(() { members = []; loadingMembers = false; }); return; }
        try {
          final res = await ref.read(apiClientProvider).searchAttendance(q);
          setSheet(() { members = res['users'] ?? []; loadingMembers = false; });
        } catch (_) { setSheet(() => loadingMembers = false); }
      }

      return Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.lock_outline_rounded, color: const Color(0xFF10B981)), const SizedBox(width: 12), Text('Assign Locker #${l['id']}', style: context.typo.titleLarge)]),
          const SizedBox(height: 18),
          TextField(
            decoration: const InputDecoration(hintText: 'Search member by name or phone...', prefixIcon: Icon(Icons.search_rounded, size: 20)),
            onChanged: (v) { searchQuery = v; doSearch(v); },
          ),
          const SizedBox(height: 12),
          if (searchQuery.isNotEmpty && members.isEmpty)
            Padding(padding: const EdgeInsets.all(16), child: Text('No members found', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)))
          else
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 200),
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: members.length,
                itemBuilder: (_, i) {
                  final m = members[i];
                  final isSelected = selectedMember == m['id'];
                  return ListTile(
                    dense: true,
                    leading: GxAvatar(name: m['name'] ?? 'M', size: 34),
                    title: Text(m['name'] ?? '', style: context.typo.titleSmall),
                    subtitle: Text(m['phone_number'] ?? '', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                    trailing: isSelected ? const Icon(Icons.check_circle_rounded, color: AppTheme.success) : null,
                    onTap: () => setSheet(() => selectedMember = m['id']),
                  );
                },
              ),
            ),
          if (selectedMember != null) ...[
            const SizedBox(height: 16),
            FireButton(
              label: 'Assign Locker #${l['id']}',
              icon: Icons.lock_rounded,
              onPressed: () async {
    try {
      final today = DateTime.now().toIso8601String().split('T')[0];
      await ref.read(apiClientProvider).assignLocker({
        'user_id': selectedMember,
        'locker_id': l['id'],
        'assign_date': today,
      });
                  if (ctx.mounted) Navigator.pop(ctx);
                  _load();
                  if (mounted) Toast.success(context, 'Locker #${l['id']} assigned');
                } catch (e) { Toast.error(ctx, 'Failed to assign'); }
              },
            ),
          ],
        ]),
      );
    }));
  }

  Future<void> _unassign(Map l) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Unassign Locker #${l['id']}?'),
        content: Text('This will free up the locker from ${l['assigned_user'] ?? 'member'}.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Unassign')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(apiClientProvider).unassignLocker({'locker_id': l['id']});
      _load();
      if (mounted) Toast.success(context, 'Locker unassigned');
    } catch (e) { if (mounted) Toast.error(context, 'Failed'); }
  }
}
