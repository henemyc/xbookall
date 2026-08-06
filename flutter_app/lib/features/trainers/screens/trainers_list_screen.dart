import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';
import 'add_trainer_screen.dart';
import 'trainer_detail_screen.dart';

class TrainersListScreen extends ConsumerStatefulWidget {
  const TrainersListScreen({super.key});
  @override
  ConsumerState<TrainersListScreen> createState() => _TrainersListScreenState();
}

class _TrainersListScreenState extends ConsumerState<TrainersListScreen> {
  List trainers = [];
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
      final res = await ref.read(apiClientProvider).getTrainers();
      if (mounted) setState(() { trainers = res['trainers'] ?? []; loading = false; });
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
        if (data['errors'] is Map) {
          final first = (data['errors'] as Map).values.first;
          if (first is List && first.isNotEmpty) return first.first.toString();
          return first.toString();
        }
      }
    } catch (_) {}
    final msg = e.toString();
    if (msg.contains('connection') || msg.contains('SocketException') || msg.contains('Connection refused')) return 'No internet. Please check connection.';
    if (msg.contains('401')) return 'Session expired. Please login again.';
    return 'Failed. Please try again.';
  }

  Future<void> _openAdd() async {
    final created = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => const AddTrainerScreen()));
    if (created == true) _load();
  }

  Future<void> _openDetail(Map trainer) async {
    final id = int.tryParse((trainer['id'] ?? trainer['user_id'] ?? 0).toString()) ?? 0;
    if (id <= 0) return;
    final changed = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => TrainerDetailScreen(trainerId: id, trainerName: trainer['name'] ?? 'Trainer')));
    if (changed == true) _load();
  }

  Future<void> _toggleActive(Map t) async {
    try {
      await ref.read(apiClientProvider).toggleTrainer(t['id']);
      await _load();
      if (mounted) Toast.success(context, 'Trainer status updated');
    } catch (e) {
      if (mounted) Toast.error(context, _friendlyError(e));
    }
  }

  bool _active(Map t) => t['is_active'] == true || t['is_active'].toString() == '1';

  @override
  Widget build(BuildContext context) {
    final perms = ref.watch(permissionProvider);
    final canCreate = perms.can('trainers.create');
    return Scaffold(
      body: loading
          ? const SkeletonList()
          : error != null
              ? ErrorRetry(message: error!, onRetry: _load)
              : trainers.isEmpty
                  ? EmptyState(icon: Icons.sports_martial_arts_rounded, title: 'No trainers yet', subtitle: canCreate ? 'Add trainers to assign members, workouts and classes' : 'No trainers available for your role', actionLabel: canCreate ? 'Add Trainer' : null, onAction: canCreate ? _openAdd : null)
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                        itemCount: trainers.length + 1,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (ctx, i) {
                          if (i == 0) return _hero();
                          final t = Map<String, dynamic>.from(trainers[i - 1] as Map);
                          return FadeInUp(delayMs: ((i - 1) * 22).clamp(0, 240), offset: 10, child: _trainerCard(t));
                        },
                      ),
                    ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(
              onPressed: _openAdd,
              icon: const Icon(Icons.person_add_rounded),
              label: const Text('Add Trainer', style: TextStyle(fontWeight: FontWeight.w700)),
              backgroundColor: AppTheme.brand,
            )
          : null,
    );
  }

  Widget _hero() {
    final active = trainers.where((e) => _active(Map<String, dynamic>.from(e as Map))).length;
    final assignedMembers = trainers.fold<int>(0, (sum, e) => sum + (int.tryParse(((e as Map)['assigned_members_count'] ?? 0).toString()) ?? 0));
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)),
      child: Row(children: [
        IconBadge(Icons.sports_martial_arts_rounded, color: AppTheme.brandAmber, size: 54, iconSize: 28),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Trainers', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text('${trainers.length} trainers • $active active • $assignedMembers assigned members', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.68), fontSize: 12.5)),
        ])),
      ]),
    );
  }

  Widget _trainerCard(Map<String, dynamic> t) {
    final active = _active(t);
    final members = int.tryParse((t['assigned_members_count'] ?? 0).toString()) ?? 0;
    final classes = int.tryParse((t['assigned_classes_count'] ?? 0).toString()) ?? 0;
    final canEdit = ref.watch(permissionProvider).can('trainers.edit');

    return SurfaceCard(
      padding: const EdgeInsets.all(12),
      onTap: () => _openDetail(t),
      child: Row(children: [
        GxAvatar(name: t['name'] ?? 'T', size: 50),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(t['name'] ?? '', style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
          const SizedBox(height: 2),
          Text((t['specialization'] ?? t['qualification'] ?? t['email'] ?? '').toString(), style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary), maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 7),
          Wrap(spacing: 6, runSpacing: 6, children: [
            StatusBadge(active ? 'Active' : 'Inactive', color: active ? AppTheme.success : AppTheme.danger),
            StatusBadge('$members members', color: AppTheme.info, icon: Icons.people_rounded),
            if (classes > 0) StatusBadge('$classes classes', color: AppTheme.warning, icon: Icons.self_improvement_rounded),
          ]),
        ])),
        PopupMenuButton<String>(
          icon: Icon(Icons.more_vert_rounded, color: context.tokens.textTertiary, size: 21),
          onSelected: (v) {
            if (v == 'view') _openDetail(t);
            if (v == 'toggle') _toggleActive(t);
          },
          itemBuilder: (_) => [
            const PopupMenuItem(value: 'view', child: Row(children: [Icon(Icons.visibility_rounded, size: 18), SizedBox(width: 8), Text('View Details')])),
            if (canEdit) PopupMenuItem(value: 'toggle', child: Row(children: [Icon(active ? Icons.toggle_off_rounded : Icons.toggle_on_rounded, size: 18), const SizedBox(width: 8), Text(active ? 'Deactivate' : 'Activate')])),
          ],
        ),
      ]),
    );
  }
}
