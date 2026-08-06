import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/features/invoices/models/invoice.dart';
import 'package:gymxbook/features/invoices/screens/invoice_detail_screen.dart';

class MemberDetailScreen extends ConsumerStatefulWidget {
  final int memberId;
  final String memberName;
  const MemberDetailScreen({super.key, required this.memberId, required this.memberName});

  @override
  ConsumerState<MemberDetailScreen> createState() => _MemberDetailScreenState();
}

class _MemberDetailScreenState extends ConsumerState<MemberDetailScreen> {
  Map<String, dynamic>? member;
  List _invoices = [];
  List _payments = [];
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
      final res = await api.getMember(widget.memberId);
      final m = res['member'] ?? res;

      // Rich data now comes directly from backend show()
      final invoicesRaw = (m['invoices'] ?? []) as List;
      final txRaw = (m['transactions'] ?? []) as List;

      _invoices = invoicesRaw;
      _payments = txRaw;

      // Fallback: if still empty, try dedicated endpoint
      if (_invoices.isEmpty && _payments.isEmpty) {
        try {
          final tx = await api.getMemberTransactions(userId: widget.memberId);
          _invoices = (tx['invoices'] ?? tx['data'] ?? []) as List;
          _payments = (tx['payments'] ?? tx['data'] ?? []) as List;
        } catch (_) {}
      }

