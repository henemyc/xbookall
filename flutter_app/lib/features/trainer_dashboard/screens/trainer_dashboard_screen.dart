import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/providers/nav_provider.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class TrainerDashboardScreen extends ConsumerStatefulWidget {
  const TrainerDashboardScreen({super.key});

  @override
  ConsumerState<TrainerDashboardScreen> createState() => _TrainerDashboardScreenState();
}

class _TrainerDashboardScreenState extends ConsumerState<TrainerDashboardScreen> {
  bool loading = true;
  Map<String, dynamic> data = {};
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getTrainerDashboard();
      if (mounted) setState(() { data = res; loading = false; });
    } catch (e) {
      if (mounted) setState(() { error = 'Could not load trainer dashboard'; loading = false; });
    }
  }

  void _go(int index) {
    ref.read(navIndexProvider.notifier).state = index;
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final name = (auth.user?['name'] ?? 'Trainer').toString().split(' ').first;
    final detail = auth.user?['trainer_detail'] ?? auth.user?['trainerDetails'] ?? data['trainer']?['trainer_details'];

    return Scaffold(
      body: loading
          ? const SkeletonGrid()
          : error != null
              ? ErrorRetry(message: error!, onRetry: _load)
              : RefreshIndicator(
                  color: AppTheme.brand,
                  onRefresh: _load,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                    children: [
                      FadeInUp(child: _hero(name, detail)),
                      const SizedBox(height: 16),
                      Row(children: [
                        Expanded(child: StatTile(label: 'Members', value: '${data['assigned_members_count'] ?? 0}', icon: Icons.people_rounded, color: AppTheme.brand, onTap: () => _go(1))),
                        const SizedBox(width: 12),
                        Expanded(child: StatTile(label: 'Active', value: '${data['active_members_count'] ?? 0}', icon: Icons.verified_rounded, color: AppTheme.success, onTap: () => _go(1))),
                      ]),
                      const SizedBox(height: 12),
                      Row(children: [
                        Expanded(child: StatTile(label: 'Workouts', value: '${data['workouts_count'] ?? 0}', icon: Icons.fitness_center_rounded, color: AppTheme.warning, onTap: () => _go(2))),
                        const SizedBox(width: 12),
                        Expanded(child: StatTile(label: 'Today Classes', value: '${data['today_classes_count'] ?? 0}', icon: Icons.today_rounded, color: AppTheme.info, onTap: () => _go(3))),
                      ]),
                      const SizedBox(height: 20),
                      const SectionHeader('Quick Actions'),
                      const SizedBox(height: 12),
                      GridView.count(
                        crossAxisCount: 2,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        crossAxisSpacing: 12,
                        mainAxisSpacing: 12,
                        childAspectRatio: 1.36,
                        children: [
                          _quickAction(Icons.people_alt_rounded, 'Assigned Members', 'View member profiles', AppTheme.brand, () => _go(1)),
                          _quickAction(Icons.fitness_center_rounded, 'Workout Plans', 'Create member plans', AppTheme.warning, () => _go(2)),
                          _quickAction(Icons.self_improvement_rounded, 'Classes', 'Today & all schedules', AppTheme.success, () => _go(3)),
                          _quickAction(Icons.calculate_rounded, 'BMI Calculator', 'Quick health check', const Color(0xFF8B5CF6), () => _go(5)),
                        ],
                      ),
                      const SizedBox(height: 20),
                      const SectionHeader('Today Overview'),
                      const SizedBox(height: 12),
                      SurfaceCard(
                        child: Column(children: [
                          _infoRow(Icons.today_rounded, 'Today classes', '${data['today_classes_count'] ?? 0} scheduled classes assigned to you', AppTheme.info, () => _go(3)),
                          Divider(color: context.tokens.border),
                          _infoRow(Icons.groups_rounded, 'Total assigned classes', '${data['classes_count'] ?? 0} classes linked with your profile', AppTheme.success, () => _go(3)),
                          Divider(color: context.tokens.border),
                          _infoRow(Icons.warning_amber_rounded, 'Expiring members', '${data['expiring_members_count'] ?? 0} assigned members expiring in 7 days', AppTheme.warning, () => _go(1)),
                        ]),
                      ),
                      const SizedBox(height: 16),
                      SurfaceCard(
                        color: AppTheme.info.withOpacity(0.08),
                        border: Border.all(color: AppTheme.info.withOpacity(0.18)),
                        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          IconBadge(Icons.lock_rounded, color: AppTheme.info),
                          const SizedBox(width: 12),
                          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text('Trainer access', style: context.typo.titleSmall?.copyWith(color: AppTheme.info)),
                            const SizedBox(height: 3),
                            Text('You can manage assigned members, workout plans, class schedules and BMI calculations. Revenue, expenses and gym settings stay hidden.', style: context.typo.bodySmall?.copyWith(color: AppTheme.info, height: 1.45)),
                          ])),
                        ]),
                      ),
                    ],
                  ),
                ),
    );
  }

  Widget _hero(String name, dynamic detail) {
    final qualification = (detail is Map ? detail['qualification'] : null) ?? 'Fitness Trainer';
    final specialization = (detail is Map ? detail['specialization'] : null) ?? '';
    final experience = (detail is Map ? detail['experience_years'] : null)?.toString() ?? '';
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: AppTheme.darkHeroGradient,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.25), blurRadius: 24, offset: const Offset(0, 10), spreadRadius: -6)],
      ),
      child: Stack(children: [
        Positioned(right: -20, top: -28, child: Icon(Icons.sports_martial_arts_rounded, size: 132, color: AppTheme.brand.withOpacity(0.13))),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(18)),
              child: const Icon(Icons.sports_martial_arts_rounded, color: Colors.white, size: 28),
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Welcome Trainer,', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.7), fontSize: 13.5, fontWeight: FontWeight.w500)),
              Text(name, style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900, letterSpacing: -0.6), maxLines: 1, overflow: TextOverflow.ellipsis),
            ])),
          ]),
          const SizedBox(height: 14),
          Wrap(spacing: 8, runSpacing: 8, children: [
            _pill(qualification.toString()),
            if (specialization.toString().isNotEmpty) _pill(specialization.toString()),
            if (experience.isNotEmpty && experience != 'null') _pill('$experience yrs exp'),
          ]),
          const SizedBox(height: 14),
          Text('Plan sessions, track assigned members, manage workouts, review class schedules and calculate BMI from one trainer workspace.', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.68), fontSize: 12.6, height: 1.45)),
        ]),
      ]),
    );
  }

  Widget _pill(String text) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(color: Colors.white.withOpacity(0.12), borderRadius: BorderRadius.circular(30), border: Border.all(color: Colors.white.withOpacity(0.12))),
        child: Text(text, style: GoogleFonts.poppins(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w700)),
      );

  Widget _quickAction(IconData icon, String title, String sub, Color color, VoidCallback onTap) {
    return SurfaceCard(
      onTap: onTap,
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          IconBadge(icon, color: color, size: 42, iconSize: 20),
          const Spacer(),
          Icon(Icons.arrow_forward_rounded, size: 18, color: color),
        ]),
        const Spacer(),
        Text(title, style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w800), maxLines: 1, overflow: TextOverflow.ellipsis),
        const SizedBox(height: 3),
        Text(sub, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5), maxLines: 1, overflow: TextOverflow.ellipsis),
      ]),
    );
  }

  Widget _infoRow(IconData icon, String title, String sub, Color color, VoidCallback onTap) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 2),
          child: Row(children: [
            IconBadge(icon, color: color, size: 40),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: context.typo.titleSmall),
              Text(sub, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
            ])),
            Icon(Icons.chevron_right_rounded, color: context.tokens.textTertiary),
          ]),
        ),
      );
}
