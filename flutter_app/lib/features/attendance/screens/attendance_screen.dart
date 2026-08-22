import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';

class AttendanceScreen extends ConsumerStatefulWidget {
  const AttendanceScreen({super.key});
  @override
  ConsumerState<AttendanceScreen> createState() => _AttendanceScreenState();
}

class _AttendanceScreenState extends ConsumerState<AttendanceScreen> {
  DateTime selectedDate = DateTime.now();
  List attendance = [];
  bool loading = true;
  String? error;
  List searchResults = [];
  final Set<int> _attendanceBusyIds = <int>{};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final api = ref.read(apiClientProvider);
      final dateStr = "${selectedDate.year}-${selectedDate.month.toString().padLeft(2, '0')}-${selectedDate.day.toString().padLeft(2, '0')}";
      final res = await api.getAttendance(date: dateStr);
      if (mounted) setState(() { attendance = res['attendance'] ?? []; loading = false; });
    } catch (e) {
      if (mounted) setState(() { error = e.toString(); loading = false; });
    }
  }

  Future<void> _search(String q) async {
    if (q.isEmpty) { setState(() => searchResults = []); return; }
    try {
      final api = ref.read(apiClientProvider);
      final res = await api.searchAttendance(q);
      final users = (res['users'] ?? res['data'] ?? []) as List;
      setState(() => searchResults = users);
    } catch (e) {
      // Still allow fallback empty
      setState(() => searchResults = []);
    }
  }

  String _friendlyError(dynamic e) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) {
        final msg = data['error'] ?? data['message'];
        if (msg != null && msg.toString().trim().isNotEmpty) return msg.toString();
      }
      final status = (e as dynamic).response?.statusCode;
      if (status == 401) return 'Session expired. Please login again.';
      if (status == 403) return 'You do not have permission for this action.';
      if (status == 404) return 'Attendance record not found.';
    } catch (_) {}

    final text = e.toString().toLowerCase();
    if (text.contains('connection') || text.contains('socket') || text.contains('network')) {
      return 'No internet connection. Please try again.';
    }
    return 'Failed. Please try again.';
  }

  Future<void> _checkIn(int userId, String name) async {
    if (!ref.read(permissionProvider).can('attendance.mark')) {
      Toast.error(context, 'You do not have permission to mark attendance');
      return;
    }
    if (_attendanceBusyIds.contains(userId)) return;
    setState(() => _attendanceBusyIds.add(userId));
    try {
      await ref.read(apiClientProvider).postAttendance(userId: userId);
      if (mounted) {
        Toast.success(context, '$name checked in');
        await _load();
        setState(() => searchResults = []);
      }
    } catch (e) {
      if (mounted) Toast.error(context, _friendlyError(e));
    } finally {
      if (mounted) setState(() => _attendanceBusyIds.remove(userId));
    }
  }

  void _showOptions(Map att) {
    final perms = ref.read(permissionProvider);
    final canEdit = perms.can('attendance.edit');
    final canDelete = perms.can('attendance.delete');
    if (!canEdit && !canDelete) {
      Toast.info(context, 'No actions available for your role');
      return;
    }
    showAppSheet(context, child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [GxAvatar(name: att['name'] ?? 'M', size: 44), const SizedBox(width: 12), Text(att['name'] ?? 'Attendance', style: context.typo.titleLarge)]),
        const SizedBox(height: 18),
        if (canEdit) _sheetAction(Icons.edit_rounded, 'Edit Record', AppTheme.info, () { Navigator.pop(context); _edit(att); }),
        if (canEdit && canDelete) const SizedBox(height: 8),
        if (canDelete) _sheetAction(Icons.delete_rounded, 'Delete Record', AppTheme.danger, () { Navigator.pop(context); _delete(att['id']); }),
      ]),
    ));
  }

  Widget _sheetAction(IconData icon, String label, Color color, VoidCallback onTap) {
    return Pressable(onTap: onTap, radius: 16, child: Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: color.withOpacity(0.09), borderRadius: BorderRadius.circular(16)),
      child: Row(children: [IconBadge(icon, color: color, size: 38, iconSize: 18), const SizedBox(width: 12), Text(label, style: context.typo.titleSmall?.copyWith(color: color))]),
    ));
  }

  Future<void> _checkOut(Map att) async {
    if (!ref.read(permissionProvider).can('attendance.mark')) {
      Toast.error(context, 'You do not have permission to mark attendance');
      return;
    }
    final rawUserId = att['user_id'] ?? att['id']; // support both attendance row and search result shapes
    final userId = rawUserId is int ? rawUserId : int.tryParse(rawUserId.toString()) ?? 0;
    if (userId <= 0 || _attendanceBusyIds.contains(userId)) return;
    setState(() => _attendanceBusyIds.add(userId));
    try {
      await ref.read(apiClientProvider).postAttendance(
        userId: userId,
        type: 'checkout',
        notes: 'Manual checkout',
      );

      if (mounted) Toast.success(context, '${att['name'] ?? 'Member'} checked out');
      await _load();
      if (mounted) setState(() => searchResults = []);
    } catch (e) {
      if (mounted) Toast.error(context, _friendlyError(e));
    } finally {
      if (mounted) setState(() => _attendanceBusyIds.remove(userId));
    }
  }

  Future<void> _edit(Map att) async {
    final dateCtrl = TextEditingController(text: att['date']);
    final notesCtrl = TextEditingController(text: att['notes'] ?? 'QR Scan');
    TimeOfDay inTime = _parseTime(att['checked_in_time']);
    TimeOfDay? outTime = att['checked_out_time'] != null ? _parseTime(att['checked_out_time']) : null;

    final ok = await showDialog<bool>(context: context, builder: (ctx) => StatefulBuilder(builder: (ctx, setDialog) {
      return AlertDialog(
        title: const Text('Edit Attendance'),
        content: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: dateCtrl, decoration: const InputDecoration(labelText: 'Date (YYYY-MM-DD)')),
          const SizedBox(height: 12),
          ListTile(
            dense: true, contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.access_time_rounded, color: AppTheme.success),
            title: const Text('Check-in Time'),
            subtitle: Text(inTime.format(ctx), style: const TextStyle(fontWeight: FontWeight.w600)),
            onTap: () async { final picked = await showTimePicker(context: ctx, initialTime: inTime); if (picked != null) setDialog(() => inTime = picked); },
          ),
          ListTile(
            dense: true, contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.logout_rounded, color: AppTheme.warning),
            title: const Text('Check-out Time'),
            subtitle: Text(outTime != null ? outTime!.format(ctx) : 'Not checked out', style: const TextStyle(fontWeight: FontWeight.w600)),
            onTap: () async { final picked = await showTimePicker(context: ctx, initialTime: outTime ?? TimeOfDay.now()); if (picked != null) setDialog(() => outTime = picked); },
          ),
          const SizedBox(height: 8),
          TextField(controller: notesCtrl, decoration: const InputDecoration(labelText: 'Notes')),
        ])),
        actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Save'))],
      );
    }));
    if (ok != true) return;
    try {
      // Laravel REST: PUT /v1/attendance/{id}
      await ref.read(apiClientProvider).updateAttendance(att['id'], {
        'date': dateCtrl.text.trim(),
        'checked_in_time': '${inTime.hour.toString().padLeft(2, '0')}:${inTime.minute.toString().padLeft(2, '0')}:00',
        'checked_out_time': outTime != null ? '${outTime!.hour.toString().padLeft(2, '0')}:${outTime!.minute.toString().padLeft(2, '0')}:00' : null,
        'notes': notesCtrl.text.trim(),
      });
      if (mounted) Toast.success(context, 'Updated');
      _load();
    } catch (e) { if (mounted) Toast.error(context, _friendlyError(e)); }
  }

  TimeOfDay _parseTime(dynamic timeStr) {
    if (timeStr == null) return TimeOfDay.now();
    final parts = timeStr.toString().split(':');
    return TimeOfDay(hour: int.tryParse(parts[0]) ?? 0, minute: int.tryParse(parts[1]) ?? 0);
  }

  Future<void> _delete(int id) async {
    final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(title: const Text('Delete Attendance?'), content: const Text('This cannot be undone.'), actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete'))]));
    if (ok != true) return;
    try {
      // Laravel REST: DELETE /v1/attendance/{id}
      await ref.read(apiClientProvider).deleteAttendance(id);
      if (mounted) Toast.success(context, 'Deleted');
      _load();
    } catch (e) { if (mounted) Toast.error(context, _friendlyError(e)); }
  }

  @override
  Widget build(BuildContext context) {
    final total = attendance.length;
    final checkedIn = attendance.where((a) => a['checked_out_time'] == null).length;
    final checkedOut = total - checkedIn;
    final canMark = ref.watch(permissionProvider).can('attendance.mark');
    final isToday = selectedDate.year == DateTime.now().year && selectedDate.month == DateTime.now().month && selectedDate.day == DateTime.now().day;

    return Scaffold(
      body: Column(
        children: [
          // Date navigator + stats
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(22)),
              child: Column(children: [
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  _navBtn(Icons.chevron_left_rounded, () { setState(() => selectedDate = selectedDate.subtract(const Duration(days: 1))); _load(); }),
                  GestureDetector(
                    onTap: () async {
                      final picked = await showDatePicker(context: context, initialDate: selectedDate, firstDate: DateTime(2020), lastDate: DateTime.now());
                      if (picked != null) { setState(() => selectedDate = picked); _load(); }
                    },
                    child: Column(children: [
                      Text("${selectedDate.day.toString().padLeft(2, '0')}-${selectedDate.month.toString().padLeft(2, '0')}-${selectedDate.year}", style: GoogleFonts.spaceGrotesk(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 18)),
                      const SizedBox(height: 2),
                      Row(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.calendar_today_rounded, size: 11, color: (Colors.white).withOpacity(0.6)), const SizedBox(width: 4), Text(isToday ? 'Today' : 'Tap to change', style: GoogleFonts.poppins(fontSize: 10.5, color: (Colors.white).withOpacity(0.6)))]),
                    ]),
                  ),
                  _navBtn(Icons.chevron_right_rounded, isToday ? null : () { setState(() => selectedDate = selectedDate.add(const Duration(days: 1))); _load(); }),
                ]),
                const SizedBox(height: 16),
                Row(children: [
                  _attStat('Total', total, Icons.groups_rounded),
                  _attStat('Inside', checkedIn, Icons.login_rounded),
                  _attStat('Left', checkedOut, Icons.logout_rounded),
                ]),
              ]),
            ),
          ),
          if (canMark)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              child: TextField(decoration: const InputDecoration(hintText: 'Search member to check in…', prefixIcon: Icon(Icons.search_rounded, size: 20)), onChanged: _search),
            ),
          if (canMark && searchResults.isNotEmpty)
            Container(
              margin: const EdgeInsets.fromLTRB(16, 0, 16, 6),
              constraints: const BoxConstraints(maxHeight: 240),
              child: SingleChildScrollView(
                child: Column(children: searchResults.map((u) {
                  final userId = int.tryParse((u['id'] ?? '').toString()) ?? 0;
                  final status = (u['attendance_status'] ?? 'not_checked_in').toString();
                  final busy = _attendanceBusyIds.contains(userId);
                  final canCheckIn = u['can_check_in'] == true || status == 'not_checked_in';
                  final canCheckOut = u['can_check_out'] == true || status == 'inside';
                  return SurfaceCard(
                    padding: const EdgeInsets.all(10),
                    child: Row(children: [
                      GxAvatar(name: u['name'] ?? 'M', size: 38),
                      const SizedBox(width: 10),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(u['name'] ?? '', style: context.typo.titleSmall),
                        Text(
                          status == 'completed'
                              ? 'Already checked out today'
                              : status == 'inside'
                                  ? 'Inside now • ${DateFormatter.formatTime(u['checked_in_time']?.toString())}'
                                  : (u['phone_number'] ?? ''),
                          style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary),
                        ),
                      ])),
                      if (busy)
                        SizedBox(width: 34, height: 34, child: Padding(padding: EdgeInsets.all(7), child: CircularProgressIndicator(strokeWidth: 2.2, color: AppTheme.brand)))
                      else if (canCheckOut)
                        IconButton.filledTonal(
                          tooltip: 'Check Out',
                          icon: const Icon(Icons.logout_rounded, color: AppTheme.warning),
                          onPressed: () => _checkOut(Map<String, dynamic>.from(u as Map)),
                        )
                      else if (canCheckIn)
                        IconButton.filled(
                          tooltip: 'Check In',
                          icon: const Icon(Icons.login_rounded),
                          style: IconButton.styleFrom(backgroundColor: AppTheme.success),
                          onPressed: () => _checkIn(userId, u['name'] ?? 'Member'),
                        )
                      else
                        StatusBadge('Done', color: context.tokens.textTertiary, icon: Icons.check_circle_rounded),
                    ]),
                  );
                }).toList().expand((w) => [w, const SizedBox(height: 8)]).toList()),
              ),
            ),
          Expanded(
            child: loading
                ? const SkeletonList()
                : error != null
                    ? ErrorRetry(message: 'Failed to load attendance.', onRetry: _load)
                    : attendance.isEmpty
                        ? const EmptyState(icon: Icons.event_available_rounded, title: 'No records for this date', subtitle: 'Check in members using the search above')
                        : RefreshIndicator(
                            color: AppTheme.brand,
                            onRefresh: _load,
                            child: ListView.separated(
                              padding: EdgeInsets.fromLTRB(16, 4, 16, context.navSpace + 16),
                              itemCount: attendance.length,
                              separatorBuilder: (_, __) => const SizedBox(height: 10),
                              itemBuilder: (ctx, i) {
                                final a = attendance[i];
                                final isAuto = (a['notes']?.contains('Auto checkout') ?? false) || a['is_auto_checkout'] == true;
                                final inside = a['checked_out_time'] == null;
                                return FadeInUp(delayMs: (i * 20).clamp(0, 240), offset: 10, child: SurfaceCard(
                                  padding: const EdgeInsets.all(12),
                                  onTap: () => _showOptions(a),
                                  child: Row(children: [
                                    GxAvatar(name: a['name'] ?? 'M', size: 46),
                                    const SizedBox(width: 12),
                                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                      Text(a['name'] ?? '', style: context.typo.titleSmall),
                                      const SizedBox(height: 3),
                                      Text('${DateFormatter.formatTime(a['checked_in_time'])} - ${a['checked_out_time'] != null ? DateFormatter.formatTime(a['checked_out_time']) : 'Now'}${isAuto ? ' (Auto)' : ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                                    ])),
                                    StatusBadge(inside ? 'Inside' : 'Left', color: inside ? AppTheme.success : context.tokens.textTertiary, icon: inside ? Icons.circle : Icons.logout_rounded),
                                    if (inside && canMark) ...[
                                      const SizedBox(width: 8),
                                      IconButton(
                                        icon: Icon(Icons.logout_rounded, size: 18, color: AppTheme.warning),
                                        onPressed: () => _checkOut(a),
                                        tooltip: 'Check Out',
                                      ),
                                    ],
                                  ]),
                                ));
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  Widget _navBtn(IconData icon, VoidCallback? onTap) {
    return Material(
      color: Colors.white.withOpacity(onTap == null ? 0.04 : 0.12),
      shape: const CircleBorder(),
      child: InkWell(customBorder: const CircleBorder(), onTap: onTap, child: Padding(padding: const EdgeInsets.all(8), child: Icon(icon, color: Colors.white.withOpacity(onTap == null ? 0.3 : 1), size: 22))),
    );
  }

  Widget _attStat(String label, int value, IconData icon) {
    return Expanded(child: Column(children: [
      Icon(icon, color: AppTheme.brandAmber, size: 18),
      const SizedBox(height: 6),
      Text('$value', style: GoogleFonts.spaceGrotesk(fontSize: 22, fontWeight: FontWeight.w700, color: Colors.white)),
      Text(label, style: GoogleFonts.poppins(fontSize: 11, color: Colors.white.withOpacity(0.6))),
    ]));
  }
}
