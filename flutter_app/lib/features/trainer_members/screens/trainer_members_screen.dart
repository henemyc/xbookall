import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/features/diets/screens/assign_member_diet_screen.dart';

class TrainerMembersScreen extends ConsumerStatefulWidget {
  const TrainerMembersScreen({super.key});

  @override
  ConsumerState<TrainerMembersScreen> createState() => _TrainerMembersScreenState();
}

class _TrainerMembersScreenState extends ConsumerState<TrainerMembersScreen> {
  List members = [];
  String query = '';
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
      final res = await ref.read(apiClientProvider).getTrainerAssignedMembers();
      if (mounted) setState(() { members = res['members'] ?? []; loading = false; });
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
    if (e.toString().contains('connection')) return 'No internet connection';
    return 'Could not load assigned members';
  }

  List get filtered {
    final q = query.toLowerCase().trim();
    if (q.isEmpty) return members;
    return members.where((m) {
      final name = (m['name'] ?? '').toString().toLowerCase();
      final phone = (m['phone_number'] ?? '').toString().toLowerCase();
      final plan = (m['plan_name'] ?? '').toString().toLowerCase();
      return name.contains(q) || phone.contains(q) || plan.contains(q);
    }).toList();
  }

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
                      _headerCard(),
                      const SizedBox(height: 14),
                      TextField(
                        onChanged: (v) => setState(() => query = v),
                        decoration: const InputDecoration(
                          hintText: 'Search assigned members...',
                          prefixIcon: Icon(Icons.search_rounded),
                        ),
                      ),
                      const SizedBox(height: 14),
                      if (filtered.isEmpty)
                        const EmptyState(icon: Icons.people_outline_rounded, title: 'No assigned members', subtitle: 'Members assigned to you by the gym owner will appear here')
                      else
                        ...filtered.asMap().entries.map((e) => FadeInUp(
                          delayMs: (e.key * 18).clamp(0, 240),
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 10),
                            child: _memberCard(Map<String, dynamic>.from(e.value as Map)),
                          ),
                        )),
                    ],
                  ),
                ),
    );
  }

  Widget _headerCard() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)),
      child: Row(children: [
        IconBadge(Icons.people_rounded, color: AppTheme.brandAmber, size: 54, iconSize: 28),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Assigned Members', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 23, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text('${members.length} member${members.length == 1 ? '' : 's'} assigned to you', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.68), fontSize: 12.5)),
        ])),
      ]),
    );
  }

  Widget _memberCard(Map<String, dynamic> m) {
    final days = int.tryParse((m['days_left'] ?? '').toString());
    final expired = days != null && days < 0;
    final expiring = days != null && days >= 0 && days <= 7;
    final color = expired ? AppTheme.danger : (expiring ? AppTheme.warning : AppTheme.success);

    return SurfaceCard(
      padding: const EdgeInsets.all(12),
      onTap: () => _openDetail(m),
      child: Row(children: [
        GxAvatar(name: m['name'] ?? 'M', size: 48),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(m['name'] ?? '', style: context.typo.titleSmall),
          const SizedBox(height: 2),
          Text(m['phone_number'] ?? '', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          const SizedBox(height: 6),
          Wrap(spacing: 6, runSpacing: 6, children: [
            StatusBadge(m['plan_name'] ?? 'No Plan', color: AppTheme.info),
            StatusBadge(expired ? 'Expired' : (expiring ? 'Expiring' : 'Active'), color: color),
          ]),
        ])),
        const Icon(Icons.chevron_right_rounded, color: AppTheme.brand),
      ]),
    );
  }

  Future<void> _openDetail(Map<String, dynamic> m) async {
    showAppSheet(context, child: FutureBuilder<Map<String, dynamic>>(
      future: ref.read(apiClientProvider).getTrainerAssignedMember(int.parse(m['id'].toString())),
      builder: (context, snap) {
        if (!snap.hasData) {
          return const Padding(
            padding: EdgeInsets.all(32),
            child: Center(child: CircularProgressIndicator(color: AppTheme.brand)),
          );
        }

        final member = Map<String, dynamic>.from(snap.data!['member'] ?? {});
        final attendance = snap.data!['attendance_history'] as List? ?? [];
        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              GxAvatar(name: member['name'] ?? 'M', size: 54),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(member['name'] ?? '', style: context.typo.titleLarge),
                Text(member['phone_number'] ?? '', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
              ])),
            ]),
            const SizedBox(height: 16),
            _infoTile(Icons.card_membership_rounded, 'Plan', member['plan_name'] ?? 'No Plan'),
            _infoTile(Icons.event_rounded, 'Expiry', DateFormatter.formatDate(member['membership_expiry_date']?.toString())),
            _infoTile(Icons.flag_rounded, 'Fitness Goal', (member['fitness_goal'] ?? '').toString().isEmpty ? 'Not set' : member['fitness_goal'].toString()),
            if ((member['address'] ?? '').toString().isNotEmpty) _infoTile(Icons.location_on_rounded, 'Address', member['address'].toString()),
            const SizedBox(height: 18),
            FireButton(
              label: 'Assign Diet Plan',
              icon: Icons.restaurant_menu_rounded,
              onPressed: () async {
                final result = await Navigator.push(context, MaterialPageRoute(builder: (_) => AssignMemberDietScreen(memberId: int.parse(member['id'].toString()), memberName: member['name']?.toString() ?? 'Member')));
                if (result == true && context.mounted) Toast.success(context, 'Diet plan assigned');
              },
            ),
            const SizedBox(height: 18),
            Text('Recent Attendance', style: context.typo.titleMedium),
            const SizedBox(height: 10),
            if (attendance.isEmpty)
              Text('No attendance records yet', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))
            else
              ...attendance.take(7).map((a) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: SurfaceCard(
                  padding: const EdgeInsets.all(10),
                  color: context.tokens.surfaceAlt,
                  child: Row(children: [
                    const Icon(Icons.check_circle_rounded, color: AppTheme.success, size: 18),
                    const SizedBox(width: 8),
                    Expanded(child: Text(DateFormatter.formatDate(a['date']?.toString()), style: context.typo.titleSmall?.copyWith(fontSize: 13))),
                    Text('${DateFormatter.formatTime(a['checked_in_time']?.toString())} - ${a['checked_out_time'] != null ? DateFormatter.formatTime(a['checked_out_time']?.toString()) : 'In'}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                  ]),
                ),
              )),
          ]),
        );
      },
    ));
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
}
