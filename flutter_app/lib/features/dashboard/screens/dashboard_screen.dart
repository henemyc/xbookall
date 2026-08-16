import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/nav_provider.dart';
import 'package:gymxbook/features/members/screens/member_detail_screen.dart';
import 'package:gymxbook/features/members/providers/members_provider.dart';
import 'package:gymxbook/features/members/screens/add_member_screen.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';

// Dashboard member filters support Expired Members View All.
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
    // Used only for the first load. Keeping existing content visible during a
    // pull-to-refresh avoids the old jarring switch back to generic skeletons.
    if (mounted) setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).dashboard();
      if (mounted) setState(() { data = res; loading = false; });
    } catch (e) {
      if (mounted) setState(() { error = e.toString(); loading = false; });
    }
  }

  Future<void> _refresh() async {
    try {
      final res = await ref.read(apiClientProvider).dashboard();
      if (mounted) setState(() { data = res; error = null; });
    } catch (_) {
      // The existing dashboard remains usable if a background refresh fails.
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
    if (loading) return const _DashboardSkeleton();
    if (error != null) return ErrorRetry(message: 'Could not load your dashboard.', onRetry: _load);

    final auth = ref.watch(authProvider);
    final stats = data!['stats'] ?? {};
    final showRevenueExpenseCard = data!['show_revenue_expense_card'] == true;
    final tier = auth.user?['current_tier'];
    final tierCode = tier is Map ? (tier['code'] ?? '').toString().toLowerCase() : '';
    final isBronze = tierCode == 'bronze';

    final name = (auth.user?['name'] ?? 'there').toString().split(' ').first;

    // CupertinoSliverRefreshControl expands above the list while dragging.
    // Unlike the Material overlay indicator, it physically moves the complete
    // dashboard down for the fluid, modern pull-to-refresh interaction.
    return CustomScrollView(
      physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
      slivers: [
        CupertinoSliverRefreshControl(
          onRefresh: _refresh,
          builder: (context, refreshState, pulledExtent, refreshTriggerPullDistance, refreshIndicatorExtent) {
            return Center(
              child: Opacity(
                opacity: (pulledExtent / refreshTriggerPullDistance).clamp(0.0, 1.0),
                child: const CupertinoActivityIndicator(color: AppTheme.brand),
              ),
            );
          },
        ),
        SliverPadding(
          padding: EdgeInsets.fromLTRB(16, 4, 16, context.navSpace + 16),
          sliver: SliverList(
            delegate: SliverChildListDelegate([

          // ── Hero greeting card ──────────────────────────────────
          FadeInUp(child: _heroCard(name)),
          const SizedBox(height: 18),

          // ── Stats grid (row 1) ─────────────────────────────────
          FadeInUp(
            delayMs: 60,
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    child: StatTile(
                      label: 'Total Members',
                      value: '${stats['members'] ?? 0}',
                      icon: Icons.people_rounded,
                      color: AppTheme.brand,
                      onTap: () => ref.read(navIndexProvider.notifier).state = 1,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: isBronze
                        ? StatTile(
                            label: 'Expiring This Month',
                            value: '${stats['expiring_this_month'] ?? 0}',
                            icon: Icons.event_busy_rounded,
                            color: AppTheme.warning,
                            onTap: () => ref.read(navIndexProvider.notifier).state = 1,
                          )
                        : StatTile(
                            label: 'Trainers',
                            value: '${stats['trainers'] ?? 0}',
                            icon: Icons.sports_martial_arts_rounded,
                            color: AppTheme.success,
                            onTap: () => ref.read(navIndexProvider.notifier).state = 5,
                          ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),

          // ── Stats grid (row 2) ─────────────────────────────────
          FadeInUp(
            delayMs: 90,
            child: IntrinsicHeight(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Expanded(
                    child: StatTile(
                      label: 'Present Today',
                      value: '${stats['attendance_today'] ?? 0}',
                      icon: Icons.fact_check_rounded,
                      color: AppTheme.info,
                      onTap: () => ref.read(navIndexProvider.notifier).state = 2,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: StatTile(
                      label: 'Active Members',
                      value: '${stats['active_members'] ?? stats['active_memberships'] ?? 0}',
                      icon: Icons.verified_rounded,
                      color: AppTheme.warning,
                      onTap: () => ref.read(navIndexProvider.notifier).state = 1,
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),

          // ── Revenue / Expense card ──────────────────────────────
          if (showRevenueExpenseCard) ...[
            FadeInUp(delayMs: 120, child: _financeCard(stats)),
            const SizedBox(height: 24),
          ],

          // ── Quick actions ───────────────────────────────────────
          const SectionHeader('Quick Actions'),
          const SizedBox(height: 14),
          FadeInUp(delayMs: 160, child: Row(children: [
            // FIX #2: Add Member → open AddMemberScreen directly
            _quickAction(Icons.person_add_rounded, 'Add\nMember', AppTheme.brand, () async {
              final created = await Navigator.push(context, MaterialPageRoute(builder: (_) => const AddMemberScreen()));
              if (created == true) _load(); // Refresh dashboard after adding
            }),
            _quickAction(Icons.qr_code_2_rounded, 'Gym QR', AppTheme.info, () => ref.read(navIndexProvider.notifier).state = 12),
            _quickAction(Icons.receipt_long_rounded, 'Invoice', AppTheme.success, () => ref.read(navIndexProvider.notifier).state = 13),
            _quickAction(Icons.account_balance_wallet_rounded, 'Add\nExpense', AppTheme.danger, () => ref.read(navIndexProvider.notifier).state = 8),
            _quickAction(Icons.campaign_rounded, 'Notices', const Color(0xFF8B5CF6), () => ref.read(navIndexProvider.notifier).state = 10),
          ])),
          const SizedBox(height: 24),

          // ── Recent members ──────────────────────────────────────
          SectionHeader('Recent Members', action: 'View all', onAction: () => ref.read(navIndexProvider.notifier).state = 1),
          const SizedBox(height: 12),
          ...((data!['recent_members'] as List?) ?? [])
              .take(3)
              .toList()
              .asMap()
              .entries
              .map((e) => FadeInUp(
                    delayMs: 180 + e.key * 40,
                    child: Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _memberRow(e.value),
                    ),
                  )),

          const SizedBox(height: 18),
          SectionHeader('Expired Members', action: 'View all', onAction: () { ref.read(membersProvider.notifier).load(status: 'expired'); ref.read(navIndexProvider.notifier).state = 1; }),
          const SizedBox(height: 12),
          ...((data!['expired_members'] as List?) ?? []).take(3).map((m) => Padding(padding: const EdgeInsets.only(bottom: 10), child: _memberRow(Map<String, dynamic>.from(m as Map), expired: true))),
          if (((data!['expired_members'] as List?) ?? []).isEmpty) Text('No expired members', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          const SizedBox(height: 14),
          const SectionHeader("Today's Attendance"),
          const SizedBox(height: 12),
          ...(() {
            final list = (data!['today_checkins'] as List?) ?? [];
            if (list.isEmpty) {
              return [
                SurfaceCard(
                  child: Row(children: [
                    IconBadge(Icons.event_busy_rounded, color: context.tokens.textTertiary),
                    const SizedBox(width: 12),
                    Text('No attendance yet today', style: context.typo.bodyMedium),
                  ]),
                )
              ];
            }
            return list
                .take(5)
                .toList()
                .asMap()
                .entries
                .map((e) => FadeInUp(
                      delayMs: 200 + e.key * 30,
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: _checkinRow(e.value),
                      ),
                    ))
                .toList();
          }()),
            ]),
          ),
        ),
      ],
    );
  }

  Widget _heroCard(String name) {
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
          Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8), decoration: BoxDecoration(color: Colors.white.withOpacity(0.10), borderRadius: BorderRadius.circular(14), border: Border.all(color: Colors.white.withOpacity(0.12))),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.bolt_rounded, color: AppTheme.brandAmber, size: 16),
              const SizedBox(width: 6),
              Text('Your gym is running smoothly', style: GoogleFonts.poppins(color: Colors.white, fontSize: 12.5, fontWeight: FontWeight.w600)),
            ]),
          ),
        ]),
      ]),
    );
  }

  Widget _financeCard(Map stats) {
    return SurfaceCard(
      padding: const EdgeInsets.all(18),
      child: Column(children: [
        Row(children: [
          Text('This Month', style: context.typo.titleSmall),
          const Spacer(),
          Icon(Icons.calendar_month_rounded, size: 16, color: context.tokens.textTertiary),
        ]),
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
          Center(
            child: Container(
              width: 62,
              height: 62,
              alignment: Alignment.center,
              decoration: BoxDecoration(color: color.withOpacity(0.12), borderRadius: BorderRadius.circular(16), border: Border.all(color: color.withOpacity(0.12))),
              child: Icon(icon, color: color, size: 28),
            ),
          ),
          const SizedBox(height: 7),
          SizedBox(
            height: 28,
            child: Text(label, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis, style: context.typo.bodySmall?.copyWith(fontWeight: FontWeight.w600, fontSize: 10.5, height: 1.15)),
          ),
        ]),
      ),
    );
  }

  Widget _memberRow(Map m, {bool expired = false}) {
    final memberId = int.tryParse((m['id'] ?? m['user_id'] ?? 0).toString()) ?? 0;
    return SurfaceCard(
      padding: const EdgeInsets.all(12),
      onTap: () {
        if (memberId > 0) {
          Navigator.push(context, MaterialPageRoute(builder: (_) => MemberDetailScreen(memberId: memberId, memberName: m['name'] ?? '')));
        }
      },
      child: Row(children: [
        GxAvatar(name: m['name'] ?? 'M', imageUrl: m['profile_photo_url']?.toString(), size: 44),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(m['name'] ?? '', style: context.typo.titleSmall),
          const SizedBox(height: 2),
          Text(expired
              ? '${m['plan_name'] ?? 'No Plan'} • ${_expiredAgo(m['membership_expiry_date'])}'
              : '${m['plan_name'] ?? 'No Plan'}${m['joined_at'] != null ? ' • Joined ${DateFormatter.formatDate(m['joined_at'].toString())}' : ''}',
            style: context.typo.bodySmall?.copyWith(color: expired ? AppTheme.danger : context.tokens.textTertiary)), 
        ])),
        Icon(Icons.chevron_right_rounded, size: 20, color: context.tokens.textTertiary),
      ]),
    );
  }

  String _expiredAgo(dynamic raw) {
    final date = DateTime.tryParse((raw ?? '').toString());
    if (date == null) return 'Expired';
    final days = DateTime.now().difference(DateTime(date.year, date.month, date.day)).inDays;
    return days <= 0 ? 'Expired today' : 'Expired $days ${days == 1 ? 'day' : 'days'} ago';
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
            Text('In: ${inTime.length >= 5 ? inTime.substring(0, 5) : inTime}', style: context.typo.bodySmall?.copyWith(color: AppTheme.success, fontWeight: FontWeight.w600, fontSize: 11)),
            if (outTime.isNotEmpty) ...[
              const SizedBox(width: 8),
              Text('Out: ${outTime.length >= 5 ? outTime.substring(0, 5) : outTime}${isAuto ? ' (Auto)' : ''}', style: context.typo.bodySmall?.copyWith(color: AppTheme.warning, fontWeight: FontWeight.w600, fontSize: 11)),
            ],
          ]),
        ])),
      ]),
    );
  }
}


