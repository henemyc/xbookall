import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class TrainerWorkoutsScreen extends ConsumerStatefulWidget {
  const TrainerWorkoutsScreen({super.key});

  @override
  ConsumerState<TrainerWorkoutsScreen> createState() => _TrainerWorkoutsScreenState();
}

class _TrainerWorkoutsScreenState extends ConsumerState<TrainerWorkoutsScreen> {
  List members = [];
  List workouts = [];
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
      final api = ref.read(apiClientProvider);
      final mRes = await api.getTrainerAssignedMembers();
      final wRes = await api.getTrainerWorkoutPlans();
      if (mounted) {
        setState(() {
          members = mRes['members'] ?? [];
          workouts = wRes['workouts'] ?? [];
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
    return e.toString().contains('connection') ? 'No internet connection' : 'Could not load workouts';
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
                      _hero(),
                      const SizedBox(height: 14),
                      if (workouts.isEmpty)
                        EmptyState(icon: Icons.fitness_center_rounded, title: 'No workout plans yet', subtitle: members.isEmpty ? 'No assigned members found' : 'Create a plan for an assigned member', actionLabel: members.isEmpty ? null : 'Create Workout', onAction: members.isEmpty ? null : () => _showWorkoutSheet())
                      else
                        ...workouts.asMap().entries.map((e) => FadeInUp(
                          delayMs: (e.key * 18).clamp(0, 240),
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: _workoutCard(Map<String, dynamic>.from(e.value as Map)),
                          ),
                        )),
                    ],
                  ),
                ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: members.isEmpty ? null : () => _showWorkoutSheet(),
        icon: const Icon(Icons.add_rounded),
        label: const Text('Create Plan', style: TextStyle(fontWeight: FontWeight.w800)),
        backgroundColor: AppTheme.brand,
      ),
    );
  }

  Widget _hero() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)),
      child: Row(children: [
        IconBadge(Icons.fitness_center_rounded, color: AppTheme.brandAmber, size: 54, iconSize: 28),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Workout Plans', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 23, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text('${workouts.length} plan${workouts.length == 1 ? '' : 's'} • ${members.length} assigned member${members.length == 1 ? '' : 's'}', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.68), fontSize: 12.5)),
        ])),
      ]),
    );
  }

  Widget _workoutCard(Map<String, dynamic> w) {
    final plan = _parsePlan(w['workout_history']);
    final exerciseCount = plan.fold<int>(0, (sum, d) => sum + ((d['exercises'] as List?)?.length ?? 0));
    return SurfaceCard(
      padding: const EdgeInsets.all(14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          GxAvatar(name: w['member_name'] ?? 'M', size: 46),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(w['member_name'] ?? '', style: context.typo.titleSmall),
            Text('${DateFormatter.formatDate(w['start_date']?.toString())} → ${DateFormatter.formatDate(w['end_date']?.toString())}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          ])),
          PopupMenuButton<String>(
            icon: Icon(Icons.more_vert_rounded, color: context.tokens.textTertiary),
            onSelected: (v) {
              if (v == 'edit') _showWorkoutSheet(workout: w);
              if (v == 'delete') _deleteWorkout(w);
            },
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'edit', child: Text('Edit')),
              PopupMenuItem(value: 'delete', child: Text('Delete')),
            ],
          ),
        ]),
        if ((w['notes'] ?? '').toString().isNotEmpty) ...[
          const SizedBox(height: 10),
          Text(w['notes'].toString(), style: context.typo.bodySmall?.copyWith(color: context.tokens.textSecondary)),
        ],
        const SizedBox(height: 12),
        Wrap(spacing: 8, runSpacing: 8, children: [
          StatusBadge('${plan.length} days', color: AppTheme.info),
          StatusBadge('$exerciseCount exercises', color: AppTheme.success),
        ]),
        const SizedBox(height: 12),
        ...plan.take(2).map((day) => _dayPreview(day)),
        if (plan.length > 2) Text('+ ${plan.length - 2} more days', style: context.typo.bodySmall?.copyWith(color: AppTheme.brand, fontWeight: FontWeight.w700)),
      ]),
    );
  }

  Widget _dayPreview(Map day) {
    final exercises = (day['exercises'] as List?) ?? [];
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(13)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(Icons.calendar_today_rounded, size: 14, color: AppTheme.brand),
          const SizedBox(width: 6),
          Text(day['day']?.toString() ?? 'Day', style: context.typo.titleSmall?.copyWith(fontSize: 13, color: AppTheme.brand)),
        ]),
        const SizedBox(height: 6),
        ...exercises.take(3).map((ex) => Text('• $ex', style: context.typo.bodySmall)),
      ]),
    );
  }

  void _showWorkoutSheet({Map<String, dynamic>? workout}) {
    final editing = workout != null;
    int? selectedMember = editing ? int.tryParse((workout['assign_id'] ?? '').toString()) : (members.isNotEmpty ? int.tryParse(members.first['id'].toString()) : null);
    DateTime start = _parseDate(workout?['start_date']) ?? DateTime.now();
    DateTime? end = _parseDate(workout?['end_date']);
    final notesCtrl = TextEditingController(text: workout?['notes'] ?? '');
    final plan = editing ? _parsePlan(workout['workout_history']) : [{'day': 'Monday', 'ctrl': TextEditingController()}];
    final days = plan.map<Map<String, dynamic>>((d) => {
      'day': d['day'] ?? 'Monday',
      'ctrl': TextEditingController(text: ((d['exercises'] as List?) ?? []).join('\n')),
    }).toList();
    const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            IconBadge(editing ? Icons.edit_rounded : Icons.add_rounded, color: AppTheme.brand),
            const SizedBox(width: 12),
            Text(editing ? 'Edit Workout Plan' : 'Create Workout Plan', style: context.typo.titleLarge),
          ]),
          const SizedBox(height: 16),
          DropdownButtonFormField<int>(
            value: selectedMember,
            decoration: const InputDecoration(labelText: 'Assigned Member', prefixIcon: Icon(Icons.person_rounded)),
            items: members.map((m) => DropdownMenuItem<int>(value: int.parse(m['id'].toString()), child: Text(m['name'] ?? 'Member'))).toList(),
            onChanged: editing ? null : (v) => setSheet(() => selectedMember = v),
          ),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: _dateBox('Start Date', start, () async { final d = await showDatePicker(context: ctx, initialDate: start, firstDate: DateTime(2020), lastDate: DateTime(2035)); if (d != null) setSheet(() => start = d); })),
            const SizedBox(width: 12),
            Expanded(child: _dateBox('End Date', end, () async { final d = await showDatePicker(context: ctx, initialDate: end ?? start, firstDate: start, lastDate: DateTime(2035)); if (d != null) setSheet(() => end = d); })),
          ]),
          const SizedBox(height: 14),
          Text('Plan Days', style: context.typo.labelMedium?.copyWith(color: context.tokens.textSecondary, fontWeight: FontWeight.w800)),
          const SizedBox(height: 8),
          ...days.asMap().entries.map((entry) {
            final i = entry.key;
            final d = entry.value;
            return Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(14)),
              child: Column(children: [
                Row(children: [
                  Expanded(child: DropdownButtonFormField<String>(value: d['day'], decoration: const InputDecoration(labelText: 'Day'), items: weekdays.map((w) => DropdownMenuItem(value: w, child: Text(w))).toList(), onChanged: (v) => setSheet(() => d['day'] = v ?? 'Monday'))),
                  if (days.length > 1) IconButton(onPressed: () => setSheet(() => days.removeAt(i)), icon: const Icon(Icons.remove_circle_outline_rounded, color: AppTheme.danger)),
                ]),
                const SizedBox(height: 8),
                TextField(controller: d['ctrl'] as TextEditingController, maxLines: 4, decoration: const InputDecoration(labelText: 'Exercises (one per line)', hintText: 'Bench Press 3x12\nSquats 4x10')),
              ]),
            );
          }),
          TextButton.icon(onPressed: () => setSheet(() => days.add({'day': weekdays[days.length % 7], 'ctrl': TextEditingController()})), icon: const Icon(Icons.add_rounded), label: const Text('Add Day')),
          const SizedBox(height: 8),
          TextField(controller: notesCtrl, decoration: const InputDecoration(labelText: 'Notes'), maxLines: 2),
          const SizedBox(height: 18),
          FireButton(label: editing ? 'Save Plan' : 'Create Plan', onPressed: () async {
            final built = days.map((d) {
              final exercises = (d['ctrl'] as TextEditingController).text.split('\n').map((e) => e.trim()).where((e) => e.isNotEmpty).toList();
              return {'day': d['day'], 'exercises': exercises};
            }).where((d) => (d['exercises'] as List).isNotEmpty).toList();
            if (selectedMember == null) { Toast.error(ctx, 'Select member'); return; }
            if (built.isEmpty) { Toast.error(ctx, 'Add at least one exercise'); return; }
            final payload = {
              'user_id': selectedMember,
              'workout_plan': jsonEncode(built),
              'start_date': _dateValue(start),
              'end_date': _dateValue(end),
              'notes': notesCtrl.text.trim(),
            };
            try {
              final api = ref.read(apiClientProvider);
              if (editing) {
                await api.updateTrainerWorkoutPlan(int.parse(workout['id'].toString()), payload);
              } else {
                await api.createTrainerWorkoutPlan(payload);
              }
              if (mounted) Navigator.pop(ctx);
              _load();
              if (mounted) Toast.success(context, editing ? 'Workout updated' : 'Workout created');
            } catch (e) { Toast.error(ctx, _friendlyError(e)); }
          }),
        ]),
      );
    }));
  }

  Future<void> _deleteWorkout(Map<String, dynamic> w) async {
    final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(title: const Text('Delete workout?'), content: const Text('This cannot be undone.'), actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete'))]));
    if (ok != true) return;
    try {
      await ref.read(apiClientProvider).deleteTrainerWorkoutPlan(int.parse(w['id'].toString()));
      _load();
      if (mounted) Toast.success(context, 'Workout deleted');
    } catch (e) { if (mounted) Toast.error(context, _friendlyError(e)); }
  }

  List<Map<String, dynamic>> _parsePlan(dynamic raw) {
    try {
      final decoded = raw is String ? jsonDecode(raw) : raw;
      if (decoded is List) return decoded.map((e) => Map<String, dynamic>.from(e as Map)).toList();
      if (decoded is Map) {
        return decoded.entries.map((e) => {'day': e.key.toString(), 'exercises': (e.value as List).map((x) => x.toString()).toList()}).toList();
      }
    } catch (_) {}
    return [];
  }

  DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty) return null;
    try { return DateTime.parse(v.toString().split('T').first); } catch (_) { return null; }
  }

  String? _dateValue(DateTime? d) {
    if (d == null) return null;
    return '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
  }

  Widget _dateBox(String label, DateTime? value, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: InputDecorator(
        decoration: InputDecoration(labelText: label, prefixIcon: const Icon(Icons.calendar_today_rounded)),
        child: Text(value == null ? 'Select' : DateFormatter.formatDate(_dateValue(value))),
      ),
    );
  }
}
