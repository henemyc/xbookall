import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class MemberWorkoutScreen extends ConsumerStatefulWidget {
  const MemberWorkoutScreen({super.key});
  @override
  ConsumerState<MemberWorkoutScreen> createState() => _MemberWorkoutScreenState();
}

class _MemberWorkoutScreenState extends ConsumerState<MemberWorkoutScreen> {
  List workouts = [];
  bool loading = true;
  String? error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getWorkouts();
      final data = (res is Map) ? Map<String, dynamic>.from(res) : <String, dynamic>{};
      if (mounted) setState(() { workouts = data['workouts'] ?? []; loading = false; });
    } catch (e) { if (mounted) setState(() { error = e.toString(); loading = false; }); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: loading
          ? const SkeletonList()
          : error != null
              ? ErrorRetry(message: 'Could not load your workouts.', onRetry: _load)
              : workouts.isEmpty
                  ? const EmptyState(icon: Icons.fitness_center_rounded, title: 'No workout plan assigned', subtitle: 'Ask your trainer to assign a plan')
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                        itemCount: workouts.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 12),
                        itemBuilder: (ctx, i) {
                          final w = workouts[i];
                          return FadeInUp(delayMs: (i * 22).clamp(0, 240), offset: 10, child: SurfaceCard(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Row(children: [
                                IconBadge(Icons.fitness_center_rounded, color: AppTheme.brand, size: 38),
                                const SizedBox(width: 10),
                                Expanded(child: Text('${w['start_date'] ?? ''} → ${w['end_date'] ?? ''}', style: context.typo.titleSmall)),
                                const StatusBadge('Active', color: AppTheme.success),
                              ]),
                              if ((w['notes'] ?? '') != '') ...[const SizedBox(height: 12), Text(w['notes'] ?? '', style: context.typo.bodyMedium)],
                              if (w['workout_history'] != null) ...[
                                const SizedBox(height: 12),
                                ..._buildWorkoutPlan(w['workout_history']),
                              ],
                            ]),
                          ));
                        },
                      ),
                    ),
    );
  }

  List<Widget> _buildWorkoutPlan(dynamic history) {
    List<dynamic> plan;
    try {
      if (history is String) {
        plan = jsonDecode(history) as List;
      } else if (history is List) {
        plan = history;
      } else {
        return [Text(history.toString(), style: context.typo.bodySmall)];
      }
    } catch (_) {
      return [Text(history.toString(), style: context.typo.bodySmall)];
    }

    return plan.map((day) {
      final dayName = day['day'] ?? '';
      final exercises = (day['exercises'] as List?)?.cast<String>() ?? [];
      return Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(14)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Icon(Icons.calendar_today_rounded, size: 14, color: AppTheme.brand),
            const SizedBox(width: 6),
            Text(dayName, style: context.typo.titleSmall?.copyWith(color: AppTheme.brand)),
          ]),
          const SizedBox(height: 8),
          ...exercises.map((ex) => Padding(
            padding: const EdgeInsets.only(bottom: 4),
            child: Row(children: [
              Container(width: 6, height: 6, decoration: BoxDecoration(color: AppTheme.brand.withOpacity(0.4), shape: BoxShape.circle)),
              const SizedBox(width: 10),
              Expanded(child: Text(ex, style: context.typo.bodyMedium)),
            ]),
          )),
        ]),
      );
    }).toList();
  }
}
