import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';

class MemberAttendanceScreen extends ConsumerStatefulWidget {
  const MemberAttendanceScreen({super.key});
  @override
  ConsumerState<MemberAttendanceScreen> createState() => _MemberAttendanceScreenState();
}

class _MemberAttendanceScreenState extends ConsumerState<MemberAttendanceScreen> {
  List records = [];
  bool loading = true;
  String? error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final api = ref.read(apiClientProvider);
      final auth = ref.read(authProvider);
      final userId = auth.user?['id'];

      // Preferred: Use getMember which now returns attendance_history for trainee
      if (userId != null) {
        try {
          final memberRes = await api.getMember(int.parse(userId.toString()));
          final data = (memberRes is Map) ? Map<String, dynamic>.from(memberRes) : <String, dynamic>{};
          List history = [];
          if (data['member'] != null && data['member'] is Map) {
            history = (data['member']['attendance_history'] ?? []) as List;
          } else if (data['attendance_history'] != null) {
            history = data['attendance_history'] as List;
          }
          if (history.isNotEmpty && mounted) {
            setState(() { records = history; loading = false; });
            return;
          }
        } catch (_) {}
      }

      // Fallback: direct attendance (backend already filters for trainee)
      final res = await api.getAttendance();
      final data = (res is Map) ? Map<String, dynamic>.from(res) : <String, dynamic>{};
      if (mounted) {
        setState(() {
          records = (data['attendance'] ?? []) as List;
          loading = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() { error = e.toString(); loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: loading
          ? const SkeletonList()
          : error != null
              ? ErrorRetry(message: 'Could not load your attendance.', onRetry: _load)
              : records.isEmpty
                  ? const EmptyState(icon: Icons.event_available_rounded, title: 'No attendance yet', subtitle: 'Scan the gym QR to check in')
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                        itemCount: records.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (ctx, i) {
                          final a = records[i];
                          final isAuto = (a['notes']?.contains('Auto checkout') ?? false);
                          return FadeInUp(delayMs: (i * 20).clamp(0, 240), offset: 10, child: SurfaceCard(
                            padding: const EdgeInsets.all(12),
                            child: Row(children: [
                              IconBadge(Icons.check_circle_rounded, color: AppTheme.success, size: 44),
                              const SizedBox(width: 12),
                              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Text(DateFormatter.formatDate(a['date']), style: context.typo.titleSmall),
                                const SizedBox(height: 2),
                                Text('${DateFormatter.formatTime(a['checked_in_time'])} - ${a['checked_out_time'] != null ? DateFormatter.formatTime(a['checked_out_time']) : 'In'}${isAuto ? ' (Auto)' : ''}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                              ])),
                            ]),
                          ));
                        },
                      ),
                    ),
    );
  }
}