/// Mirrors the real dashboard hierarchy so first paint feels intentional
/// instead of looking like a different screen while data is loading.
class _DashboardSkeleton extends StatelessWidget {
  const _DashboardSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const NeverScrollableScrollPhysics(),
      padding: EdgeInsets.fromLTRB(16, 4, 16, context.navSpace + 16),
      children: [
        const ShimmerBox(height: 142, radius: 26),
        const SizedBox(height: 18),
        Row(children: const [Expanded(child: ShimmerBox(height: 104, radius: 18)), SizedBox(width: 12), Expanded(child: ShimmerBox(height: 104, radius: 18))]),
        const SizedBox(height: 12),
        Row(children: const [Expanded(child: ShimmerBox(height: 104, radius: 18)), SizedBox(width: 12), Expanded(child: ShimmerBox(height: 104, radius: 18))]),
        const SizedBox(height: 12),
        const ShimmerBox(height: 104, radius: 22),
        const SizedBox(height: 25),
        const ShimmerBox(width: 120, height: 18, radius: 6),
        const SizedBox(height: 15),
        Row(children: const [Expanded(child: ShimmerBox(height: 88, radius: 18)), SizedBox(width: 12), Expanded(child: ShimmerBox(height: 88, radius: 18)), SizedBox(width: 12), Expanded(child: ShimmerBox(height: 88, radius: 18)), SizedBox(width: 12), Expanded(child: ShimmerBox(height: 88, radius: 18))]),
        const SizedBox(height: 25),
        const ShimmerBox(width: 160, height: 18, radius: 6),
        const SizedBox(height: 13),
        const ShimmerBox(height: 68, radius: 18),
        const SizedBox(height: 10),
        const ShimmerBox(height: 68, radius: 18),
      ],
    );
  }
}