      if (mounted) setState(() { member = m; loading = false; });
    } catch (e) {
      if (mounted) setState(() { error = e.toString(); loading = false; });
    }
  }

  String get _phone => (member?['phone_number'] ?? '').toString().trim();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.memberName)),
      body: loading
          ? const SkeletonList(count: 4)
          : error != null
              ? ErrorRetry(message: 'Could not load member.', onRetry: _load)
              : member == null
                  ? const EmptyState(icon: Icons.person_off_rounded, title: 'Member not found')
                  : RefreshIndicator(
                      color: AppTheme.brand,
                      onRefresh: _load,
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
                        children: [
                          FadeInUp(child: _header()),
                          const SizedBox(height: 14),
                          FadeInUp(delayMs: 60, child: _actions()),
                          const SizedBox(height: 16),
                          FadeInUp(delayMs: 100, child: _invoicesSection()),
                          const SizedBox(height: 12),
                          FadeInUp(delayMs: 130, child: _transactionsSection()),
                          const SizedBox(height: 12),
                          FadeInUp(delayMs: 160, child: _sectionCard('Assigned Classes', Icons.self_improvement_rounded, const Color(0xFFEC4899), (member!['assigned_classes'] as List?) ?? [], (list) {
                            return Column(children: list.map((c) => _rowTile(Icons.check_circle_rounded, AppTheme.brand, c['title'] ?? '', '₹${c['fees'] ?? 0}')).toList());
                          })),
                          const SizedBox(height: 12),
                          FadeInUp(delayMs: 190, child: _healthSection()),
                          const SizedBox(height: 12),
                          FadeInUp(delayMs: 210, child: _sectionCard('Attendance History', Icons.fact_check_rounded, AppTheme.success, (member!['attendance_history'] as List?) ?? [], (list) {
                            final preview = list.take(3).toList();
                            return Column(children: [
                              ...preview.map((a) => _rowTile(Icons.login_rounded, AppTheme.success, DateFormatter.formatDate(a['date']), '${DateFormatter.formatTime(a['checked_in_time'])} - ${DateFormatter.formatTime(a['checked_out_time'])}${(a['notes']?.contains('Auto checkout') ?? false) ? ' (Auto)' : ''}')),
                              if (list.length > 3) _viewAll('View all ${list.length}', () => _showFullAttendance(list)),
                            ]);
                          })),
                          const SizedBox(height: 12),
                          FadeInUp(delayMs: 230, child: _sectionCard('Freeze History', Icons.ac_unit_rounded, AppTheme.info, (member!['freeze_logs'] as List?) ?? [], (list) {
                            return Column(children: list.map((f) => _rowTile(Icons.pause_circle_rounded, AppTheme.info, '${DateFormatter.formatDate(f['freeze_start_date'])} → ${DateFormatter.formatDate(f['freeze_end_date'])}', '${f['freeze_days']} days${(f['remarks'] ?? '').toString().isNotEmpty ? ' • ${f['remarks']}' : ''}')).toList());
                          })),
                          const SizedBox(height: 24),
                          // Plain delete at the very bottom (no Danger Zone box).
                          FadeInUp(delayMs: 250, child: SizedBox(width: double.infinity, child: OutlinedButton.icon(
                            style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger, side: BorderSide(color: AppTheme.danger.withOpacity(0.4)), padding: const EdgeInsets.symmetric(vertical: 15)),
                            onPressed: _hardDelete,
                            icon: const Icon(Icons.delete_outline_rounded),
                            label: const Text('Delete Member'),
                          ))),
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
          GxAvatar(name: member!['name'] ?? 'M', size: 62),
          const SizedBox(width: 16),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(member!['name'] ?? '', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            if ((member!['email'] ?? '').toString().isNotEmpty) Text(member!['email'] ?? '', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.7), fontSize: 12.5)),
            if (_phone.isNotEmpty) Text(_phone, style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.7), fontSize: 12.5)),
          ])),
        ]),
        if (_phone.isNotEmpty) ...[
          const SizedBox(height: 16),
          Row(children: [
            Expanded(child: _contactBtn(Icons.call_rounded, 'Call', AppTheme.success, _callMember)),
            const SizedBox(width: 10),
            Expanded(child: _contactBtn(Icons.chat_rounded, 'WhatsApp', const Color(0xFF25D366), _whatsappMember)),
          ]),
        ],
        const SizedBox(height: 16),
        Row(children: [
          _quickStat('Plan', member!['plan_name'] ?? 'No Plan', Icons.card_membership_rounded),
          const SizedBox(width: 12),
          _quickStat('Expiry', DateFormatter.formatDate(member!['membership_expiry_date']), Icons.event_rounded),
        ]),
      ]),
    );
  }

  Widget _contactBtn(IconData icon, String label, Color color, VoidCallback onTap) {
    return Pressable(radius: 14, onTap: onTap, child: Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      alignment: Alignment.center,
      decoration: BoxDecoration(color: color.withOpacity(0.2), borderRadius: BorderRadius.circular(14), border: Border.all(color: color.withOpacity(0.4))),
      child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(icon, size: 18, color: Colors.white),
        const SizedBox(width: 8),
        Text(label, style: GoogleFonts.poppins(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13.5)),
      ]),
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
          Text(value, style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w700, color: Colors.white), overflow: TextOverflow.ellipsis),
        ])),
      ]),
    ));
  }

  Widget _actions() {
    final frozen = member!['trainee_status'] == 3;
    return Column(children: [
      Row(children: [
        Expanded(child: FireButton(label: 'Renew', icon: Icons.autorenew_rounded, onPressed: _showRenewSheet)),
        const SizedBox(width: 10),
        Expanded(child: FireButton(label: frozen ? 'Unfreeze' : 'Freeze', icon: Icons.ac_unit_rounded, gradient: AppTheme.amberGradient, onPressed: _showFreezeSheet)),
      ]),
      const SizedBox(height: 10),
      Row(children: [
        Expanded(child: OutlinedButton.icon(onPressed: _showWorkoutSheet, icon: const Icon(Icons.fitness_center_rounded, size: 18), label: const Text('Workout'))),
        const SizedBox(width: 10),
        Expanded(child: OutlinedButton.icon(onPressed: _editMember, icon: const Icon(Icons.edit_rounded, size: 18), label: const Text('Edit'))),
      ]),
    ]);
  }

  // ── Invoices of this member ───────────────────────────────────────
  Widget _invoicesSection() {
    return SurfaceCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        IconBadge(Icons.receipt_long_rounded, color: AppTheme.brand, size: 36, iconSize: 18),
        const SizedBox(width: 10),
        Text('Invoices', style: context.typo.titleMedium),
        const Spacer(),
        if (_invoices.isNotEmpty) StatusBadge('${_invoices.length}', color: AppTheme.brand),
      ]),
      if (_invoices.isEmpty)
        Padding(padding: const EdgeInsets.only(top: 12), child: Text('No invoices yet', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)))
      else ...[
        ..._invoices.take(5).map((raw) {
          final inv = Invoice.fromDetailJson(raw);
          final c = inv.statusColors;
          return Padding(padding: const EdgeInsets.only(top: 10), child: Pressable(radius: 14, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InvoiceDetailScreen(invoiceDbId: inv.id))), child: Row(children: [
            Container(width: 4, height: 34, decoration: BoxDecoration(color: Color(c['text']), borderRadius: BorderRadius.circular(4))),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('INV #${inv.invoiceId}', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
              Text('${inv.formattedDate} • ₹${inv.totalAmount.toStringAsFixed(0)}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5)),
            ])),
            StatusBadge(c['label'], color: Color(c['text'])),
          ])));
        }),
        if (_invoices.length > 5)
          _viewAll('View all ${_invoices.length} invoices', () => _showFullInvoices()),
      ],
    ]));
  }

  // ── Payment/transaction history of this member ────────────────────
  Widget _transactionsSection() {
    final preview = _payments.take(6).toList();
    return _sectionCard('Transactions', Icons.swap_horiz_rounded, AppTheme.info, _payments, (list) {
      return Column(children: [
        ...preview.map((p) {
          final amt = double.tryParse(p['amount'].toString()) ?? 0;
          return _rowTile(Icons.arrow_downward_rounded, AppTheme.success, '₹${amt.toStringAsFixed(0)} • ${(p['payment_type'] ?? 'cash').toString().toUpperCase()}', '${DateFormatter.formatDate(p['payment_date'])}${(p['invoice_id'] ?? '') != '' ? ' • INV #${p['invoice_id']}' : ''}');
        }),
        if (_payments.length > 6)
          _viewAll('View all ${_payments.length} transactions', () => _showFullTransactions()),
      ]);
    });
  }

  // ── Health records → parsed key/value chips instead of raw JSON ────
  Widget _healthSection() {
    final records = (member!['health_records'] as List?) ?? [];
    return SurfaceCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        IconBadge(Icons.monitor_heart_rounded, color: AppTheme.warning, size: 36, iconSize: 18),
        const SizedBox(width: 10),
        Text('Health Records', style: context.typo.titleMedium),
        const Spacer(),
        if (records.isNotEmpty) StatusBadge('${records.length}', color: AppTheme.warning),
      ]),
      if (records.isEmpty)
        Padding(padding: const EdgeInsets.only(top: 12), child: Text('No health records yet', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)))
      else
        ...records.take(4).map((h) => _healthCard(h)),
    ]));
  }

  Widget _healthCard(Map h) {
    final metrics = _parseHealth(h['result']);
    return Container(
      margin: const EdgeInsets.only(top: 12),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(14)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const Icon(Icons.event_rounded, size: 13, color: AppTheme.warning),
          const SizedBox(width: 5),
          Text(DateFormatter.formatDate(h['measurement_date']), style: context.typo.titleSmall?.copyWith(fontSize: 12.5)),
          if ((h['notes'] ?? '').toString().isNotEmpty) ...[const SizedBox(width: 6), Expanded(child: Text('• ${h['notes']}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11), overflow: TextOverflow.ellipsis))],
        ]),
        const SizedBox(height: 10),
        if (metrics.isEmpty)
          Text(h['result']?.toString() ?? '-', style: context.typo.bodySmall)
        else
          Wrap(spacing: 8, runSpacing: 8, children: metrics.entries.map((e) => _metricChip(e.key, e.value)).toList()),
      ]),
    );
  }

  Widget _metricChip(String label, String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(color: context.tokens.surface, borderRadius: BorderRadius.circular(12), border: Border.all(color: context.tokens.border)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
        Text(_titleCase(label), style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary, letterSpacing: 0.4)),
        const SizedBox(height: 2),
        Text(value, style: GoogleFonts.spaceGrotesk(fontSize: 15, fontWeight: FontWeight.w700, color: context.tokens.text)),
      ]),
    );
  }

  Map<String, String> _parseHealth(dynamic result) {
    if (result == null) return {};
    final s = result.toString().trim();
    if (s.isEmpty) return {};
    // Try JSON object/array.
    try {
      final decoded = jsonDecode(s);
      if (decoded is Map) {
        return decoded.map((k, v) => MapEntry(k.toString(), v.toString())).cast<String, String>();
      }
      if (decoded is List && decoded.isNotEmpty && decoded.first is Map) {
        return (decoded.first as Map).map((k, v) => MapEntry(k.toString(), v.toString())).cast<String, String>();
      }
    } catch (_) {}
    // Fallback: parse "key: value • key: value" style.
    if (s.contains(':')) {
      final out = <String, String>{};
      for (final part in s.split(RegExp(r'[•,]'))) {
        final kv = part.split(':');
        if (kv.length == 2) out[kv[0].trim()] = kv[1].trim();
      }
      if (out.isNotEmpty) return out;
    }
    return {};
  }

  String _titleCase(String s) => s.replaceAll('_', ' ').split(' ').map((w) => w.isEmpty ? w : '${w[0].toUpperCase()}${w.substring(1)}').join(' ');

  Widget _rowTile(IconData icon, Color color, String title, String subtitle) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        IconBadge(icon, color: color, size: 34, iconSize: 16),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
          const SizedBox(height: 2),
          Text(subtitle, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5)),
        ])),
      ]),
    );
  }

  Widget _viewAll(String label, VoidCallback onTap) => Padding(
        padding: const EdgeInsets.only(top: 8),
        child: Align(alignment: Alignment.centerLeft, child: TextButton(onPressed: onTap, child: Text(label))),
      );

  Widget _sectionCard(String title, IconData icon, Color color, List list, Widget Function(List) builder) {
    return SurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(children: [
            IconBadge(icon, color: color, size: 36, iconSize: 18),
            const SizedBox(width: 10),
            Text(title, style: context.typo.titleMedium),
            const Spacer(),
            if (list.isNotEmpty) StatusBadge('${list.length}', color: color),
          ]),
          if (list.isEmpty)
            Padding(padding: const EdgeInsets.only(top: 12), child: Text('No records yet', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)))
          else
            builder(list),
        ],
      ),
    );
  }

  // ── Contact actions ───────────────────────────────────────────────
  Future<void> _callMember() async {
    final uri = Uri(scheme: 'tel', path: _phone);
    try { await launchUrl(uri); } catch (e) { if (mounted) Toast.error(context, 'Cannot open dialer'); }
  }

  Future<void> _whatsappMember() async {
    var num = _phone.replaceAll(RegExp(r'[^0-9]'), '');
    if (num.length == 10) num = '91$num'; // default to India country code
    final uri = Uri.parse('https://wa.me/$num');
    try { await launchUrl(uri, mode: LaunchMode.externalApplication); } catch (e) { if (mounted) Toast.error(context, 'Cannot open WhatsApp'); }
  }

  // ── Full lists ────────────────────────────────────────────────────
  void _showFullAttendance(List list) {
    showAppSheet(context, child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.7,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Full Attendance · ${list.length}', style: context.typo.titleLarge),
          const SizedBox(height: 12),
          Expanded(child: ListView.builder(itemCount: list.length, itemBuilder: (c, i) {
            final a = list[i];
            return _rowTile(Icons.login_rounded, AppTheme.success, DateFormatter.formatDate(a['date']), '${DateFormatter.formatTime(a['checked_in_time'])} - ${a['checked_out_time'] != null ? DateFormatter.formatTime(a['checked_out_time']) : 'In'}${(a['notes']?.contains('Auto checkout') ?? false) ? ' (Auto)' : ''}');
          })),
        ]),
      ),
    ));
  }

  void _showFullInvoices() {
    showAppSheet(context, child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.75,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('All Invoices · ${_invoices.length}', style: context.typo.titleLarge),
          const SizedBox(height: 12),
          Expanded(
            child: ListView.builder(
              itemCount: _invoices.length,
              itemBuilder: (c, i) {
                final raw = _invoices[i];
                final inv = Invoice.fromDetailJson(raw);
                final co = inv.statusColors;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Pressable(
                    radius: 14,
                    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InvoiceDetailScreen(invoiceDbId: inv.id))),
                    child: Row(children: [
                      Container(width: 4, height: 42, decoration: BoxDecoration(color: Color(co['text']), borderRadius: BorderRadius.circular(4))),
                      const SizedBox(width: 10),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('INV #${inv.invoiceId}', style: context.typo.titleSmall),
                        Text('${inv.formattedDate} • ₹${inv.totalAmount.toStringAsFixed(0)}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                      ])),
                      StatusBadge(co['label'], color: Color(co['text'])),
                    ]),
                  ),
                );
              },
            ),
          ),
        ]),
      ),
    ));
  }

  void _showFullTransactions() {
    showAppSheet(context, child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.75,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('All Transactions · ${_payments.length}', style: context.typo.titleLarge),
          const SizedBox(height: 12),
          Expanded(
            child: ListView.builder(
              itemCount: _payments.length,
              itemBuilder: (c, i) {
                final p = _payments[i];
                final amt = double.tryParse(p['amount'].toString()) ?? 0;
                return Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: _rowTile(
                    Icons.arrow_downward_rounded,
                    AppTheme.success,
                    '₹${amt.toStringAsFixed(0)} • ${(p['payment_type'] ?? 'cash').toString().toUpperCase()}',
                    '${DateFormatter.formatDate(p['payment_date'])}${(p['invoice_id'] ?? '') != '' ? ' • INV #${p['invoice_id']}' : ''}'
                  ),
                );
              },
            ),
          ),
        ]),
      ),
    ));
  }

  // ── Renew (logic preserved) ───────────────────────────────────────
  void _showRenewSheet() {
    final paidCtrl = TextEditingController();
    String? selectedPlan;
    DateTime? start = DateTime.now();
    DateTime? expiry = DateTime.now().add(const Duration(days: 30));
    List plans = [];
    bool loadingPlans = true;

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      if (loadingPlans) {
        Future.microtask(() async {
          try {
            final res = await ref.read(apiClientProvider).getMemberships();
            setSheet(() { plans = res['memberships'] ?? []; loadingPlans = false; });
          } catch (_) { setSheet(() => loadingPlans = false); }
        });
      }
      // Auto expiry from the selected plan's package (1/3/12 months).
      void recalc() {
        if (selectedPlan == null || start == null) return;
        Map? plan;
        try { plan = plans.firstWhere((p) => p['id'].toString() == selectedPlan); } catch (_) { plan = null; }
        if (plan == null) return;
        final months = _planMonths(plan);
        if (months <= 0) return;
        var y = start!.year; var m = start!.month + months;
        while (m > 12) { m -= 12; y += 1; }
        expiry = DateTime(y, m, start!.day).subtract(const Duration(days: 1));
      }
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.autorenew_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text('Renew Membership', style: context.typo.titleLarge)]),
          const SizedBox(height: 18),
          loadingPlans
              ? const Center(child: Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator()))
              : DropdownButtonFormField<String>(
                  value: selectedPlan,
                  decoration: const InputDecoration(labelText: 'New Plan*'),
                  items: plans.map((p) => DropdownMenuItem<String>(value: p['id'].toString(), child: Text("${p['title']} - ₹${p['amount']}"))).toList(),
                  onChanged: (v) => setSheet(() { selectedPlan = v; recalc(); }),
                ),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: _dateField(ctx, 'Start Date', start!, (d) => setSheet(() { start = d; recalc(); }), DateTime(2020))),
            const SizedBox(width: 12),
            Expanded(child: _dateField(ctx, 'Expiry Date', expiry!, (d) => setSheet(() => expiry = d), DateTime(2020))),
          ]),
          const SizedBox(height: 12),
          TextField(controller: paidCtrl, decoration: const InputDecoration(labelText: 'Paid Amount', prefixText: '₹ '), keyboardType: TextInputType.number),
          const SizedBox(height: 20),
          FireButton(label: 'Renew Membership', onPressed: () async {
            if (selectedPlan == null) { Toast.error(ctx, 'Select plan'); return; }
            try {
              // Laravel REST: POST /v1/members/{id}/renew
              await ref.read(apiClientProvider).renewMember(widget.memberId, {
                'membership_plan': int.tryParse(selectedPlan!),
                'membership_start_date': "${start!.year}-${start!.month.toString().padLeft(2, '0')}-${start!.day.toString().padLeft(2, '0')}",
                'membership_expiry_date': "${expiry!.year}-${expiry!.month.toString().padLeft(2, '0')}-${expiry!.day.toString().padLeft(2, '0')}",
                'paid_amount': double.tryParse(paidCtrl.text) ?? 0,
                'payment_method': 'cash',
              });
              if (mounted) Navigator.pop(ctx);
              _load();
              if (mounted) Toast.success(context, 'Membership renewed');
            } catch (e) { Toast.error(ctx, 'Failed to renew'); }
          }),
        ]),
      );
    }));
  }

  int _planMonths(Map plan) {
    final pkg = (plan['package'] ?? '').toString().toLowerCase().trim();
    if (pkg.isEmpty) return 1;
    final n = int.tryParse(pkg);
    if (n != null && n > 0) return n;
    if (pkg.contains('year') || pkg.contains('annual') || pkg.contains('12')) return 12;
    if (pkg.contains('half') || pkg.contains('6')) return 6;
    if (pkg.contains('quarter') || pkg.contains('3')) return 3;
    if (pkg.contains('week')) return 0;
    return 1;
  }

  // ── Freeze / Unfreeze (logic preserved) ───────────────────────────
  void _showFreezeSheet() {
    final isFrozen = member!['trainee_status'] == 3;
    if (isFrozen) {
      showDialog(context: context, builder: (ctx) => AlertDialog(
        title: const Text('Unfreeze Membership?'),
        content: const Text('This will unfreeze the membership.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(onPressed: () async {
            try {
              // Laravel REST: POST /v1/members/{id}/unfreeze
              await ref.read(apiClientProvider).unfreezeMember(widget.memberId);
              if (mounted) Navigator.pop(ctx);
              _load();
              if (mounted) Toast.success(context, 'Membership unfrozen');
            } catch (e) { Toast.error(ctx, 'Failed'); }
          }, child: const Text('Unfreeze')),
        ],
      ));
      return;
    }
    final remarksCtrl = TextEditingController();
    DateTime? freezeStart = DateTime.now();
    DateTime? freezeEnd = DateTime.now().add(const Duration(days: 7));
    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.ac_unit_rounded, color: AppTheme.info), const SizedBox(width: 12), Text('Freeze Membership', style: context.typo.titleLarge)]),
          const SizedBox(height: 18),
          Row(children: [
            Expanded(child: _dateField(ctx, 'Freeze Start', freezeStart!, (d) => setSheet(() => freezeStart = d), DateTime.now())),
            const SizedBox(width: 12),
            Expanded(child: _dateField(ctx, 'Freeze End', freezeEnd!, (d) => setSheet(() => freezeEnd = d), DateTime.now())),
          ]),
          const SizedBox(height: 12),
          TextField(controller: remarksCtrl, decoration: const InputDecoration(labelText: 'Remarks')),
          const SizedBox(height: 20),
          FireButton(label: 'Freeze Membership', gradient: AppTheme.amberGradient, onPressed: () async {
            try {
              // Laravel REST: POST /v1/members/{id}/freeze
              await ref.read(apiClientProvider).freezeMember(widget.memberId, {
                'freeze_start_date': "${freezeStart!.year}-${freezeStart!.month.toString().padLeft(2, '0')}-${freezeStart!.day.toString().padLeft(2, '0')}",
                'freeze_end_date': "${freezeEnd!.year}-${freezeEnd!.month.toString().padLeft(2, '0')}-${freezeEnd!.day.toString().padLeft(2, '0')}",
                'remarks': remarksCtrl.text.trim(),
              });
              if (mounted) Navigator.pop(ctx);
              _load();
              if (mounted) Toast.success(context, 'Membership frozen');
            } catch (e) { Toast.error(ctx, 'Failed'); }
          }),
        ]),
      );
    }));
  }

  // ── Workout — proper structured form (day + exercises), not raw JSON ─
  void _showWorkoutSheet() {
    final notesCtrl = TextEditingController();
    DateTime start = DateTime.now();
    // Editable list of days, each with its own exercises text (one per line).
    final days = <Map<String, dynamic>>[
      {'day': 'Monday', 'ctrl': TextEditingController()},
    ];
    const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.fitness_center_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text('Assign Workout', style: context.typo.titleLarge)]),
          const SizedBox(height: 16),
          _dateField(ctx, 'Start Date', start, (d) => setSheet(() => start = d), DateTime(2020)),
          const SizedBox(height: 14),
          ...days.asMap().entries.map((e) {
            final i = e.key;
            final d = e.value;
            return Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(14)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Expanded(child: DropdownButtonFormField<String>(
                    value: d['day'],
                    isDense: true,
                    decoration: const InputDecoration(labelText: 'Day', contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10)),
                    items: weekdays.map((w) => DropdownMenuItem(value: w, child: Text(w))).toList(),
                    onChanged: (v) => setSheet(() => d['day'] = v),
                  )),
                  if (days.length > 1)
                    IconButton(onPressed: () => setSheet(() => days.removeAt(i)), icon: const Icon(Icons.remove_circle_outline_rounded, color: AppTheme.danger)),
                ]),
                const SizedBox(height: 8),
                TextField(controller: d['ctrl'] as TextEditingController, maxLines: 3, decoration: const InputDecoration(labelText: 'Exercises (one per line)', hintText: 'Bench Press 3x12\nSquats 4x10')),
              ]),
            );
          }),
          Align(alignment: Alignment.centerLeft, child: TextButton.icon(onPressed: () => setSheet(() => days.add({'day': weekdays[days.length % 7], 'ctrl': TextEditingController()})), icon: const Icon(Icons.add_rounded, size: 18), label: const Text('Add Day'))),
          const SizedBox(height: 8),
          TextField(controller: notesCtrl, decoration: const InputDecoration(labelText: 'Notes (optional)')),
          const SizedBox(height: 18),
          FireButton(label: 'Assign Workout', onPressed: () async {
            // Build the same JSON structure the backend expects.
            final plan = days.map((d) {
              final lines = (d['ctrl'] as TextEditingController).text.split('\n').map((l) => l.trim()).where((l) => l.isNotEmpty).toList();
              return {'day': d['day'], 'exercises': lines};
            }).where((d) => (d['exercises'] as List).isNotEmpty).toList();
            if (plan.isEmpty) { Toast.error(ctx, 'Add at least one exercise'); return; }
            try {
              // Laravel REST: POST /v1/workouts
              await ref.read(apiClientProvider).createWorkout({
                'user_id': widget.memberId,
                'workout_plan': jsonEncode(plan),
                'notes': notesCtrl.text.trim(),
                'start_date': "${start.year}-${start.month.toString().padLeft(2, '0')}-${start.day.toString().padLeft(2, '0')}",
              });
              if (mounted) Navigator.pop(ctx);
              if (mounted) Toast.success(context, 'Workout assigned');
            } catch (e) { Toast.error(ctx, 'Failed'); }
          }),
        ]),
      );
    }));
  }

  // ── Assign Locker ────────────────────────────────────────────────
  void _showAssignLockerSheet() {
    List lockers = [];
    bool loadingLockers = true;
    int? selectedLocker;

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      if (loadingLockers) {
        Future.microtask(() async {
          try {
            final res = await ref.read(apiClientProvider).getLockers();
            final all = (res['lockers'] ?? []) as List;
            setSheet(() { lockers = all.where((l) => (l['available'] ?? l['is_available']) == 1).toList(); loadingLockers = false; });
          } catch (_) { setSheet(() => loadingLockers = false); }
        });
      }
      return Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.lock_outline_rounded, color: const Color(0xFF10B981)), const SizedBox(width: 12), Text('Assign Locker', style: context.typo.titleLarge)]),
          const SizedBox(height: 18),
          if (loadingLockers)
            const Center(child: Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator()))
          else if (lockers.isEmpty)
            Padding(padding: const EdgeInsets.all(20), child: Text('No available lockers. Add lockers from the Lockers screen.', style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary)))
          else
            DropdownButtonFormField<int>(
              value: selectedLocker,
              decoration: const InputDecoration(labelText: 'Select Available Locker', prefixIcon: Icon(Icons.lock_outline_rounded)),
              items: lockers.map((l) => DropdownMenuItem<int>(value: l['id'] as int, child: Text('Locker #${l['id']}'))).toList(),
              onChanged: (v) => setSheet(() => selectedLocker = v),
            ),
          if (lockers.isNotEmpty) ...[
            const SizedBox(height: 20),
            FireButton(
              label: 'Assign Locker',
              icon: Icons.lock_rounded,
              onPressed: selectedLocker == null ? null : () async {
                try {
              // Laravel REST: POST /v1/lockers/assign
              await ref.read(apiClientProvider).assignLocker({
                'user_id': widget.memberId,
                'locker_id': selectedLocker,
                'assign_date': DateTime.now().toIso8601String().split('T')[0],
              });
              if (mounted) Navigator.pop(ctx);
              if (mounted) Toast.success(context, 'Locker #$selectedLocker assigned');
            } catch (e) { Toast.error(ctx, 'Failed to assign locker'); }
              },
            ),
          ],
        ]),
      );
    }));
  }

  Widget _dateField(BuildContext ctx, String label, DateTime value, ValueChanged<DateTime> onPick, DateTime first) {
    return InkWell(
      onTap: () async {
        final d = await showDatePicker(context: ctx, initialDate: value, firstDate: first, lastDate: DateTime(2030));
        if (d != null) onPick(d);
      },
      child: InputDecorator(
        decoration: InputDecoration(labelText: label),
        child: Text("${value.day}-${value.month}-${value.year}", style: context.typo.bodyLarge),
      ),
    );
  }

  void _editMember() {
    final nameCtrl = TextEditingController(text: member!['name'] ?? '');
    final emailCtrl = TextEditingController(text: member!['email'] ?? '');
    final phoneCtrl = TextEditingController(text: member!['phone_number'] ?? '');
    final addressCtrl = TextEditingController(text: member!['address'] ?? '');
    final cityCtrl = TextEditingController(text: member!['city'] ?? '');
    String gender = (member!['gender'] ?? 'male').toString().toLowerCase();
    if (!['male', 'female', 'other'].contains(gender)) gender = 'male';

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.edit_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text('Edit Member', style: context.typo.titleLarge)]),
          const SizedBox(height: 18),
          TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Full Name*', prefixIcon: Icon(Icons.person_outline_rounded))),
          const SizedBox(height: 12),
          TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.email_outlined)), keyboardType: TextInputType.emailAddress),
          const SizedBox(height: 12),
          TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Phone', prefixIcon: Icon(Icons.phone_rounded)), keyboardType: TextInputType.phone),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            value: gender,
            decoration: const InputDecoration(labelText: 'Gender', prefixIcon: Icon(Icons.wc_rounded)),
            items: const [DropdownMenuItem(value: 'male', child: Text('Male')), DropdownMenuItem(value: 'female', child: Text('Female')), DropdownMenuItem(value: 'other', child: Text('Other'))],
            onChanged: (v) => setSheet(() => gender = v ?? 'male'),
          ),
          const SizedBox(height: 12),
          TextField(controller: addressCtrl, decoration: const InputDecoration(labelText: 'Address', prefixIcon: Icon(Icons.location_on_outlined))),
          const SizedBox(height: 12),
          TextField(controller: cityCtrl, decoration: const InputDecoration(labelText: 'City', prefixIcon: Icon(Icons.location_city_rounded))),
          const SizedBox(height: 20),
          FireButton(label: 'Save Changes', icon: Icons.check_rounded, onPressed: () async {
            if (nameCtrl.text.trim().isEmpty) { Toast.error(ctx, 'Name required'); return; }
            try {
              // Laravel REST: PUT /v1/members/{id}
              await ref.read(apiClientProvider).updateMember(widget.memberId, {
                'name': nameCtrl.text.trim(),
                'email': emailCtrl.text.trim(),
                'phone_number': phoneCtrl.text.trim(),
                'gender': gender,
                'address': addressCtrl.text.trim(),
                'city': cityCtrl.text.trim(),
                'is_active': member!['is_active'] ?? 1,
              });
              if (ctx.mounted) Navigator.pop(ctx);
              _load();
              if (mounted) Toast.success(context, 'Member updated');
            } catch (e) { Toast.error(ctx, 'Failed to update'); }
          }),
        ]),
      );
    }));
  }

  Future<void> _hardDelete() async {
    final confirm1 = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(
      title: const Text('Delete Member?'),
      content: const Text('This will delete member + invoices, payments, attendance, health, lockers, workouts. Cannot be undone.'),
      actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete'))],
    ));
    if (confirm1 != true) return;
    final confirm2 = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(
      title: const Text('Final confirmation'),
      content: const Text('Are you absolutely sure? This action is permanent.'),
      actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Yes, Delete'))],
    ));
    if (confirm2 != true) return;
    try {
      // Laravel REST: DELETE /v1/members/{id}/hard
      await ref.read(apiClientProvider).hardDeleteMember(widget.memberId);
      if (mounted) { Toast.success(context, 'Member deleted'); Navigator.pop(context); }
    } catch (e) { if (mounted) Toast.error(context, 'Failed to delete'); }
  }
}
