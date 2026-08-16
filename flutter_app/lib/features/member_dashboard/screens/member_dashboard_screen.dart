import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/core/providers/nav_provider.dart';

class MemberDashboardScreen extends ConsumerStatefulWidget {
  const MemberDashboardScreen({super.key});
  @override
  ConsumerState<MemberDashboardScreen> createState() => _MemberDashboardScreenState();
}

class _MemberDashboardScreenState extends ConsumerState<MemberDashboardScreen> {
  Map<String, dynamic>? member;
  bool loading = true;
  String? error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getMyProfile();
      final authUser = ref.read(authProvider).user ?? {};

      final userData = {
        ...authUser,
        ...Map<String, dynamic>.from(res['user'] ?? res),
      };

      final trainee = res['trainee_detail'] 
          ?? res['trainee_details'] 
          ?? res['traineeDetails']
          ?? userData['trainee_detail'] 
          ?? userData['traineeDetails'];

      if (trainee != null) {
        userData['trainee_detail'] = trainee;
      }

      // 100% Dart-safe plan name extraction (no null-aware subscript in ternary)
      String? computedPlanName = res['plan_name'] ?? userData['plan_name'];
      if (computedPlanName == null && trainee is Map) {
        final mem = trainee['membership'];
        if (mem is Map) {
          computedPlanName = mem['title']?.toString();
        }
      }
      userData['plan_name'] = computedPlanName ?? 'No Plan';

      userData['membership_expiry_date'] = res['membership_expiry_date'] 
          ?? res['membership_expiry'] 
          ?? (trainee is Map ? trainee['membership_expiry_date'] : null);
      
      userData['membership_start_date'] = res['membership_start_date'] 
          ?? (trainee is Map ? trainee['membership_start_date'] : null);
      
      userData['fitness_goal'] = res['fitness_goal'] 
          ?? (trainee is Map ? trainee['fitness_goal'] : null);
      
      userData['trainee_status'] = res['trainee_status'] 
          ?? (trainee is Map ? trainee['status'] : 1);

