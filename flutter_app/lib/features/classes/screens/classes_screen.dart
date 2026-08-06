import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';

class ClassesScreen extends ConsumerStatefulWidget {
  const ClassesScreen({super.key});
  @override
  ConsumerState<ClassesScreen> createState() => _ClassesScreenState();
}

class _ClassesScreenState extends ConsumerState<ClassesScreen> {
  List classes = [];
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
      final api = ref.read(apiClientProvider);
      final results = await Future.wait([
        api.getClasses(),
        api.getTrainers(),
      ]);
      if (mounted) {
        setState(() {
          classes = results[0]['classes'] ?? [];
          trainers = results[1]['trainers'] ?? [];
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
        if (data['errors'] is Map) {
          final first = (data['errors'] as Map).values.first;
          if (first is List && first.isNotEmpty) return first.first.toString();
          return first.toString();
        }
      }
    } catch (_) {}
    final msg = e.toString();
    if (msg.contains('connection') || msg.contains('SocketException')) return 'No internet connection';
    return 'Could not load classes';
  }

  @override
  Widget build(BuildContext context) {
    final perms = ref.watch(permissionProvider);
    final canCreate = perms.can('classes.create');
    return Scaffold(
      body: loading
          ? const SkeletonList()
          : error != null
              ? ErrorRetry(message: error!, onRetry: _load)
              : classes.isEmpty
                  ? EmptyState(icon: Icons.self_improvement_rounded, title: 'No classes yet', subtitle: canCreate ? 'Add yoga, zumba, HIIT or other group classes' : 'No classes available for your role', actionLabel: canCreate ? 'Add Class' : null, onAction: canCreate ? _showAdd : null)
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                        itemCount: classes.length + 1,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (ctx, i) {
                          if (i == 0) return _summaryCard();
                          final c = Map<String, dynamic>.from(classes[i - 1] as Map);
                          final color = AppTheme.categoryColors[(i - 1) % AppTheme.categoryColors.length];
                          return FadeInUp(delayMs: ((i - 1) * 22).clamp(0, 240), offset: 10, child: _classCard(c, color));
                        },
                      ),
                    ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(
              onPressed: _showAdd,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Add Class', style: TextStyle(fontWeight: FontWeight.w700)),
              backgroundColor: AppTheme.brand,
            )
          : null,
    );
  }

  Widget _summaryCard() {
    final totalMembers = classes.fold<int>(0, (sum, c) => sum + (int.tryParse(((c as Map)['member_count'] ?? c['assigned_count'] ?? 0).toString()) ?? 0));
    final assignedTrainers = classes.fold<int>(0, (sum, c) => sum + _intList((c as Map)['trainer_ids']).length);
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)),
      child: Row(children: [
        IconBadge(Icons.self_improvement_rounded, color: AppTheme.brandAmber, size: 54, iconSize: 28),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Group Classes', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 23, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text('${classes.length} classes • $totalMembers member seats • $assignedTrainers trainer assignments', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.68), fontSize: 12.2)),
        ])),
      ]),
    );
  }

  Widget _classCard(Map<String, dynamic> c, Color color) {
    final schedules = _mapList(c['schedules']);
    final trainerNames = _stringList(c['trainer_names']);
    final memberCount = c['member_count'] ?? c['assigned_count'] ?? 0;
    final perms = ref.watch(permissionProvider);
    final canEdit = perms.can('classes.edit');
    final canDelete = perms.can('classes.delete');

    return SurfaceCard(
      padding: const EdgeInsets.all(14),
      onTap: () => _showClassDetail(c, color),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          IconBadge(Icons.self_improvement_rounded, color: color, size: 48, iconSize: 23),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(c['title']?.toString() ?? '', style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
            const SizedBox(height: 3),
            Text('₹${_amount(c['fees'])} • $memberCount members${(c['address'] ?? '').toString().isNotEmpty ? ' • ${c['address']}' : ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary), maxLines: 1, overflow: TextOverflow.ellipsis),
          ])),
          if (canEdit || canDelete)
            PopupMenuButton<String>(
              icon: Icon(Icons.more_vert_rounded, size: 20, color: context.tokens.textTertiary),
              onSelected: (v) {
                if (v == 'edit') _showEdit(Map<String, dynamic>.from(c));
                if (v == 'delete') _deleteClass(c);
              },
              itemBuilder: (_) => [
                if (canEdit) const PopupMenuItem(value: 'edit', child: Text('Edit')),
                if (canDelete) const PopupMenuItem(value: 'delete', child: Text('Delete', style: TextStyle(color: AppTheme.danger))),
              ],
            ),
        ]),
        if (trainerNames.isNotEmpty) ...[
          const SizedBox(height: 10),
          Wrap(spacing: 6, runSpacing: 6, children: trainerNames.map((name) => StatusBadge(name, color: AppTheme.success, icon: Icons.sports_martial_arts_rounded)).toList()),
        ],
        if (schedules.isNotEmpty) ...[
          const SizedBox(height: 10),
          Wrap(spacing: 6, runSpacing: 6, children: schedules.take(2).map((s) => _scheduleChip(s)).toList()),
        ],
      ]),
    );
  }

  Widget _scheduleChip(Map<String, dynamic> s) {
    final days = (s['days'] ?? '').toString();
    final start = DateFormatter.formatTime(s['start_time']?.toString());
    final end = DateFormatter.formatTime(s['end_time']?.toString());
    final label = '$days • $start - $end';
    return StatusBadge(label, color: AppTheme.info, icon: Icons.schedule_rounded);
  }

  void _showAdd() => _showClassSheet();

  void _showEdit(Map c) => _showClassSheet(cls: c);

  Future<void> _deleteClass(Map c) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Class?'),
        content: const Text('Classes with enrolled members cannot be deleted.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(apiClientProvider).deleteClass(c['id']);
      _load();
      if (mounted) Toast.success(context, 'Class deleted');
    } catch (e) {
      if (mounted) Toast.error(context, _friendlyError(e));
    }
  }

  void _showClassDetail(Map<String, dynamic> c, Color color) {
    final schedules = _mapList(c['schedules']);
    final trainersForClass = _mapList(c['trainers']);
    showAppSheet(context, child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          IconBadge(Icons.self_improvement_rounded, color: color, size: 56, iconSize: 28),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(c['title']?.toString() ?? 'Class', style: context.typo.titleLarge),
            Text('₹${_amount(c['fees'])} • ${c['member_count'] ?? c['assigned_count'] ?? 0} members', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          ])),
        ]),
        const SizedBox(height: 16),
        if ((c['address'] ?? '').toString().isNotEmpty) _infoTile(Icons.location_on_rounded, 'Location', c['address'].toString()),
        if ((c['notes'] ?? '').toString().isNotEmpty) _infoTile(Icons.notes_rounded, 'Notes', c['notes'].toString()),
        const SizedBox(height: 12),
        SectionHeader('Assigned Trainers'),
        const SizedBox(height: 10),
        if (trainersForClass.isEmpty)
          Text('No trainer assigned yet', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))
        else
          Wrap(spacing: 8, runSpacing: 8, children: trainersForClass.map((t) => StatusBadge(t['name']?.toString() ?? 'Trainer', color: AppTheme.success, icon: Icons.sports_martial_arts_rounded)).toList()),
        const SizedBox(height: 18),
        SectionHeader('Schedule'),
        const SizedBox(height: 10),
        if (schedules.isEmpty)
          Text('No schedule added', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))
        else
          ...schedules.map((s) => Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: SurfaceCard(
              padding: const EdgeInsets.all(10),
              color: context.tokens.surfaceAlt,
              shadow: false,
              child: Row(children: [
                IconBadge(Icons.schedule_rounded, color: AppTheme.info, size: 36, iconSize: 17),
                const SizedBox(width: 10),
                Expanded(child: Text(s['days']?.toString() ?? '', style: context.typo.titleSmall)),
                Text('${DateFormatter.formatTime(s['start_time']?.toString())} - ${DateFormatter.formatTime(s['end_time']?.toString())}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
              ]),
            ),
          )),
      ]),
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

  void _showClassSheet({Map? cls}) {
    final editing = cls != null;
    final titleCtrl = TextEditingController(text: cls?['title'] ?? '');
    final feesCtrl = TextEditingController(text: cls != null ? '${cls['fees'] ?? ''}' : '');
    final addressCtrl = TextEditingController(text: cls?['address'] ?? '');
    final notesCtrl = TextEditingController(text: cls?['notes'] ?? '');
    final firstSchedule = _mapList(cls?['schedules']).isNotEmpty ? _mapList(cls?['schedules']).first : <String, dynamic>{};
    final daysCtrl = TextEditingController(text: firstSchedule['days']?.toString() ?? '');
    final startCtrl = TextEditingController(text: firstSchedule['start_time']?.toString() ?? '');
    final endCtrl = TextEditingController(text: firstSchedule['end_time']?.toString() ?? '');
    final selectedTrainerIds = _intList(cls?['trainer_ids']).toSet();

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(editing ? Icons.edit_rounded : Icons.self_improvement_rounded, color: AppTheme.brand), const SizedBox(width: 12), Expanded(child: Text(editing ? 'Edit Class' : 'Add Class', style: context.typo.titleLarge))]),
          const SizedBox(height: 18),
          _sheetLabel('Class Details'),
          TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: 'Class Name*', prefixIcon: Icon(Icons.self_improvement_rounded))),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: TextField(controller: feesCtrl, decoration: const InputDecoration(labelText: 'Fees', prefixText: '₹ '), keyboardType: TextInputType.number)),
            const SizedBox(width: 12),
            Expanded(child: TextField(controller: addressCtrl, decoration: const InputDecoration(labelText: 'Location'))),
          ]),
          const SizedBox(height: 12),
          TextField(controller: notesCtrl, decoration: const InputDecoration(labelText: 'Notes'), maxLines: 2),
          const SizedBox(height: 16),
          _sheetLabel('Schedule'),
          TextField(controller: daysCtrl, decoration: const InputDecoration(labelText: 'Days', hintText: 'Mon,Wed,Fri', prefixIcon: Icon(Icons.calendar_month_rounded))),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: TextField(controller: startCtrl, decoration: const InputDecoration(labelText: 'Start Time', hintText: '06:00'), keyboardType: TextInputType.datetime)),
            const SizedBox(width: 12),
            Expanded(child: TextField(controller: endCtrl, decoration: const InputDecoration(labelText: 'End Time', hintText: '07:00'), keyboardType: TextInputType.datetime)),
          ]),
          const SizedBox(height: 16),
          _sheetLabel('Assign Trainers'),
          if (trainers.isEmpty)
            SurfaceCard(
              color: AppTheme.warning.withOpacity(0.08),
              border: Border.all(color: AppTheme.warning.withOpacity(0.18)),
              child: Text('No trainers found. Add trainers first to assign classes.', style: context.typo.bodySmall?.copyWith(color: AppTheme.warning, fontWeight: FontWeight.w700)),
            )
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: trainers.map((trainer) {
                final id = int.tryParse((trainer['id'] ?? '').toString());
                final selected = id != null && selectedTrainerIds.contains(id);
                return FilterChip(
                  selected: selected,
                  avatar: Icon(selected ? Icons.check_circle_rounded : Icons.sports_martial_arts_rounded, size: 17, color: selected ? AppTheme.success : context.tokens.textTertiary),
                  label: Text(trainer['name']?.toString() ?? 'Trainer'),
                  onSelected: id == null ? null : (v) => setSheet(() {
                    if (v) {
                      selectedTrainerIds.add(id);
                    } else {
                      selectedTrainerIds.remove(id);
                    }
                  }),
                  selectedColor: AppTheme.success.withOpacity(0.16),
                  backgroundColor: context.tokens.surfaceAlt,
                  labelStyle: context.typo.bodySmall?.copyWith(fontWeight: FontWeight.w700, color: selected ? AppTheme.success : context.tokens.textSecondary),
                  side: BorderSide(color: selected ? AppTheme.success.withOpacity(0.35) : context.tokens.border),
                );
              }).toList(),
            ),
          const SizedBox(height: 20),
          FireButton(label: editing ? 'Save Changes' : 'Add Class', icon: editing ? Icons.save_rounded : Icons.add_rounded, onPressed: () async {
            if (titleCtrl.text.trim().isEmpty) { Toast.error(ctx, 'Class name required'); return; }
            final hasSchedule = daysCtrl.text.trim().isNotEmpty || startCtrl.text.trim().isNotEmpty || endCtrl.text.trim().isNotEmpty;
            if (hasSchedule && (daysCtrl.text.trim().isEmpty || startCtrl.text.trim().isEmpty || endCtrl.text.trim().isEmpty)) {
              Toast.error(ctx, 'Days, start time and end time are required for schedule');
              return;
            }
            final data = {
              'title': titleCtrl.text.trim(),
              'fees': double.tryParse(feesCtrl.text.trim()) ?? 0,
              'address': addressCtrl.text.trim(),
              'notes': notesCtrl.text.trim(),
              'trainer_ids': selectedTrainerIds.toList(),
              if (hasSchedule) 'days': daysCtrl.text.trim(),
              if (hasSchedule) 'start_time': _cleanTimeInput(startCtrl.text.trim()),
              if (hasSchedule) 'end_time': _cleanTimeInput(endCtrl.text.trim()),
            };
            try {
              final api = ref.read(apiClientProvider);
              if (editing) {
                await api.updateClass(cls['id'], data);
              } else {
                await api.createClass(data);
              }
              if (mounted) Navigator.pop(ctx);
              _load();
              if (mounted) Toast.success(context, editing ? 'Class updated' : 'Class added');
            } catch (e) {
              Toast.error(ctx, _friendlyError(e));
            }
          }),
        ]),
      );
    }));
  }

  Widget _sheetLabel(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(text, style: context.typo.labelMedium?.copyWith(color: context.tokens.textSecondary, fontWeight: FontWeight.w800)),
      );

  List<Map<String, dynamic>> _mapList(dynamic raw) {
    if (raw is! List) return [];
    return raw.where((e) => e is Map).map<Map<String, dynamic>>((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  List<int> _intList(dynamic raw) {
    if (raw is! List) return [];
    return raw.map((e) => int.tryParse(e.toString())).whereType<int>().toList();
  }

  List<String> _stringList(dynamic raw) {
    if (raw is List) return raw.map((e) => e.toString()).where((e) => e.trim().isNotEmpty).toList();
    return [];
  }

  String _amount(dynamic value) {
    final num? n = value is num ? value : num.tryParse(value?.toString() ?? '0');
    if (n == null) return '0';
    if (n % 1 == 0) return n.toInt().toString();
    return n.toStringAsFixed(2);
  }

  String _cleanTimeInput(String value) {
    if (value.length >= 5) return value.substring(0, 5);
    return value;
  }
}
