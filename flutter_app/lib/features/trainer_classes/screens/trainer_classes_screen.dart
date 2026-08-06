import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class TrainerClassesScreen extends ConsumerStatefulWidget {
  const TrainerClassesScreen({super.key});

  @override
  ConsumerState<TrainerClassesScreen> createState() => _TrainerClassesScreenState();
}

class _TrainerClassesScreenState extends ConsumerState<TrainerClassesScreen> {
  List classes = [];
  int totalMembers = 0;
  int todayCount = 0;
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
      final res = await ref.read(apiClientProvider).getTrainerClasses();
      if (mounted) {
        setState(() {
          classes = res['classes'] ?? [];
          totalMembers = int.tryParse((res['total_members'] ?? 0).toString()) ?? 0;
          todayCount = int.tryParse((res['today_count'] ?? 0).toString()) ?? _todayClasses.length;
          loading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { error = _friendlyError(e); loading = false; });
    }
  }

  String _friendlyError(dynamic e) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) {
        final msg = data['error'] ?? data['message'];
        if (msg != null) return msg.toString();
      }
    } catch (_) {}
    final msg = e.toString();
    if (msg.contains('connection') || msg.contains('SocketException')) return 'No internet connection';
    if (msg.contains('403')) return 'Trainer access required';
    return 'Could not load classes';
  }

  List<Map<String, dynamic>> get _classMaps => _mapList(classes);

  List<Map<String, dynamic>> get _todayClasses => _classMaps.where((c) {
    if (c['has_today_schedule'] == true || c['has_today_schedule'].toString() == '1') return true;
    return _mapList(c['schedules']).any(_isTodaySchedule);
  }).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: loading
          ? const SkeletonList()
          : error != null
              ? ErrorRetry(message: error!, onRetry: _load)
              : RefreshIndicator(
                  color: AppTheme.brand,
                  onRefresh: _load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                    children: [
                      _hero(),
                      const SizedBox(height: 14),
                      Row(children: [
                        Expanded(child: StatTile(label: 'Assigned Classes', value: '${classes.length}', icon: Icons.self_improvement_rounded, color: AppTheme.brand)),
                        const SizedBox(width: 12),
                        Expanded(child: StatTile(label: 'Today', value: '$todayCount', icon: Icons.today_rounded, color: AppTheme.success)),
                      ]),
                      const SizedBox(height: 14),
                      Row(children: [
                        Expanded(child: StatTile(label: 'Class Members', value: '$totalMembers', icon: Icons.groups_rounded, color: AppTheme.info)),
                        const SizedBox(width: 12),
                        Expanded(child: StatTile(label: 'Schedules', value: '${_scheduleCount()}', icon: Icons.schedule_rounded, color: AppTheme.warning)),
                      ]),
                      const SizedBox(height: 20),
                      if (_todayClasses.isNotEmpty) ...[
                        const SectionHeader('Today\'s Classes'),
                        const SizedBox(height: 12),
                        ..._todayClasses.asMap().entries.map((entry) => FadeInUp(
                          delayMs: (entry.key * 18).clamp(0, 180),
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: _todayCard(entry.value),
                          ),
                        )),
                        const SizedBox(height: 8),
                      ],
                      const SectionHeader('All Assigned Classes'),
                      const SizedBox(height: 12),
                      if (classes.isEmpty)
                        const EmptyState(icon: Icons.self_improvement_outlined, title: 'No classes assigned', subtitle: 'Classes assigned by the gym owner will appear here')
                      else
                        ..._classMaps.asMap().entries.map((entry) => FadeInUp(
                          delayMs: (entry.key * 18).clamp(0, 240),
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: _classCard(entry.value, AppTheme.categoryColors[entry.key % AppTheme.categoryColors.length]),
                          ),
                        )),
                    ],
                  ),
                ),
    );
  }

  Widget _hero() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)),
      child: Stack(children: [
        Positioned(right: -20, top: -28, child: Icon(Icons.calendar_month_rounded, size: 118, color: AppTheme.brand.withOpacity(0.13))),
        Row(children: [
          IconBadge(Icons.self_improvement_rounded, color: AppTheme.brandAmber, size: 56, iconSize: 29),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Class Schedule', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800)),
            const SizedBox(height: 4),
            Text('View classes, timings and enrolled members assigned to you.', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.68), fontSize: 12.5, height: 1.35)),
          ])),
        ]),
      ]),
    );
  }

  Widget _todayCard(Map<String, dynamic> c) {
    final todaySchedules = _mapList(c['today_schedules']).isNotEmpty
        ? _mapList(c['today_schedules'])
        : _mapList(c['schedules']).where(_isTodaySchedule).toList();
    final first = todaySchedules.isNotEmpty ? todaySchedules.first : <String, dynamic>{};

    return SurfaceCard(
      padding: const EdgeInsets.all(14),
      color: AppTheme.success.withOpacity(0.08),
      border: Border.all(color: AppTheme.success.withOpacity(0.18)),
      onTap: () => _showClassDetail(c),
      child: Row(children: [
        IconBadge(Icons.today_rounded, color: AppTheme.success, size: 46, iconSize: 22),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(c['title']?.toString() ?? 'Class', style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 3),
          Text(_scheduleLine(first), style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
        ])),
        StatusBadge('${c['member_count'] ?? 0} members', color: AppTheme.success),
      ]),
    );
  }

  Widget _classCard(Map<String, dynamic> c, Color color) {
    final schedules = _mapList(c['schedules']);
    final hasToday = c['has_today_schedule'] == true || c['has_today_schedule'].toString() == '1' || schedules.any(_isTodaySchedule);
    return SurfaceCard(
      padding: const EdgeInsets.all(14),
      onTap: () => _showClassDetail(c),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          IconBadge(Icons.self_improvement_rounded, color: color, size: 48, iconSize: 23),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(c['title']?.toString() ?? 'Class', style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 3),
            Text('₹${_amount(c['fees'])}${(c['address'] ?? '').toString().isNotEmpty ? ' • ${c['address']}' : ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary), maxLines: 1, overflow: TextOverflow.ellipsis),
          ])),
          if (hasToday) StatusBadge('Today', color: AppTheme.success, icon: Icons.today_rounded),
          const SizedBox(width: 6),
          Icon(Icons.chevron_right_rounded, color: context.tokens.textTertiary),
        ]),
        const SizedBox(height: 12),
        Wrap(spacing: 8, runSpacing: 8, children: [
          StatusBadge('${c['member_count'] ?? 0} members', color: AppTheme.info, icon: Icons.groups_rounded),
          StatusBadge('${schedules.length} schedule${schedules.length == 1 ? '' : 's'}', color: AppTheme.warning, icon: Icons.schedule_rounded),
        ]),
        if (schedules.isNotEmpty) ...[
          const SizedBox(height: 12),
          ...schedules.take(2).map((s) => Padding(
            padding: const EdgeInsets.only(bottom: 7),
            child: _scheduleRow(s),
          )),
          if (schedules.length > 2) Text('+ ${schedules.length - 2} more schedules', style: context.typo.bodySmall?.copyWith(color: AppTheme.brand, fontWeight: FontWeight.w800)),
        ],
      ]),
    );
  }

  Widget _scheduleRow(Map<String, dynamic> s) {
    final today = _isTodaySchedule(s);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(13), border: Border.all(color: today ? AppTheme.success.withOpacity(0.22) : context.tokens.border)),
      child: Row(children: [
        Icon(Icons.schedule_rounded, size: 16, color: today ? AppTheme.success : AppTheme.info),
        const SizedBox(width: 8),
        Expanded(child: Text(s['days']?.toString() ?? 'Schedule', style: context.typo.titleSmall?.copyWith(fontSize: 13))),
        Text(_timeRange(s), style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontWeight: FontWeight.w700)),
      ]),
    );
  }

  void _showClassDetail(Map<String, dynamic> c) {
    final schedules = _mapList(c['schedules']);
    final members = _mapList(c['assigned_members']);
    showAppSheet(context, child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          IconBadge(Icons.self_improvement_rounded, color: AppTheme.brand, size: 58, iconSize: 29),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(c['title']?.toString() ?? 'Class', style: context.typo.titleLarge),
            Text('₹${_amount(c['fees'])} • ${c['member_count'] ?? 0} enrolled members', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          ])),
        ]),
        if ((c['address'] ?? '').toString().isNotEmpty) ...[
          const SizedBox(height: 16),
          _infoTile(Icons.location_on_rounded, 'Location', c['address'].toString()),
        ],
        if ((c['notes'] ?? '').toString().isNotEmpty) _infoTile(Icons.notes_rounded, 'Notes', c['notes'].toString()),
        const SizedBox(height: 18),
        const SectionHeader('Schedule'),
        const SizedBox(height: 10),
        if (schedules.isEmpty)
          Text('No schedule added by gym owner', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))
        else
          ...schedules.map((s) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: _scheduleRow(s),
          )),
        const SizedBox(height: 18),
        const SectionHeader('Class Members'),
        const SizedBox(height: 10),
        if (members.isEmpty)
          Text('No members enrolled in this class', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))
        else
          ...members.map((m) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: SurfaceCard(
              padding: const EdgeInsets.all(10),
              color: context.tokens.surfaceAlt,
              shadow: false,
              child: Row(children: [
                GxAvatar(name: m['name']?.toString() ?? 'M', size: 40),
                const SizedBox(width: 10),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(m['name']?.toString() ?? 'Member', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
                  Text('${m['phone_number'] ?? ''}${(m['plan_name'] ?? '').toString().isNotEmpty ? ' • ${m['plan_name']}' : ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary), maxLines: 1, overflow: TextOverflow.ellipsis),
                ])),
                _memberStatus(m),
              ]),
            ),
          )),
      ]),
    ));
  }

  Widget _memberStatus(Map<String, dynamic> m) {
    final days = int.tryParse((m['days_left'] ?? '').toString());
    if (days == null) return const SizedBox.shrink();
    final expired = days < 0;
    final expiring = days >= 0 && days <= 7;
    return StatusBadge(expired ? 'Expired' : (expiring ? 'Expiring' : 'Active'), color: expired ? AppTheme.danger : (expiring ? AppTheme.warning : AppTheme.success));
  }

  Widget _infoTile(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        IconBadge(icon, color: AppTheme.brand, size: 36, iconSize: 17),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          Text(value, style: context.typo.titleSmall),
        ])),
      ]),
    );
  }

  int _scheduleCount() => _classMaps.fold<int>(0, (sum, c) => sum + _mapList(c['schedules']).length);

  bool _isTodaySchedule(Map<String, dynamic> s) => s['is_today'] == true || s['is_today'].toString() == '1';

  String _scheduleLine(Map<String, dynamic> s) {
    if (s.isEmpty) return 'Today';
    return '${s['days'] ?? 'Today'} • ${_timeRange(s)}';
  }

  String _timeRange(Map<String, dynamic> s) {
    final start = DateFormatter.formatTime(s['start_time']?.toString());
    final end = DateFormatter.formatTime(s['end_time']?.toString());
    if (start == '-' && end == '-') return '-';
    if (end == '-') return start;
    return '$start - $end';
  }

  List<Map<String, dynamic>> _mapList(dynamic raw) {
    if (raw is! List) return [];
    return raw.where((e) => e is Map).map<Map<String, dynamic>>((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  String _amount(dynamic value) {
    final num? n = value is num ? value : num.tryParse(value?.toString() ?? '0');
    if (n == null) return '0';
    if (n % 1 == 0) return n.toInt().toString();
    return n.toStringAsFixed(2);
  }
}