      if (mounted) setState(() { member = userData; loading = false; });
    } catch (e) {
      final authUser = ref.read(authProvider).user;
      if (mounted) setState(() { member = authUser; loading = false; });
    }
  }

  // Safe helper to avoid any ?[] issues
  String _getStr(dynamic map, String key, [String fallback = '']) {
    if (map is! Map) return fallback;
    final v = map[key];
    return v?.toString() ?? fallback;
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final user = auth.user;

    Map<String, dynamic>? td = {};
    if (member != null) {
      final rawTd = member!['trainee_detail'] ?? member!['trainee_details'] ?? member!['traineeDetails'];
      td = (rawTd is Map) ? Map<String, dynamic>.from(rawTd) : {};

      // Root-level fallback (safe)
      if (_getStr(td, 'plan_name').isEmpty) {
        String? p = _getStr(member, 'plan_name');
        if (p.isEmpty) {
          final mem = td['membership'];
          if (mem is Map) {
            p = _getStr(mem, 'title');
          }
        }
        td['plan_name'] = p.isNotEmpty ? p : 'No Plan';
      }
      if (_getStr(td, 'membership_expiry_date').isEmpty) {
        td['membership_expiry_date'] = member!['membership_expiry'] ?? member!['membership_expiry_date'];
      }
      if (_getStr(td, 'membership_start_date').isEmpty) {
        td['membership_start_date'] = member!['membership_start_date'];
      }
      if (_getStr(td, 'fitness_goal').isEmpty) {
        td['fitness_goal'] = member!['fitness_goal'];
      }
      if (_getStr(td, 'trainee_status').isEmpty) {
        td['trainee_status'] = member!['trainee_status'] ?? 1;
      }
    }

    if (loading) return const SkeletonGrid();
    if (error != null) return ErrorRetry(message: 'Could not load your profile.', onRetry: _load);

    final planName = _getStr(td, 'plan_name', 'No Plan');
    final goal = _getStr(td, 'fitness_goal', 'General');
    final startDate = _getStr(td, 'membership_start_date');
    final expiryDate = _getStr(td, 'membership_expiry_date');
    final status = _getStr(td, 'trainee_status', '1');

    return RefreshIndicator(
      color: AppTheme.brand,
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.fromLTRB(16, 4, 16, context.navSpace + 16),
        children: [
          FadeInUp(child: Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(26)),
            child: Stack(children: [
              Positioned(right: -10, top: -10, child: Icon(Icons.local_fire_department_rounded, size: 110, color: AppTheme.brand.withOpacity(0.16))),
              Row(children: [
                GxAvatar(name: (user != null ? user['name'] : null) ?? 'M', imageUrl: (user != null ? user['profile_photo_url'] : null)?.toString(), size: 60),
                const SizedBox(width: 16),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Hi, ${(user != null ? user['name'] : null) ?? 'Member'} 👋', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 3),
                  Text((user != null ? user['email'] : null) ?? '', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.7), fontSize: 12)),
                  if (expiryDate.isNotEmpty)
                    Container(
                      margin: const EdgeInsets.only(top: 8), 
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5), 
                      decoration: BoxDecoration(color: Colors.white.withOpacity(0.12), borderRadius: BorderRadius.circular(20)), 
                      child: Text('Expires ${DateFormatter.formatDate(expiryDate)}', style: GoogleFonts.poppins(color: AppTheme.brandAmber, fontSize: 11, fontWeight: FontWeight.w700))
                    ),
                ])),
              ]),
            ]),
          )),
          const SizedBox(height: 18),
          FadeInUp(delayMs: 60, child: Row(children: [
            Expanded(child: _statCard('Plan', planName, Icons.card_membership_rounded, AppTheme.brand)),
            const SizedBox(width: 12),
            Expanded(child: _statCard('Goal', goal, Icons.flag_rounded, AppTheme.success)),
          ])),
          const SizedBox(height: 22),
          const SectionHeader('Quick Actions'),
          const SizedBox(height: 12),
          FadeInUp(delayMs: 100, child: Row(children: [
            _quickAction(Icons.qr_code_scanner_rounded, 'Scan QR', AppTheme.brand, () => ref.read(navIndexProvider.notifier).state = 2),
            _quickAction(Icons.fact_check_rounded, 'Visits', AppTheme.info, () => ref.read(navIndexProvider.notifier).state = 1),
            _quickAction(Icons.fitness_center_rounded, 'Workout', AppTheme.success, () => ref.read(navIndexProvider.notifier).state = 3),
            _quickAction(Icons.campaign_rounded, 'Notices', AppTheme.warning, () => ref.read(navIndexProvider.notifier).state = 5),
          ])),
          const SizedBox(height: 22),
          const SectionHeader('Membership Details'),
          const SizedBox(height: 12),
          FadeInUp(delayMs: 140, child: SurfaceCard(child: Column(children: [
            _detailRow('Start Date', DateFormatter.formatDate(startDate)),
            _detailRow('Expiry Date', DateFormatter.formatDate(expiryDate)),
            _detailRow('Plan', planName),
            _detailRow('Status', status == '1' ? 'Active' : 'Inactive', last: true),
          ]))),
        ],
      ),
    );
  }

  Widget _statCard(String label, String value, IconData icon, Color color) {
    return SurfaceCard(padding: const EdgeInsets.all(14), child: Row(children: [
      IconBadge(icon, color: color, size: 40),
      const SizedBox(width: 10),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11)), 
        Text(value, style: context.typo.titleSmall, overflow: TextOverflow.ellipsis)
      ])),
    ]));
  }

  Widget _quickAction(IconData icon, String label, Color color, VoidCallback onTap) {
    return Expanded(child: Pressable(radius: 20, onTap: onTap, child: Column(children: [
      Center(child: Container(width: 58, height: 58, alignment: Alignment.center, decoration: BoxDecoration(color: color.withOpacity(0.12), borderRadius: BorderRadius.circular(18), border: Border.all(color: color.withOpacity(0.12))), child: Icon(icon, color: color, size: 26))),
      const SizedBox(height: 8),
      Text(label, textAlign: TextAlign.center, style: context.typo.bodySmall?.copyWith(fontWeight: FontWeight.w600, fontSize: 11.5)),
    ])));
  }

  Widget _detailRow(String label, String value, {bool last = false}) {
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(label, style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary)),
        Text(value, style: context.typo.titleSmall?.copyWith(fontSize: 13)),
      ]),
    );
  }
}
