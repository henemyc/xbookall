import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

// D6: member read-only active diet screen.
class MemberDietScreen extends ConsumerStatefulWidget {
  const MemberDietScreen({super.key});
  @override
  ConsumerState<MemberDietScreen> createState() => _MemberDietScreenState();
}

class _MemberDietScreenState extends ConsumerState<MemberDietScreen> {
  Map<String, dynamic>? diet;
  bool loading = true;
  @override
  void initState() { super.initState(); _load(); }
  Future<void> _load() async {
    setState(() => loading = true);
    try { final r = await ref.read(apiClientProvider).getMyDiet(); if (mounted) setState(() { diet = r['diet'] == null ? null : Map<String, dynamic>.from(r['diet']); loading = false; }); }
    catch (_) { if (mounted) setState(() => loading = false); }
  }
  @override
  Widget build(BuildContext context) => Scaffold(
    body: loading ? const SkeletonList() : diet == null ? const EmptyState(icon: Icons.restaurant_menu_rounded, title: 'No diet plan assigned', subtitle: 'Ask your trainer or gym to assign a diet plan.') : RefreshIndicator(
      onRefresh: _load,
      child: ListView(padding: EdgeInsets.fromLTRB(16, 10, 16, context.navSpace + 18), children: [
        Container(padding: const EdgeInsets.all(20), decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(diet!['title'] ?? 'My Diet', style: context.typo.titleLarge?.copyWith(color: Colors.white)),
          const SizedBox(height: 6), Text(diet!['goal'] ?? 'Your personalized meal plan', style: context.typo.bodyMedium?.copyWith(color: Colors.white70)),
          const SizedBox(height: 14), Wrap(spacing: 8, children: [if (diet!['daily_calories'] != null) _chip('${diet!['daily_calories']} kcal'), if (diet!['protein_target'] != null) _chip('${diet!['protein_target']}g protein'), if (diet!['water_target'] != null) _chip('${diet!['water_target']} ml water')]),
        ])),
        const SizedBox(height: 18), Text('Daily Meals', style: context.typo.titleMedium),
        ...((diet!['meals'] as List?) ?? const []).map((raw) { final m = Map<String, dynamic>.from(raw as Map); return Padding(padding: const EdgeInsets.only(top: 10), child: SurfaceCard(child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [IconBadge(Icons.restaurant_rounded, color: AppTheme.success, size: 38), const SizedBox(width: 10), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(m['meal_name'] ?? '', style: context.typo.titleSmall), if ((m['meal_time'] ?? '').toString().isNotEmpty) Text(m['meal_time'], style: context.typo.bodySmall?.copyWith(color: AppTheme.brand)), if ((m['food_items'] ?? '').toString().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 5), child: Text(m['food_items'], style: context.typo.bodyMedium)), if ((m['quantity'] ?? '').toString().isNotEmpty) Text(m['quantity'], style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)), if ((m['notes'] ?? '').toString().isNotEmpty) Padding(padding: const EdgeInsets.only(top: 4), child: Text(m['notes'], style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)))]))]))); }),
        if ((diet!['general_instructions'] ?? '').toString().isNotEmpty) ...[const SizedBox(height: 18), Text('Instructions', style: context.typo.titleMedium), const SizedBox(height: 8), SurfaceCard(child: Text(diet!['general_instructions'], style: context.typo.bodyMedium))],
      ]),
    ),
  );
  Widget _chip(String text) => Container(padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5), decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(16)), child: Text(text, style: context.typo.labelSmall?.copyWith(color: Colors.white)));
}
