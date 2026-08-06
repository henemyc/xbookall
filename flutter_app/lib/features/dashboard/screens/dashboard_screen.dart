import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/nav_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';
import 'package:gymxbook/features/members/screens/member_detail_screen.dart';
import 'package:gymxbook/features/members/screens/add_member_screen.dart';
import 'package:gymxbook/features/members/providers/members_provider.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});
  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  Map<String, dynamic>? data;
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).dashboard();
      if (mounted) setState(() { data = res; loading = false; });
    } catch (e) {
      if (mounted) setState(() { error = e.toString(); loading = false; });
    }
  }

  String _greeting() {
    final h = DateTime.now().hour;
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const SkeletonGrid();
    if (error != null) return ErrorRetry(message: 'Could not load your dashboard.', onRetry: _load);

    final auth = ref.watch(authProvider);
    final perms = ref.watch(permissionProvider);
    final stats = data!['stats'] ?? {};
    final showRevenueExpenseCard = data!['show_revenue_expense_card'] != false;
    final gymName = (data?['gym_info'] is Map ? (data!['gym_info']['name'] ?? '') : '').toString().trim();
    final displayName = gymName.isNotEmpty ? gymName : (auth.user?['company_name'] ?? auth.user?['name'] ?? 'there').toString();
    final name = displayName.split(' ').first;
    final trainerEnabled = _planFeatureEnabled(auth.user, 'trainers_enabled');

    final statTiles = <Widget>[];
    if (perms.can('members.view')) {
      statTiles.add(StatTile(label: 'Total Members', value: '${stats['members'] ?? 0}', icon: Icons.people_rounded, color: AppTheme.brand, onTap: () => _openMembers('all')));
      statTiles.add(StatTile(label: 'Active Members', value: '${stats['active_members'] ?? stats['active_memberships'] ?? 0}', icon: Icons.verified_rounded, color: AppTheme.warning, onTap: () => _openMembers('active')));
    }
    if (trainerEnabled && perms.can('trainers.view')) {
      statTiles.add(StatTile(label: 'Trainers', value: '${stats['trainers'] ?? 0}', icon: Icons.sports_martial_arts_rounded, color: AppTheme.success, onTap: () => ref.read(navIndexProvider.notifier).state = 5));
    } else if (!trainerEnabled && perms.can('members.view')) {
      statTiles.add(StatTile(label: 'Expiring Soon', value: '${stats['expiring_members'] ?? 0}', icon: Icons.event_busy_rounded, color: AppTheme.success, onTap: () => _openMembers('expiring_7')));
    }
    if (perms.can('attendance.view')) {
      statTiles.add(StatTile(label: 'Present Today', value: '${stats['attendance_today'] ?? 0}', icon: Icons.fact_check_rounded, color: AppTheme.info, onTap: () => ref.read(navIndexProvider.notifier).state = 2));
    }

    final quickActions = <Widget>[];
    if (perms.can('members.create')) {
      quickActions.add(_quickAction(Icons.person_add_rounded, 'Add\nMember', AppTheme.brand, () async {
        final created = await Navigator.push(context, MaterialPageRoute(builder: (_) => const AddMemberScreen()));
        if (created == true) _load();
      }));
    }
    if (perms.can('attendance.qr')) {
      quickActions.add(_quickAction(Icons.qr_code_2_rounded, 'Gym QR', AppTheme.info, () => ref.read(navIndexProvider.notifier).state = 12));
    }
    if (perms.can('invoices.view')) {
      quickActions.add(_quickAction(Icons.receipt_long_rounded, 'Invoice', AppTheme.success, () => ref.read(navIndexProvider.notifier).state = 13));
    }
    if (perms.can('expenses.create')) {
      quickActions.add(_quickAction(Icons.account_balance_wallet_rounded, 'Add\nExpense', AppTheme.danger, () => ref.read(navIndexProvider.notifier).state = 8));
    }
    if (perms.can('notices.view')) {
      quickActions.add(_quickAction(Icons.campaign_rounded, 'Notices', const Color(0xFF8B5CF6), () => ref.read(navIndexProvider.notifier).state = 10));
    }

    return RefreshIndicator(
      color: AppTheme.brand,
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(16, 4, 16, context.navSpace + 16),
        children: [
          FadeInUp(child: _heroCard(name, perms)),
          const SizedBox(height: 18),

          if (statTiles.isNotEmpty) ...[
            FadeInUp(delayMs: 60, child: GridView.count(
              crossAxisCount: 2,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 10,
              crossAxisSpacing: 10,
              childAspectRatio: 1.72,
              children: statTiles,
            )),
            const SizedBox(height: 12),
          ],

          if (showRevenueExpenseCard && perms.canAny(['transactions.view', 'reports.view', 'expenses.view'])) ...[
            FadeInUp(delayMs: 120, child: _financeCard(stats)),
            const SizedBox(height: 24),
          ] else
            const SizedBox(height: 12),

          if (quickActions.isNotEmpty) ...[
            const SectionHeader('Quick Actions'),
            const SizedBox(height: 14),
            FadeInUp(delayMs: 160, child: Row(children: quickActions)),
            const SizedBox(height: 24),
          ],

          if (perms.can('members.view')) ...[
            SectionHeader('Recent Members', action: 'View all', onAction: () => ref.read(navIndexProvider.notifier).state = 1),
            const SizedBox(height: 12),
            ...((data!['recent_members'] as List?) ?? [])
                .take(3)
                .toList()
                .asMap()
                .entries
                .map((e) => FadeInUp(
                      delayMs: 180 + e.key * 40,
                      child: Padding(padding: const EdgeInsets.only(bottom: 10), child: _memberRow(e.value)),
                    )),
            const SizedBox(height: 14),
          ],

          if (perms.can('attendance.view')) ...[
            const SectionHeader("Today's Attendance"),
            const SizedBox(height: 12),
            ...(() {
              final list = (data!['today_checkins'] as List?) ?? [];
              if (list.isEmpty) {
                return [SurfaceCard(child: Row(children: [IconBadge(Icons.event_busy_rounded, color: context.tokens.textTertiary), const SizedBox(width: 12), Text('No attendance yet today', style: context.typo.bodyMedium)]))];
              }
              return list.take(5).toList().asMap().entries.map((e) => FadeInUp(delayMs: 200 + e.key * 30, child: Padding(padding: const EdgeInsets.only(bottom: 10), child: _checkinRow(e.value)))).toList();
            }()),
          ],

          if (statTiles.isEmpty && quickActions.isEmpty)
            FadeInUp(
              child: SurfaceCard(
                color: AppTheme.warning.withOpacity(0.08),
                border: Border.all(color: AppTheme.warning.withOpacity(0.18)),
                child: Row(children: [
                  IconBadge(Icons.lock_outline_rounded, color: AppTheme.warning),
                  const SizedBox(width: 12),
                  Expanded(child: Text('Your staff role has no dashboard widgets enabled. Please ask the gym owner to update your role permissions.', style: context.typo.bodySmall?.copyWith(color: AppTheme.warning, fontWeight: FontWeight.w700, height: 1.45))),
                ]),
              ),
            ),
        ],
      ),
    );
  }

  void _openMembers(String status) {
    ref.read(membersProvider.notifier).load(status: status);
    ref.read(navIndexProvider.notifier).state = 1;
  }

  bool _planFeatureEnabled(Map<String, dynamic>? user, String key) {
    final tier = user?['current_tier'];
    final features = user?['plan_features'];
    if (features is Map && features.containsKey(key)) {
      final value = features[key];
      if (value is bool) return value;
      return !['0', 'false', 'no', 'disabled', 'coming_soon'].contains(value.toString().toLowerCase());
    }
    final tierCode = tier is Map ? (tier['code'] ?? '').toString().toLowerCase() : '';
    if (tierCode == 'bronze' && key == 'trainers_enabled') return false;
    return tier == null;
  }

  String _relativeTime(dynamic raw) {
    if (raw == null) return '';
    DateTime? dt;
    try { dt = DateTime.parse(raw.toString()).toLocal(); } catch (_) {}
    if (dt == null) return '';
    final diff = DateTime.now().difference(dt);
    if (diff.inDays >= 1) return '${diff.inDays} day${diff.inDays == 1 ? '' : 's'} ago';
    if (diff.inHours >= 1) return '${diff.inHours} hour${diff.inHours == 1 ? '' : 's'} ago';
    if (diff.inMinutes >= 1) return '${diff.inMinutes} min ago';
    return 'just now';
  }

  Widget _heroCard(String name, PermissionService perms) {
    final role = ref.watch(authProvider).user?['staff_role'];
    final roleName = role is Map ? role['name']?.toString() : null;
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: AppTheme.darkHeroGradient,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.25), blurRadius: 24, offset: const Offset(0, 10), spreadRadius: -6)],
      ),
      child: Stack(children: [
        Positioned(right: -10, top: -20, child: Icon(Icons.local_fire_department_rounded, size: 120, color: AppTheme.brand.withOpacity(0.16))),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('${_greeting()},', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.7), fontSize: 13.5, fontWeight: FontWeight.w500)),
          const SizedBox(height: 2),
          Text(name, style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w700, letterSpacing: -0.5)),
          const SizedBox(height: 14),
          Wrap(spacing: 8, runSpacing: 8, children: [
            Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8), decoration: BoxDecoration(color: Colors.white.withOpacity(0.10), borderRadius: BorderRadius.circular(14), border: Border.all(color: Colors.white.withOpacity(0.12))),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.bolt_rounded, color: AppTheme.brandAmber, size: 16),
                const SizedBox(width: 6),
                Text(perms.isStaff ? 'Staff workspace' : 'Your gym is running smoothly', style: GoogleFonts.poppins(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w600)),
              ]),
            ),
            if (roleName != null && roleName.isNotEmpty)
              Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8), decoration: BoxDecoration(color: AppTheme.info.withOpacity(0.22), borderRadius: BorderRadius.circular(14), border: Border.all(color: Colors.white.withOpacity(0.12))), child: Text(roleName, style: GoogleFonts.poppins(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w700))),
          ]),
        ]),
      ]),
    );
  }

  Widget _financeCard(Map stats) {
    return SurfaceCard(
      padding: const EdgeInsets.all(18),
      child: Column(children: [
        Row(children: [Text('This Month', style: context.typo.titleSmall), const Spacer(), Icon(Icons.calendar_month_rounded, size: 16, color: context.tokens.textTertiary)]),
        const SizedBox(height: 16),
        Row(children: [
          Expanded(child: _financeCell('Revenue', '${stats['revenue'] ?? 0}', Icons.trending_up_rounded, AppTheme.success)),
          Container(width: 1, height: 42, color: context.tokens.border),
          Expanded(child: _financeCell('Expense', '${stats['expenses'] ?? 0}', Icons.trending_down_rounded, AppTheme.danger)),
        ]),
      ]),
    );
  }

  Widget _financeCell(String label, String value, IconData icon, Color color) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 6),
      child: Row(children: [
        IconBadge(icon, color: color, size: 40, iconSize: 20),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          const SizedBox(height: 2),
          CountUp(num.tryParse(value.replaceAll(',', '')) ?? 0, prefix: '₹', style: context.typo.titleLarge?.copyWith(fontFamily: GoogleFonts.spaceGrotesk().fontFamily, fontWeight: FontWeight.w700)),
        ])),
      ]),
    );
  }

  Widget _quickAction(IconData icon, String label, Color color, VoidCallback onTap) {
    return Expanded(
      child: Pressable(
        onTap: onTap,
        radius: 20,
        child: Column(children: [
          Center(child: Container(width: 62, height: 62, alignment: Alignment.center, decoration: BoxDecoration(color: color.withOpacity(0.12), borderRadius: BorderRadius.circular(16), border: Border.all(color: color.withOpacity(0.12))), child: Icon(icon, color: color, size: 28))),
          const SizedBox(height: 7),
          SizedBox(height: 28, child: Text(label, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.typo.bodySmall?.copyWith(fontWeight: FontWeight.w600, fontSize: 10.5, height: 1.15))),
        ]),
      ),
    );
  }

  Widget _memberRow(Map m) {
    final memberId = int.tryParse((m['id'] ?? m['user_id'] ?? 0).toString()) ?? 0;
    return SurfaceCard(
      padding: const EdgeInsets.all(12),
      onTap: () {
        if (memberId > 0) Navigator.push(context, MaterialPageRoute(builder: (_) => MemberDetailScreen(memberId: memberId, memberName: m['name'] ?? '')));
      },
      child: Row(children: [
        GxAvatar(name: m['name'] ?? 'M', size: 44),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(m['name'] ?? '', style: context.typo.titleSmall),
          const SizedBox(height: 2),
          Text('${m['plan_name'] ?? 'No Plan'}${_relativeTime(m['joined_at'] ?? m['created_at']).isNotEmpty ? ' • Joined ${_relativeTime(m['joined_at'] ?? m['created_at'])}' : ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
        ])),
        Icon(Icons.chevron_right_rounded, size: 20, color: context.tokens.textTertiary),
      ]),
    );
  }

  Widget _checkinRow(Map c) {
    final inTime = (c['checked_in_time'] ?? '').toString();
    final outTime = (c['checked_out_time'] ?? '').toString();
    final isAuto = (c['notes'] ?? '').toString().contains('Auto checkout');
    return SurfaceCard(
      padding: const EdgeInsets.all(12),
      child: Row(children: [
        IconBadge(Icons.check_circle_rounded, color: AppTheme.success, size: 38, iconSize: 18),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(c['name'] ?? '', style: context.typo.titleSmall),
          const SizedBox(height: 2),
          Row(children: [
            Text('In: ${DateFormatter.formatTime(inTime)}', style: context.typo.bodySmall?.copyWith(color: AppTheme.success, fontWeight: FontWeight.w600, fontSize: 11)),
            if (outTime.isNotEmpty) ...[
              const SizedBox(width: 8),
              Text('Out: ${DateFormatter.formatTime(outTime)}${isAuto ? ' (Auto)' : ''}', style: context.typo.bodySmall?.copyWith(color: AppTheme.warning, fontWeight: FontWeight.w600, fontSize: 11)),
            ],
          ]),
        ])),
      ]),
    );
  }
}
