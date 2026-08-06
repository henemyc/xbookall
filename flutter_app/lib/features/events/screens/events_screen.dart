import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';

class EventsScreen extends ConsumerStatefulWidget {
  const EventsScreen({super.key});
  @override
  ConsumerState<EventsScreen> createState() => _EventsScreenState();
}

class _EventsScreenState extends ConsumerState<EventsScreen> {
  List events = [];
  bool loading = true;
  String? error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getEvents();
      if (mounted) setState(() { events = res['events'] ?? []; loading = false; });
    } catch (e) { if (mounted) setState(() { error = _friendly(e); loading = false; }); }
  }

  String _friendly(dynamic e) {
    final m = e.toString();
    if (m.contains('connection') || m.contains('SocketException')) return 'No internet';
    return 'Failed to load';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Events')),
      body: loading
          ? const SkeletonList()
          : error != null
              ? ErrorRetry(message: error!, onRetry: _load)
              : events.isEmpty
                  ? EmptyState(icon: Icons.event_rounded, title: 'No events', subtitle: 'Create events for your gym', actionLabel: 'Add Event', onAction: _showAddSheet)
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
                        itemCount: events.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (ctx, i) {
                          final ev = events[i];
                          final color = AppTheme.categoryColors[i % AppTheme.categoryColors.length];
                          final startDate = ev['start_date']?.toString();
                          final endDate = ev['end_date']?.toString();
                          return FadeInUp(
                            delayMs: (i * 22).clamp(0, 240),
                            offset: 10,
                            child: SurfaceCard(
                              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                Row(children: [
                                  IconBadge(Icons.event_rounded, color: color, size: 38, iconSize: 18),
                                  const SizedBox(width: 10),
                                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Text(ev['title'] ?? '', style: context.typo.titleMedium),
                                    if (ev['type_name'] != null && ev['type_name'].toString().isNotEmpty)
                                      Text(ev['type_name'], style: context.typo.bodySmall?.copyWith(color: color, fontWeight: FontWeight.w600)),
                                  ])),
                                  IconButton(
                                    icon: Icon(Icons.delete_outline_rounded, size: 20, color: AppTheme.danger.withOpacity(0.7)),
                                    onPressed: () => _delete(ev),
                                    tooltip: 'Delete',
                                  ),
                                ]),
                                if ((ev['description'] ?? '').toString().isNotEmpty) ...[
                                  const SizedBox(height: 10),
                                  Text(ev['description'], style: context.typo.bodyMedium?.copyWith(height: 1.45)),
                                ],
                                const SizedBox(height: 10),
                                Row(children: [
                                  Icon(Icons.calendar_today_rounded, size: 12, color: context.tokens.textTertiary),
                                  const SizedBox(width: 4),
                                  Text(
                                    startDate != null ? DateFormatter.formatDate(startDate) : '-',
                                    style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11),
                                  ),
                                  if (endDate != null && endDate != startDate) ...[
                                    const SizedBox(width: 4),
                                    Text('→ ${DateFormatter.formatDate(endDate)}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11)),
                                  ],
                                ]),
                              ]),
                            ),
                          );
                        },
                      ),
                    ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showAddSheet,
        icon: const Icon(Icons.add_rounded),
        label: const Text('Add Event', style: TextStyle(fontWeight: FontWeight.w700)),
        backgroundColor: AppTheme.brand,
      ),
    );
  }

  void _showAddSheet() {
    final titleCtrl = TextEditingController();
    final descCtrl = TextEditingController();
    DateTime startDate = DateTime.now();
    DateTime endDate = DateTime.now().add(const Duration(days: 1));

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.event_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text('Add Event', style: context.typo.titleLarge)]),
          const SizedBox(height: 18),
          TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: 'Title*', prefixIcon: Icon(Icons.edit_rounded))),
          const SizedBox(height: 12),
          TextField(controller: descCtrl, decoration: const InputDecoration(labelText: 'Description'), maxLines: 3),
          const SizedBox(height: 14),
          Row(children: [
            Expanded(child: _datePicker(ctx, 'Start Date', startDate, (d) => setSheet(() => startDate = d))),
            const SizedBox(width: 12),
            Expanded(child: _datePicker(ctx, 'End Date', endDate, (d) => setSheet(() => endDate = d))),
          ]),
          const SizedBox(height: 20),
          FireButton(label: 'Create Event', onPressed: () async {
            if (titleCtrl.text.trim().isEmpty) { Toast.error(ctx, 'Title required'); return; }
            try {
              await ref.read(apiClientProvider).createEvent({
                'title': titleCtrl.text.trim(),
                'description': descCtrl.text.trim(),
                'start_date': _fmt(startDate),
                'end_date': _fmt(endDate),
              });
              if (mounted) Navigator.pop(ctx);
              _load();
              if (mounted) Toast.success(context, 'Event created');
            } catch (e) { Toast.error(ctx, 'Failed'); }
          }),
        ]),
      );
    }));
  }

  Widget _datePicker(BuildContext ctx, String label, DateTime value, ValueChanged<DateTime> onPick) {
    return InkWell(
      onTap: () async {
        final d = await showDatePicker(context: ctx, initialDate: value, firstDate: DateTime(2020), lastDate: DateTime(2030));
        if (d != null) onPick(d);
      },
      child: InputDecorator(
        decoration: InputDecoration(labelText: label),
        child: Text('${value.day}-${value.month}-${value.year}', style: context.typo.bodyLarge),
      ),
    );
  }

  String _fmt(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  Future<void> _delete(Map ev) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Event?'),
        content: Text('Delete "${ev['title']}"? This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(apiClientProvider).deleteEvent(ev['id']);
      _load();
      if (mounted) Toast.success(context, 'Event deleted');
    } catch (e) { if (mounted) Toast.error(context, 'Failed'); }
  }
}
