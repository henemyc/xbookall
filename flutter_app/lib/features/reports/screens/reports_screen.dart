import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/nav_provider.dart';
import 'package:gymxbook/features/members/providers/members_provider.dart';
import 'package:gymxbook/features/members/screens/member_detail_screen.dart';

class ReportsScreen extends ConsumerStatefulWidget {
  const ReportsScreen({super.key});
  @override
  ConsumerState<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends ConsumerState<ReportsScreen> {
  Map<String, dynamic>? reports;
  bool loadingReports = true;

  DateTime calendarMonth = DateTime.now();
  List calendarData = [];
  Map<String, dynamic> countsMap = {};
  bool loadingCalendar = true;

  @override
  void initState() {
    super.initState();
    _loadReports();
    _loadCalendar();
  }

  Future<void> _loadReports() async {
    setState(() => loadingReports = true);
    try {
      final res = await ref.read(apiClientProvider).getReports();
      if (mounted) setState(() { reports = res; loadingReports = false; });
    } catch (e) { if (mounted) setState(() => loadingReports = false); }
  }

  Future<void> _loadCalendar() async {
    setState(() => loadingCalendar = true);
    try {
      final res = await ref.read(apiClientProvider).getAttendanceCalendar(month: calendarMonth.month, year: calendarMonth.year);
      if (mounted) setState(() {
        calendarData = res['calendar'] ?? [];
        countsMap = res['counts_map'] ?? {};
        loadingCalendar = false;
      });
    } catch (e) { if (mounted) setState(() => loadingCalendar = false); }
  }

  void _changeCalendar(int delta) {
    setState(() => calendarMonth = DateTime(calendarMonth.year, calendarMonth.month + delta));
    _loadCalendar();
  }

  void _openAttendanceForDate(String dateStr) {
    final day = calendarData.cast<dynamic>().firstWhere(
      (d) => (d is Map && d['date'] == dateStr),
      orElse: () => null,
    );
    final present = day is Map ? (day['present_count'] ?? day['present'] ?? countsMap[dateStr] ?? 0) : (countsMap[dateStr] ?? 0);
    Toast.info(context, '$present member${present.toString() == '1' ? '' : 's'} present on ${DateFormatter.formatDate(dateStr)}');
  }

  /// Navigate to members tab with filter using nav provider
  void _goToMembers(String status) {
    ref.read(membersProvider.notifier).load(status: status);
    ref.read(navIndexProvider.notifier).state = 1; // Members tab
  }

  void _openMemberDetail(dynamic raw) {
    if (raw is! Map) return;
    final member = Map<String, dynamic>.from(raw);
    final id = int.tryParse((member['id'] ?? member['user_id'] ?? 0).toString()) ?? 0;
    if (id <= 0) return;
    Navigator.push(context, MaterialPageRoute(builder: (_) => MemberDetailScreen(memberId: id, memberName: (member['name'] ?? 'Member').toString())));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: loadingReports
          ? const SkeletonGrid()
          : RefreshIndicator(
              color: AppTheme.brand,
              onRefresh: () async { await _loadReports(); await _loadCalendar(); },
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                children: [
                  FadeInUp(child: _calendarCard()),
                  const SizedBox(height: 20),
                  if (reports != null) ...[
                    // ── Stats Grid (4 cards) ────────────────────────
                    FadeInUp(delayMs: 60, child: GridView.count(
                      crossAxisCount: 2, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(),
                      mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 1.35,
                      children: [
                        StatTile(label: 'Monthly Income', value: '₹${reports!['monthly_income'] ?? 0}', icon: Icons.trending_up_rounded, color: AppTheme.success),
                        StatTile(label: 'Monthly Expense', value: '₹${reports!['monthly_expense'] ?? 0}', icon: Icons.trending_down_rounded, color: AppTheme.danger),
                        // FIX #4: Active Members → tap opens active members
                        StatTile(label: 'Active Members', value: '${reports!['active_count'] ?? 0}', icon: Icons.verified_rounded, color: AppTheme.brand, onTap: () => _goToMembers('active')),
                        // FIX #4: Expired → tap opens expired members
                        StatTile(label: 'Expired', value: '${reports!['expired_count'] ?? 0}', icon: Icons.warning_amber_rounded, color: AppTheme.warning, onTap: () => _goToMembers('expired')),
                      ],
                    )),
                    const SizedBox(height: 12),

                    // FIX #5: New cards row — New This Month + Upcoming Payment
                    FadeInUp(delayMs: 80, child: IntrinsicHeight(child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      Expanded(child: _newThisMonthCard()),
                      const SizedBox(width: 12),
                      Expanded(child: _upcomingPaymentCard()),
                    ]))),
                    const SizedBox(height: 24),

                    // ── Attendance Chart ────────────────────────────
                    if ((reports!['attendance_chart'] as List).isNotEmpty) ...[
                      const SectionHeader('Last 7 Days Attendance'),
                      const SizedBox(height: 12),
                      FadeInUp(delayMs: 100, child: _chartCard()),
                      const SizedBox(height: 24),
                    ],

                    // ── Expiring in 7 Days ─────────────────────────
                    _reportListSection('Expiring in 7 Days', reports!['expiring_7days'] as List, AppTheme.warning, Icons.warning_amber_rounded),
                    const SizedBox(height: 14),

                    // ── Expired Members ────────────────────────────
                    _reportListSection('Expired Members', reports!['expired'] as List, AppTheme.danger, Icons.block_rounded, onViewAll: () => _goToMembers('expired')),
                    const SizedBox(height: 14),

                    // ── New This Month ─────────────────────────────
                    _reportListSection('New This Month', reports!['new_members'] as List, AppTheme.success, Icons.person_add_rounded),
                    const SizedBox(height: 20),

                    // ── Plan Distribution ──────────────────────────
                    if ((reports!['plan_distribution'] as List).isNotEmpty) ...[
                      const SectionHeader('Plan Distribution'),
                      const SizedBox(height: 12),
                      ...(reports!['plan_distribution'] as List).map((plan) => Padding(padding: const EdgeInsets.only(bottom: 10), child: SurfaceCard(
                        padding: const EdgeInsets.all(14),
                        onTap: () => _showPlanMembersSheet(Map<String, dynamic>.from(plan as Map)),
                        child: Row(children: [
                          IconBadge(Icons.card_membership_rounded, color: AppTheme.brand, size: 40),
                          const SizedBox(width: 12),
                          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(plan['title'] ?? '', style: context.typo.titleSmall),
                            Text('₹${plan['amount']} • ${plan['package']} • tap to view members', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                          ])),
                          StatusBadge('${plan['member_count'] ?? 0}', color: AppTheme.brand),
                          const SizedBox(width: 4),
                          Icon(Icons.chevron_right_rounded, size: 18, color: context.tokens.textTertiary),
                        ]),
                      ))),
                    ],
                  ],
                ],
              ),
            ),
    );
  }

  // ═══════════════════════════════════════════════════════════════
  // FIX #5: "New This Month" card
  // ═══════════════════════════════════════════════════════════════
  Widget _newThisMonthCard() {
    final count = (reports!['new_members'] as List?)?.length ?? 0;
    return SurfaceCard(
      onTap: () => _showNewThisMonthSheet(),
      padding: const EdgeInsets.all(14),
      radius: 22,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(children: [
            Container(
              width: 40, height: 40,
              decoration: BoxDecoration(color: AppTheme.success.withOpacity(0.14), borderRadius: BorderRadius.circular(13)),
              child: const Icon(Icons.person_add_rounded, color: AppTheme.success, size: 21),
            ),
            const Spacer(),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
              decoration: BoxDecoration(color: AppTheme.success.withOpacity(0.12), borderRadius: BorderRadius.circular(20)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.arrow_upward_rounded, size: 12, color: AppTheme.success),
                Text('New', style: GoogleFonts.poppins(fontSize: 10, fontWeight: FontWeight.w700, color: AppTheme.success)),
              ]),
            ),
          ]),
          const SizedBox(height: 8),
          Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            Text('$count', maxLines: 1, overflow: TextOverflow.ellipsis, style: GoogleFonts.spaceGrotesk(fontSize: 24, fontWeight: FontWeight.w800, height: 1, color: context.tokens.text)),
            const SizedBox(height: 3),
            Text('New This Month', maxLines: 1, overflow: TextOverflow.ellipsis, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontWeight: FontWeight.w500, fontSize: 12)),
          ]),
        ],
      ),
    );
  }

  // FIX #3: Bottom sheet with proper height (70% of screen)
  void _showNewThisMonthSheet() {
    final list = (reports!['new_members'] as List?) ?? [];
    final screenHeight = MediaQuery.of(context).size.height;
    final sheetHeight = screenHeight * 0.7; // 70% of screen

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        height: sheetHeight,
        decoration: BoxDecoration(
          color: Theme.of(ctx).colorScheme.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
          border: Border(top: BorderSide(color: Theme.of(ctx).dividerColor)),
        ),
        child: Column(children: [
          // Handle
          Container(margin: const EdgeInsets.only(top: 12, bottom: 8), width: 44, height: 5, decoration: BoxDecoration(color: Theme.of(ctx).dividerColor, borderRadius: BorderRadius.circular(10))),
          // Header
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
            child: Row(children: [
              IconBadge(Icons.person_add_rounded, color: AppTheme.success),
              const SizedBox(width: 12),
              Expanded(child: Text('New Members This Month', style: context.typo.titleLarge)),
              StatusBadge('${list.length}', color: AppTheme.success),
            ]),
          ),
          const Divider(height: 1),
          // List
          Expanded(
            child: list.isEmpty
                ? Center(child: Padding(padding: const EdgeInsets.all(20), child: Text('No new members this month', style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary))))
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                    itemCount: list.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (_, i) {
                      final m = list[i];
                      return SurfaceCard(
                        padding: const EdgeInsets.all(11),
                        onTap: () {
                          Navigator.pop(ctx);
                          _openMemberDetail(m);
                        },
                        child: Row(children: [
                          GxAvatar(name: m['name'] ?? 'M', size: 40),
                          const SizedBox(width: 11),
                          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(m['name'] ?? '', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
                            Text('Plan: ${m['plan_name'] ?? 'No Plan'}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11)),
                            Text('Exp: ${DateFormatter.formatDate(m['membership_expiry_date'])} • Joined: ${DateFormatter.formatDate(m['created_at'] ?? m['membership_start_date'])}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11)),
                          ])),
                          Icon(Icons.chevron_right_rounded, size: 18, color: context.tokens.textTertiary),
                        ]),
                      );
                    },
                  ),
          ),
        ]),
      ),
    );
  }

  // ═══════════════════════════════════════════════════════════════
  // FIX #5: "Upcoming Payment" card
  // ═══════════════════════════════════════════════════════════════
  Widget _upcomingPaymentCard() {
    final expiringList = (reports!['expiring_7days'] as List?) ?? [];
    final count = expiringList.length;
    double totalRenewal = 0;
    for (var m in expiringList) {
      totalRenewal += double.tryParse((m['plan_amount'] ?? m['amount'] ?? 0).toString()) ?? 0;
    }

    return SurfaceCard(
      onTap: () => _showUpcomingPaymentSheet(),
      padding: const EdgeInsets.all(14),
      radius: 22,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(children: [
            Container(
              width: 40, height: 40,
              decoration: BoxDecoration(color: AppTheme.warning.withOpacity(0.14), borderRadius: BorderRadius.circular(13)),
              child: const Icon(Icons.payment_rounded, color: AppTheme.warning, size: 21),
            ),
            const Spacer(),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
              decoration: BoxDecoration(color: AppTheme.warning.withOpacity(0.12), borderRadius: BorderRadius.circular(20)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.schedule_rounded, size: 12, color: AppTheme.warning),
                Text('7d', style: GoogleFonts.poppins(fontSize: 10, fontWeight: FontWeight.w700, color: AppTheme.warning)),
              ]),
            ),
          ]),
          const SizedBox(height: 8),
          Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            Text('₹${totalRenewal.toStringAsFixed(0)}', maxLines: 1, overflow: TextOverflow.ellipsis, style: GoogleFonts.spaceGrotesk(fontSize: 24, fontWeight: FontWeight.w800, height: 1, color: context.tokens.text)),
            const SizedBox(height: 3),
            Text('Upcoming ($count)', maxLines: 1, overflow: TextOverflow.ellipsis, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontWeight: FontWeight.w500, fontSize: 12)),
          ]),
        ],
      ),
    );
  }

  // FIX #3: Bottom sheet with proper height (70% of screen)
  void _showUpcomingPaymentSheet() {
    final expiringList = (reports!['expiring_7days'] as List?) ?? [];
    double totalRenewal = 0;
    for (var m in expiringList) {
      totalRenewal += double.tryParse((m['plan_amount'] ?? m['amount'] ?? 0).toString()) ?? 0;
    }
    final screenHeight = MediaQuery.of(context).size.height;
    final sheetHeight = screenHeight * 0.7;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        height: sheetHeight,
        decoration: BoxDecoration(
          color: Theme.of(ctx).colorScheme.surface,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
          border: Border(top: BorderSide(color: Theme.of(ctx).dividerColor)),
        ),
        child: Column(children: [
          // Handle
          Container(margin: const EdgeInsets.only(top: 12, bottom: 8), width: 44, height: 5, decoration: BoxDecoration(color: Theme.of(ctx).dividerColor, borderRadius: BorderRadius.circular(10))),
          // Header
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
            child: Row(children: [
              IconBadge(Icons.payment_rounded, color: AppTheme.warning),
              const SizedBox(width: 12),
              Expanded(child: Text('Upcoming Payments', style: context.typo.titleLarge)),
            ]),
          ),
          // Summary card
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(16)),
              child: Row(children: [
                const Icon(Icons.account_balance_wallet_rounded, color: AppTheme.brandAmber, size: 20),
                const SizedBox(width: 10),
                Text('Total Renewal', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.7), fontSize: 12)),
                const Spacer(),
                Text('₹${totalRenewal.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700)),
              ]),
            ),
          ),
          const Divider(height: 1),
          // List
          Expanded(
            child: expiringList.isEmpty
                ? Center(child: Padding(padding: const EdgeInsets.all(20), child: Text('No payments due in 7 days', style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary))))
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                    itemCount: expiringList.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (_, i) {
                      final m = expiringList[i];
                      final amount = double.tryParse((m['plan_amount'] ?? m['amount'] ?? 0).toString()) ?? 0;
                      return SurfaceCard(
                        padding: const EdgeInsets.all(11),
                        onTap: () {
                          Navigator.pop(ctx);
                          _openMemberDetail(m);
                        },
                        child: Row(children: [
                          GxAvatar(name: m['name'] ?? 'M', size: 40),
                          const SizedBox(width: 11),
                          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(m['name'] ?? '', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
                            Text('Exp: ${DateFormatter.formatDate(m['membership_expiry_date'])} • ${m['plan_name'] ?? ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11)),
                          ])),
                          Text('₹${amount.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontSize: 14, fontWeight: FontWeight.w700, color: AppTheme.warning)),
                          const SizedBox(width: 4),
                          Icon(Icons.chevron_right_rounded, size: 18, color: context.tokens.textTertiary),
                        ]),
                      );
                    },
                  ),
          ),
        ]),
      ),
    );
  }

  void _showPlanMembersSheet(Map<String, dynamic> plan) {
    final list = (plan['members'] as List?) ?? [];
    showAppSheet(context, child: SizedBox(
      height: MediaQuery.of(context).size.height * 0.70,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
          child: Row(children: [
            IconBadge(Icons.card_membership_rounded, color: AppTheme.brand),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text((plan['title'] ?? 'Plan Members').toString(), style: context.typo.titleLarge),
              Text('${list.length} member${list.length == 1 ? '' : 's'}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
            ])),
          ]),
        ),
        const Divider(height: 1),
        Expanded(
          child: list.isEmpty
              ? Center(child: Text('No members in this plan', style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary)))
              : ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                  itemCount: list.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (_, i) {
                    final member = list[i];
                    return SurfaceCard(
                      padding: const EdgeInsets.all(11),
                      onTap: () {
                        Navigator.pop(context);
                        _openMemberDetail(member);
                      },
                      child: Row(children: [
                        GxAvatar(name: member['name'] ?? 'M', size: 40),
                        const SizedBox(width: 11),
                        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(member['name'] ?? '', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
                          Text('Exp: ${DateFormatter.formatDate(member['membership_expiry_date'])}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11)),
                        ])),
                        Icon(Icons.chevron_right_rounded, size: 18, color: context.tokens.textTertiary),
                      ]),
                    );
                  },
                ),
        ),
      ]),
    ));
  }

  // ═══════════════════════════════════════════════════════════════
  // CALENDAR
  // ═══════════════════════════════════════════════════════════════
  Widget _calendarCard() {
    return SurfaceCard(
      child: Column(children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text('Attendance Calendar', style: context.typo.titleMedium),
          Row(children: [
            _mini(Icons.chevron_left_rounded, () => _changeCalendar(-1)),
            Padding(padding: const EdgeInsets.symmetric(horizontal: 8), child: Text('${_monthName(calendarMonth.month)} ${calendarMonth.year}', style: context.typo.titleSmall)),
            _mini(Icons.chevron_right_rounded, () => _changeCalendar(1)),
          ]),
        ]),
        const SizedBox(height: 14),
        Row(children: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => Expanded(child: Center(child: Text(d, style: context.typo.labelSmall)))).toList()),
        const SizedBox(height: 8),
        if (loadingCalendar) const Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator()) else _buildCalendarGrid(),
      ]),
    );
  }

  Widget _mini(IconData icon, VoidCallback onTap) => Material(color: context.tokens.surfaceAlt, shape: const CircleBorder(), child: InkWell(customBorder: const CircleBorder(), onTap: onTap, child: Padding(padding: const EdgeInsets.all(6), child: Icon(icon, size: 20))));

  Widget _buildCalendarGrid() {
    if (calendarData.isEmpty) return Text('No data', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary));
    final firstDay = DateTime(calendarMonth.year, calendarMonth.month, 1);
    final emptyCells = firstDay.weekday - 1;

    List<Widget> cells = [];
    for (int i = 0; i < emptyCells; i++) cells.add(const SizedBox(height: 46));
    for (var day in calendarData) {
      final dateKey = (day['date'] ?? '').toString();
      final present = day['present_count'] ?? day['present'] ?? countsMap[dateKey] ?? 0;
      final isToday = day['is_today'] ?? false;
      final isFuture = day['is_future'] ?? false;
      cells.add(GestureDetector(
        onTap: isFuture ? null : () => _openAttendanceForDate(day['date']),
        child: Container(
          height: 54,
          margin: const EdgeInsets.all(2.5),
          decoration: BoxDecoration(
            gradient: isToday ? AppTheme.fireGradient : null,
            color: isToday ? null : (present > 0 ? AppTheme.success.withOpacity(0.14) : context.tokens.surfaceAlt),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Opacity(
            opacity: isFuture ? 0.4 : 1,
            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              Text('${day['day']}', style: GoogleFonts.poppins(fontSize: 12.5, fontWeight: isToday ? FontWeight.w800 : FontWeight.w600, color: isToday ? Colors.white : context.tokens.text)),
              const SizedBox(height: 2),
              Text(
                '$present',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.poppins(
                  fontSize: present > 0 ? 8.5 : 8,
                  color: isToday ? Colors.white : (present > 0 ? AppTheme.success : context.tokens.textTertiary),
                  fontWeight: present > 0 ? FontWeight.w800 : FontWeight.w500,
                ),
              ),
            ]),
          ),
        ),
      ));
    }
    return GridView.count(crossAxisCount: 7, childAspectRatio: 0.78, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(), children: cells);
  }

  // ═══════════════════════════════════════════════════════════════
  // ATTENDANCE CHART
  // ═══════════════════════════════════════════════════════════════
  Widget _chartCard() {
    final chart = reports!['attendance_chart'] as List;
    final maxCount = chart.map((d) => int.tryParse(d['count'].toString()) ?? 0).fold(0, (a, b) => a > b ? a : b);
    return SurfaceCard(
      child: SizedBox(
        height: 130,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          crossAxisAlignment: CrossAxisAlignment.end,
          children: chart.map((day) {
            final count = int.tryParse(day['count'].toString()) ?? 0;
            final height = maxCount > 0 ? (count / maxCount * 80) : 0.0;
            return Column(mainAxisSize: MainAxisSize.min, children: [
              Text('$count', style: context.typo.labelSmall?.copyWith(color: context.tokens.text, fontSize: 10)),
              const SizedBox(height: 4),
              TweenAnimationBuilder<double>(
                tween: Tween(begin: 0, end: height + 8),
                duration: const Duration(milliseconds: 700),
                curve: Curves.easeOutCubic,
                builder: (_, h, __) => Container(width: 22, height: h, decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(8))),
              ),
              const SizedBox(height: 6),
              Text(DateFormatter.formatDate(day['date']).split('-').first, style: context.typo.labelSmall?.copyWith(fontSize: 9)),
            ]);
          }).toList(),
        ),
      ),
    );
  }

  // ═══════════════════════════════════════════════════════════════
  // REPORT LIST SECTIONS
  // ═══════════════════════════════════════════════════════════════
  Widget _reportListSection(String title, List list, Color color, IconData icon, {VoidCallback? onViewAll}) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      GestureDetector(
        onTap: onViewAll,
        child: Row(children: [
          IconBadge(icon, color: color, size: 32, iconSize: 16),
          const SizedBox(width: 10),
          Expanded(child: Text(title, style: context.typo.titleMedium)),
          StatusBadge('${list.length}', color: color),
          if (onViewAll != null) ...[
            const SizedBox(width: 6),
            Icon(Icons.chevron_right_rounded, size: 18, color: context.tokens.textTertiary),
          ],
        ]),
      ),
      const SizedBox(height: 10),
      if (list.isEmpty)
        SurfaceCard(padding: const EdgeInsets.all(14), child: Row(children: [const Icon(Icons.check_circle_rounded, size: 18, color: AppTheme.success), const SizedBox(width: 8), Text('None — all good!', style: context.typo.bodyMedium)]))
      else
        ...list.take(5).map((m) => Padding(padding: const EdgeInsets.only(bottom: 8), child: SurfaceCard(
          padding: const EdgeInsets.all(11),
          onTap: () => _openMemberDetail(m),
          child: Row(children: [
            GxAvatar(name: m['name'] ?? 'M', size: 40),
            const SizedBox(width: 11),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(m['name'] ?? '', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
              Text('Exp: ${DateFormatter.formatDate(m['membership_expiry_date'])} • ${m['plan_name'] ?? ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11)),
            ])),
            Icon(Icons.chevron_right_rounded, size: 18, color: context.tokens.textTertiary),
          ]),
        ))),
    ]);
  }

  String _monthName(int m) {
    const names = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return names[m];
  }
}
