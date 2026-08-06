import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';
import 'package:gymxbook/features/members/screens/member_detail_screen.dart';
import 'add_trainer_screen.dart';

class TrainerDetailScreen extends ConsumerStatefulWidget {
  final int trainerId;
  final String trainerName;
  const TrainerDetailScreen({super.key, required this.trainerId, required this.trainerName});

  @override
  ConsumerState<TrainerDetailScreen> createState() => _TrainerDetailScreenState();
}

class _TrainerDetailScreenState extends ConsumerState<TrainerDetailScreen> {
  Map<String, dynamic>? trainer;
  bool loading = true;
  String? error;
  bool actionBusy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getTrainer(widget.trainerId);
      final t = res['trainer'] ?? res;
      if (mounted) setState(() { trainer = Map<String, dynamic>.from(t as Map); loading = false; });
    } catch (e) {
      if (mounted) setState(() { error = _friendly(e); loading = false; });
    }
  }

  String _friendly(dynamic e) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) {
        final msg = data['error'] ?? data['message'];
        if (msg != null) return msg.toString();
      }
    } catch (_) {}
    final msg = e.toString();
    if (msg.contains('connection') || msg.contains('SocketException')) return 'No internet. Please check connection.';
    return 'Failed. Please try again.';
  }

  String get _phone => (trainer?['phone_number'] ?? '').toString().trim();
  bool get _active => trainer?['is_active'] == true || trainer?['is_active'].toString() == '1';
  int get _membersCount => int.tryParse((trainer?['assigned_members_count'] ?? 0).toString()) ?? 0;
  int get _classesCount => int.tryParse((trainer?['assigned_classes_count'] ?? 0).toString()) ?? 0;
  bool get _canDelete => trainer?['can_delete'] == true || _membersCount == 0;

  Future<void> _callTrainer() async {
    if (_phone.isEmpty) return;
    await launchUrl(Uri.parse('tel:$_phone'));
  }

  Future<void> _whatsappTrainer() async {
    if (_phone.isEmpty) return;
    final digits = _phone.replaceAll(RegExp(r'[^0-9]'), '');
    final phone = digits.length == 10 ? '91$digits' : digits;
    await launchUrl(Uri.parse('https://wa.me/$phone'), mode: LaunchMode.externalApplication);
  }

  Future<void> _editTrainer() async {
    if (trainer == null) return;
    final changed = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => AddTrainerScreen(trainer: trainer)));
    if (changed == true) _load();
  }

  Future<void> _toggleTrainer() async {
    if (trainer == null || actionBusy) return;
    setState(() => actionBusy = true);
    try {
      await ref.read(apiClientProvider).toggleTrainer(widget.trainerId);
      if (mounted) Toast.success(context, _active ? 'Trainer deactivated' : 'Trainer activated');
      await _load();
    } catch (e) {
      if (mounted) Toast.error(context, _friendly(e));
    } finally {
      if (mounted) setState(() => actionBusy = false);
    }
  }

  Future<void> _deleteTrainer() async {
    if (trainer == null || actionBusy) return;
    if (!_canDelete) {
      Toast.error(context, 'Cannot delete trainer while $_membersCount member${_membersCount == 1 ? '' : 's'} assigned.');
      return;
    }
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Trainer?'),
        content: Text('Delete ${trainer!['name'] ?? 'this trainer'}? This is allowed only when no members are assigned.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete')),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => actionBusy = true);
    try {
      await ref.read(apiClientProvider).deleteTrainer(widget.trainerId);
      if (!mounted) return;
      Toast.success(context, 'Trainer deleted');
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) Toast.error(context, _friendly(e));
    } finally {
      if (mounted) setState(() => actionBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.trainerName)),
      body: loading
          ? const SkeletonList(count: 5)
          : error != null
              ? ErrorRetry(message: error!, onRetry: _load)
              : trainer == null
                  ? const EmptyState(icon: Icons.person_off_rounded, title: 'Trainer not found')
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: _load,
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
                        children: [
                          FadeInUp(child: _header()),
                          const SizedBox(height: 14),
                          FadeInUp(delayMs: 60, child: _actions()),
                          const SizedBox(height: 14),
                          FadeInUp(delayMs: 90, child: _profileSection()),
                          const SizedBox(height: 12),
                          FadeInUp(delayMs: 120, child: _assignedMembersSection()),
                          const SizedBox(height: 12),
                          FadeInUp(delayMs: 150, child: _assignedClassesSection()),
                          const SizedBox(height: 22),
                          FadeInUp(delayMs: 180, child: _deleteSection()),
                        ],
                      ),
                    ),
    );
  }

  Widget _header() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(26)),
      child: Column(children: [
        Row(children: [
          GxAvatar(name: trainer!['name'] ?? 'T', size: 64),
          const SizedBox(width: 16),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(trainer!['name'] ?? '', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 21, fontWeight: FontWeight.w800)),
            const SizedBox(height: 4),
            Text((trainer!['specialization'] ?? '').toString().isEmpty ? 'Fitness Trainer' : trainer!['specialization'].toString(), style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.72), fontSize: 12.5)),
            if (_phone.isNotEmpty) Text(_phone, style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.72), fontSize: 12.5)),
          ])),
          StatusBadge(_active ? 'Active' : 'Inactive', color: _active ? AppTheme.success : AppTheme.danger),
        ]),
        if (_phone.isNotEmpty) ...[
          const SizedBox(height: 16),
          Row(children: [
            Expanded(child: _contactBtn(Icons.call_rounded, 'Call', AppTheme.success, _callTrainer)),
            const SizedBox(width: 10),
            Expanded(child: _contactBtn(Icons.chat_rounded, 'WhatsApp', const Color(0xFF25D366), _whatsappTrainer)),
          ]),
        ],
        const SizedBox(height: 16),
        Row(children: [
          _quickStat('Members', '$_membersCount', Icons.people_rounded),
          const SizedBox(width: 12),
          _quickStat('Classes', '$_classesCount', Icons.self_improvement_rounded),
        ]),
      ]),
    );
  }

  Widget _contactBtn(IconData icon, String label, Color color, VoidCallback onTap) {
    return Pressable(radius: 14, onTap: onTap, child: Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      alignment: Alignment.center,
      decoration: BoxDecoration(color: color.withOpacity(0.2), borderRadius: BorderRadius.circular(14), border: Border.all(color: color.withOpacity(0.4))),
      child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(icon, size: 18, color: Colors.white), const SizedBox(width: 8), Text(label, style: GoogleFonts.poppins(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13.5))]),
    ));
  }

  Widget _quickStat(String label, String value, IconData icon) {
    return Expanded(child: Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: Colors.white.withOpacity(0.08), borderRadius: BorderRadius.circular(16), border: Border.all(color: Colors.white.withOpacity(0.10))),
      child: Row(children: [
        Icon(icon, size: 18, color: AppTheme.brandAmber),
        const SizedBox(width: 9),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: GoogleFonts.poppins(fontSize: 10.5, color: Colors.white.withOpacity(0.6), fontWeight: FontWeight.w500)),
          Text(value, style: GoogleFonts.poppins(fontSize: 14, fontWeight: FontWeight.w800, color: Colors.white), overflow: TextOverflow.ellipsis),
        ])),
      ]),
    ));
  }

  Widget _actions() {
    final perms = ref.watch(permissionProvider);
    final canEdit = perms.can('trainers.edit');
    if (!canEdit) {
      return SurfaceCard(
        color: AppTheme.info.withOpacity(0.08),
        border: Border.all(color: AppTheme.info.withOpacity(0.18)),
        child: Text('You have view-only access for trainers.', style: context.typo.bodySmall?.copyWith(color: AppTheme.info, fontWeight: FontWeight.w700)),
      );
    }
    return Row(children: [
      Expanded(child: FireButton(label: 'Edit', icon: Icons.edit_rounded, onPressed: _editTrainer)),
      const SizedBox(width: 10),
      Expanded(child: FireButton(label: _active ? 'Deactivate' : 'Activate', icon: _active ? Icons.toggle_off_rounded : Icons.toggle_on_rounded, gradient: _active ? AppTheme.amberGradient : AppTheme.fireGradient, loading: actionBusy, onPressed: actionBusy ? null : _toggleTrainer)),
    ]);
  }

  Widget _profileSection() {
    final salary = double.tryParse((trainer!['salary'] ?? 0).toString()) ?? 0;
    return SurfaceCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [IconBadge(Icons.badge_rounded, color: AppTheme.brand, size: 36, iconSize: 18), const SizedBox(width: 10), Text('Trainer Details', style: context.typo.titleMedium)]),
      _info('Email', trainer!['email'] ?? '-'),
      _info('Gender', _title(trainer!['gender'] ?? '-')),
      _info('DOB', DateFormatter.formatDate(trainer!['dob']?.toString())),
      _info('Qualification', trainer!['qualification'] ?? '-'),
      _info('Specialization', trainer!['specialization'] ?? '-'),
      _info('Experience', '${trainer!['experience_years'] ?? 0} years'),
      _info('Joining Date', DateFormatter.formatDate(trainer!['joining_date']?.toString())),
      _info('Salary', '₹${salary.toStringAsFixed(0)}'),
      _info('City', trainer!['city'] ?? '-'),
      _info('Emergency Contact', trainer!['emergency_contact'] ?? '-'),
      if ((trainer!['address'] ?? '').toString().isNotEmpty) _info('Address', trainer!['address']),
      if ((trainer!['bio'] ?? '').toString().isNotEmpty) _info('Bio', trainer!['bio']),
    ]));
  }

  Widget _info(String label, dynamic value) {
    final text = (value ?? '-').toString();
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        SizedBox(width: 120, child: Text(label, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))),
        Expanded(child: Text(text.isEmpty ? '-' : text, style: context.typo.titleSmall?.copyWith(fontSize: 13.5))),
      ]),
    );
  }

  Widget _assignedMembersSection() {
    final members = (trainer!['assigned_members'] as List?) ?? [];
    return SurfaceCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [IconBadge(Icons.people_rounded, color: AppTheme.info, size: 36, iconSize: 18), const SizedBox(width: 10), Text('Assigned Members', style: context.typo.titleMedium), const Spacer(), if (members.isNotEmpty) StatusBadge('${members.length}', color: AppTheme.info)]),
      if (members.isEmpty)
        Padding(padding: const EdgeInsets.only(top: 12), child: Text('No members assigned to this trainer', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)))
      else
        ...members.map((m) => _memberTile(Map<String, dynamic>.from(m as Map))),
    ]));
  }

  Widget _memberTile(Map<String, dynamic> m) {
    final id = int.tryParse((m['id'] ?? 0).toString()) ?? 0;
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: SurfaceCard(
        padding: const EdgeInsets.all(10),
        color: context.tokens.surfaceAlt,
        shadow: false,
        onTap: id > 0 ? () => Navigator.push(context, MaterialPageRoute(builder: (_) => MemberDetailScreen(memberId: id, memberName: m['name'] ?? 'Member'))) : null,
        child: Row(children: [
          GxAvatar(name: m['name'] ?? 'M', size: 38),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(m['name'] ?? '', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
            Text('${m['phone_number'] ?? ''}${(m['plan_name'] ?? '').toString().isNotEmpty ? ' • ${m['plan_name']}' : ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5)),
          ])),
          Icon(Icons.chevron_right_rounded, color: context.tokens.textTertiary),
        ]),
      ),
    );
  }

  Widget _assignedClassesSection() {
    final classes = (trainer!['assigned_classes'] as List?) ?? [];
    return SurfaceCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [IconBadge(Icons.self_improvement_rounded, color: AppTheme.success, size: 36, iconSize: 18), const SizedBox(width: 10), Text('Assigned Classes', style: context.typo.titleMedium), const Spacer(), if (classes.isNotEmpty) StatusBadge('${classes.length}', color: AppTheme.success)]),
      if (classes.isEmpty)
        Padding(padding: const EdgeInsets.only(top: 12), child: Text('No classes assigned to this trainer', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)))
      else
        ...classes.map((c) => _classTile(Map<String, dynamic>.from(c as Map))),
    ]));
  }

  Widget _classTile(Map<String, dynamic> c) {
    final schedules = (c['schedules'] as List?) ?? [];
    final scheduleText = schedules.isEmpty
        ? 'No schedule'
        : schedules.map((s) => '${s['days'] ?? ''} ${DateFormatter.formatTime(s['start_time']?.toString())}-${DateFormatter.formatTime(s['end_time']?.toString())}').join(' • ');
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        IconBadge(Icons.calendar_month_rounded, color: AppTheme.success, size: 34, iconSize: 16),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(c['title'] ?? '', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
          Text(scheduleText, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5)),
        ])),
      ]),
    );
  }

  Widget _deleteSection() {
    if (!ref.watch(permissionProvider).can('trainers.delete')) return const SizedBox.shrink();
    return OutlinedButton.icon(
      style: OutlinedButton.styleFrom(
        foregroundColor: _canDelete ? AppTheme.danger : context.tokens.textTertiary,
        side: BorderSide(color: (_canDelete ? AppTheme.danger : context.tokens.textTertiary).withOpacity(0.35)),
        padding: const EdgeInsets.symmetric(vertical: 15),
      ),
      onPressed: actionBusy ? null : _deleteTrainer,
      icon: Icon(_canDelete ? Icons.delete_outline_rounded : Icons.lock_outline_rounded),
      label: Text(_canDelete ? 'Delete Trainer' : 'Cannot Delete • Members Assigned'),
    );
  }

  String _title(dynamic value) {
    final s = value.toString();
    if (s.isEmpty) return '-';
    return '${s[0].toUpperCase()}${s.substring(1)}';
  }
}
