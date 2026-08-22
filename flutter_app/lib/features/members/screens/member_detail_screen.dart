import 'dart:convert';
import 'dart:io';
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:image_picker/image_picker.dart';
import 'package:image_cropper/image_cropper.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';
import 'package:gymxbook/features/invoices/models/invoice.dart';
import 'package:gymxbook/features/invoices/screens/invoice_detail_screen.dart';
import 'package:gymxbook/features/diets/screens/assign_member_diet_screen.dart';
import 'package:gymxbook/features/diets/screens/member_diet_detail_screen.dart';

// Repaired: removed duplicated trailing Dart fragment.
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
  List _diets = [];
  List _documents = [];
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
      try {
        final dietResponse = await api.getMemberDiets(widget.memberId);
        _diets = (dietResponse['diets'] ?? []) as List;
      } catch (_) {
        _diets = [];
      }
      try {
        final docResponse = await api.getMemberDocuments(widget.memberId);
        _documents = (docResponse['documents'] as List?) ?? [];
      } catch (_) {
        _documents = [];
      }

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
  List get _classes => (member!['assigned_classes'] as List?) ?? const [];
  List get _health => (member!['health_records'] as List?) ?? const [];
  List get _attendance => (member!['attendance_history'] as List?) ?? const [];
  List get _freezeLogs => (member!['freeze_logs'] as List?) ?? const [];

  Map<String, dynamic>? get _activeDiet {
    for (final d in _diets) {
      if (d is Map && (d['status'] ?? '').toString() == 'active') {
        return Map<String, dynamic>.from(d);
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.memberName)),
      body: loading
          ? const _MemberDetailSkeleton()
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
                          // Only show sections that actually have data.
                          if (_invoices.isNotEmpty) ...[
                            FadeInUp(delayMs: 100, child: _invoicesSection()),
                            const SizedBox(height: 12),
                          ],
                          if (_payments.isNotEmpty) ...[
                            FadeInUp(delayMs: 120, child: _transactionsSection()),
                            const SizedBox(height: 12),
                          ],
                          if (_classes.isNotEmpty) ...[
                            FadeInUp(delayMs: 140, child: _sectionCard('Assigned Classes', Icons.self_improvement_rounded, const Color(0xFFEC4899), _classes, (list) {
                              return Column(children: list.map((c) => _rowTile(Icons.check_circle_rounded, AppTheme.brand, c['title'] ?? '', '₹${c['fees'] ?? 0}')).toList());
                            })),
                            const SizedBox(height: 12),
                          ],
                          if (_activeDiet != null) ...[
                            FadeInUp(delayMs: 160, child: _dietSection()),
                            const SizedBox(height: 12),
                          ],
                          if (_health.isNotEmpty) ...[
                            FadeInUp(delayMs: 180, child: _healthSection()),
                            const SizedBox(height: 12),
                          ],
                          if (_attendance.isNotEmpty) ...[
                            FadeInUp(delayMs: 200, child: _sectionCard('Attendance History', Icons.fact_check_rounded, AppTheme.success, _attendance, (list) {
                              final preview = list.take(3).toList();
                              return Column(children: [
                                ...preview.map((a) => _rowTile(Icons.login_rounded, AppTheme.success, DateFormatter.formatDate(a['date']), '${DateFormatter.formatTime(a['checked_in_time'])} - ${DateFormatter.formatTime(a['checked_out_time'])}${(a['notes']?.contains('Auto checkout') ?? false) ? ' (Auto)' : ''}')),
                                if (list.length > 3) _viewAll('View all ${list.length}', () => _showFullAttendance(list)),
                              ]);
                            })),
                            const SizedBox(height: 12),
                          ],
                          if (_freezeLogs.isNotEmpty) ...[
                            FadeInUp(delayMs: 220, child: _sectionCard('Freeze History', Icons.ac_unit_rounded, AppTheme.info, _freezeLogs, (list) {
                              return Column(children: list.map((f) => _rowTile(Icons.pause_circle_rounded, AppTheme.info, '${DateFormatter.formatDate(f['freeze_start_date'])} → ${DateFormatter.formatDate(f['freeze_end_date'])}', '${f['freeze_days']} days${(f['remarks'] ?? '').toString().isNotEmpty ? ' • ${f['remarks']}' : ''}')).toList());
                            })),
                            const SizedBox(height: 12),
                          ],
                          if (_documents.isNotEmpty) ...[
                            FadeInUp(delayMs: 240, child: _documentsSection()),
                          ],
                          // Destructive actions are kept inside the compact More sheet above.
                        ],
                      ),
                    ),
    );
  }

  Future<void> _showPhotoOptions() async {
    if (!ref.read(permissionProvider).can('members.edit')) {
      Toast.error(context, 'Your role does not allow editing this member.');
      return;
    }
    final hasPhoto = (member?['profile'] ?? '').toString().isNotEmpty;
    await showAppSheet(
      context,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 22),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text('Member profile photo', style: context.typo.titleLarge),
          const SizedBox(height: 12),
          _photoOption(Icons.camera_alt_rounded, 'Take photo', () { Navigator.pop(context); _pickMemberPhoto(ImageSource.camera); }),
          _photoOption(Icons.photo_library_rounded, 'Choose from gallery', () { Navigator.pop(context); _pickMemberPhoto(ImageSource.gallery); }),
          if (hasPhoto) _photoOption(Icons.visibility_rounded, 'View photo', () { Navigator.pop(context); _showPhotoViewer(); }),
          if (hasPhoto) _photoOption(Icons.delete_outline_rounded, 'Remove photo', () { Navigator.pop(context); _removeMemberPhoto(); }, danger: true),
        ]),
      ),
    );
  }

  Widget _photoOption(IconData icon, String label, VoidCallback tap, {bool danger = false}) => ListTile(
    onTap: tap,
    leading: IconBadge(icon, color: danger ? AppTheme.danger : AppTheme.brand),
    title: Text(label, style: context.typo.titleMedium?.copyWith(color: danger ? AppTheme.danger : null)),
    trailing: Icon(Icons.chevron_right_rounded, color: context.tokens.textTertiary),
  );

  Future<void> _pickMemberPhoto(ImageSource source) async {
    try {
      final image = await ImagePicker().pickImage(source: source, imageQuality: 82, maxWidth: 1440, maxHeight: 1440);
      if (image == null) return;
      final cropped = await ImageCropper().cropImage(
        sourcePath: image.path,
        aspectRatio: const CropAspectRatio(ratioX: 1, ratioY: 1),
        uiSettings: [AndroidUiSettings(toolbarTitle: 'Crop Member Photo', lockAspectRatio: true)],
      );
      if (cropped == null) return;
      final result = await ref.read(apiClientProvider).uploadMemberPhoto(widget.memberId, File(cropped.path));
      if (!mounted) return;
      setState(() {
        member = {...?member, 'profile': result['profile'], 'profile_photo_url': result['profile_photo_url']};
      });
      Toast.success(context, 'Profile photo updated');
    } catch (e) {
      if (mounted) Toast.error(context, _photoError(e, 'Could not update profile photo'));
    }
  }

  String _photoError(Object error, String fallback) {
    try {
      final data = (error as dynamic).response?.data;
      if (data is Map) return (data['error'] ?? data['message'] ?? fallback).toString();
    } catch (_) {}
    return fallback;
  }

  Future<void> _removeMemberPhoto() async {
    try {
      final result = await ref.read(apiClientProvider).removeMemberPhoto(widget.memberId);
      if (!mounted) return;
      setState(() {
        member = {...?member, 'profile': result['profile'], 'profile_photo_url': result['profile_photo_url']};
      });
      Toast.success(context, 'Profile photo removed');
    } catch (_) {
      if (mounted) Toast.error(context, 'Could not remove profile photo');
    }
  }

  void _showPhotoViewer() {
    final url = member?['profile_photo_url']?.toString() ?? '';
    if (url.isEmpty) return;
    showGeneralDialog(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Close photo',
      barrierColor: Colors.black.withOpacity(.35),
      pageBuilder: (_, __, ___) => BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 12, sigmaY: 12),
        child: GestureDetector(
          onTap: () => Navigator.pop(context),
          child: Center(child: Hero(tag: 'member-photo-${widget.memberId}', child: FractionallySizedBox(widthFactor: .78, heightFactor: .72, child: InteractiveViewer(child: ClipRRect(borderRadius: BorderRadius.circular(20), child: Image.network(url, fit: BoxFit.contain)))))),
        ),
      ),
      transitionBuilder: (_, animation, __, child) => FadeTransition(opacity: animation, child: ScaleTransition(scale: Tween<double>(begin: .72, end: 1).animate(CurvedAnimation(parent: animation, curve: Curves.easeOutCubic)), child: child)),
    );
  }

  // ── Header: "Member C" — membership-progress hero (dark) ─────────
  Widget _header() {
    final frozen = member!['trainee_status'] == 3;
    final hasPhoto = (member?['profile'] ?? '').toString().isNotEmpty;
    final planName = (member!['plan_name'] ?? 'No Plan').toString();
    final expiryRaw = (member!['membership_expiry_date'] ?? '').toString();
    final (daysLeft, remaining) = _planStatus(member!['membership_start_date'], member!['membership_expiry_date']);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF101318), Color(0xFF0A0D12)], begin: Alignment.topCenter, end: Alignment.bottomCenter),
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Stack(clipBehavior: Clip.none, children: [
            Container(
              padding: const EdgeInsets.all(3),
              decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: AppTheme.brandAmber.withOpacity(.9), width: 2)),
              child: Pressable(
                radius: 24,
                onTap: () { if (hasPhoto) _showPhotoViewer(); else _showPhotoOptions(); },
                child: Hero(tag: 'member-photo-${widget.memberId}', child: GxAvatar(name: member!['name'] ?? 'M', imageUrl: member!['profile_photo_url']?.toString(), size: 46, circular: true)),
              ),
            ),
            if (ref.watch(permissionProvider).can('members.edit'))
              Positioned(right: -3, bottom: -3, child: Pressable(radius: 13, onTap: _showPhotoOptions, child: Container(width: 24, height: 24, decoration: BoxDecoration(color: AppTheme.brand, shape: BoxShape.circle, border: Border.all(color: const Color(0xFF0A0D12), width: 2)), child: const Icon(Icons.camera_alt_rounded, size: 12, color: Colors.white)))),
          ]),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(member!['name'] ?? '', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800), maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 2),
            Text(_phone.isEmpty ? 'Member profile' : _phone, style: GoogleFonts.poppins(color: Colors.white60, fontSize: 11.5)),
          ])),
          _heroPill(frozen ? Icons.ac_unit_rounded : Icons.verified_rounded, frozen ? 'Frozen' : 'Active', frozen ? AppTheme.info : AppTheme.success),
        ]),
        const SizedBox(height: 16),
        Row(children: [
          Text(planName, style: GoogleFonts.poppins(color: AppTheme.brandAmber, fontSize: 12.5, fontWeight: FontWeight.w700)),
          const Spacer(),
          if (expiryRaw.isNotEmpty) Text('Expires ${DateFormatter.formatDate(expiryRaw)}', style: GoogleFonts.poppins(color: Colors.white60, fontSize: 11)),
        ]),
        const SizedBox(height: 8),
        ClipRRect(borderRadius: BorderRadius.circular(6), child: LinearProgressIndicator(value: remaining, minHeight: 8, backgroundColor: Colors.white.withOpacity(.12), color: _planBarColor(daysLeft, remaining, frozen))),
        const SizedBox(height: 6),
        Text(_planLabel(daysLeft, remaining, frozen), style: GoogleFonts.poppins(color: Colors.white54, fontSize: 10.5)),
        if (_phone.isNotEmpty) ...[
          const SizedBox(height: 14),
          Row(children: [
            Expanded(child: _contactPill(Icons.call_rounded, 'Call', AppTheme.success, _callMember)),
            const SizedBox(width: 10),
            Expanded(child: _contactPill(Icons.chat_rounded, 'WhatsApp', const Color(0xFF25D366), _whatsappMember)),
          ]),
        ],
      ]),
    );
  }

  // Returns (daysLeft, remaining). remaining is the fraction of the plan still
  // left (reversed vs "used"): 1.0 = just started, 0.0 = fully used/expired.
  (int, double) _planStatus(dynamic startRaw, dynamic expiryRaw) {
    final end = DateTime.tryParse((expiryRaw ?? '').toString());
    final now = DateTime.now();
    if (end == null) return (0, 0);
    final daysLeft = end.difference(now).inDays;
    if (daysLeft < 0) return (daysLeft, 0); // expired → empty bar
    final start = DateTime.tryParse((startRaw ?? '').toString());
    if (start != null && end.isAfter(start)) {
      final total = end.difference(start).inDays;
      if (total <= 0) return (daysLeft, 0);
      return (daysLeft, (daysLeft / total).clamp(0.0, 1.0).toDouble());
    }
    // Older records without a start date: approximate from days left.
    return (daysLeft, (daysLeft / 30.0).clamp(0.0, 1.0).toDouble());
  }

  Color _planBarColor(int daysLeft, double remaining, bool frozen) {
    if (frozen) return AppTheme.info;
    if (daysLeft < 0) return Colors.white24; // expired → empty (track shows nothing)
    if (daysLeft <= 7) return AppTheme.danger; // expiring soon → red
    if (remaining >= 0.9) return AppTheme.success; // 0-10% used → green
    return AppTheme.warning; // in between → orange
  }

  String _planLabel(int daysLeft, double remaining, bool frozen) {
    if (frozen) return 'Membership frozen';
    if (daysLeft < 0) return 'Plan expired';
    return '$daysLeft days remaining · ${(remaining * 100).round()}% left';
  }

  Widget _contactPill(IconData icon, String label, Color color, VoidCallback onTap) {
    return Pressable(radius: 12, onTap: onTap, child: Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(color: color.withOpacity(.16), borderRadius: BorderRadius.circular(12), border: Border.all(color: color.withOpacity(.35))),
      child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(icon, size: 16, color: color),
        const SizedBox(width: 6),
        Text(label, style: GoogleFonts.poppins(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12)),
      ]),
    ));
  }

  Widget _heroPill(IconData icon, String label, Color color) => Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), decoration: BoxDecoration(color: color.withOpacity(.14), borderRadius: BorderRadius.circular(20), border: Border.all(color: color.withOpacity(.28))), child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 13, color: color), const SizedBox(width: 5), Text(label, style: GoogleFonts.poppins(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w600))]));
  Widget _roundContact(IconData icon, Color color, VoidCallback tap) => Pressable(radius: 24, onTap: tap, child: Container(width: 46, height: 46, decoration: BoxDecoration(color: color.withOpacity(.18), shape: BoxShape.circle, border: Border.all(color: color.withOpacity(.45))), child: Icon(icon, color: color)));

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

  // ── Quick actions — "Style D" tonal tiles (Material icons) ──────
  Widget _actions() {
    final frozen = member!['trainee_status'] == 3;
    final permissions = ref.watch(permissionProvider);

    final tiles = <Widget>[
      if (permissions.can('members.renew')) _actionTile(Icons.autorenew_rounded, 'Renew', 'Extend plan', _showRenewSheet),
      if (permissions.can('members.edit')) _actionTile(Icons.edit_rounded, 'Edit', 'Update profile', _editMember),
      _actionTile(Icons.fitness_center_rounded, 'Workout', 'Assign plan', _showWorkoutSheet),
      if (permissions.can('diets.assign')) _actionTile(Icons.restaurant_menu_rounded, 'Diet', 'Nutrition plan', _assignDiet),
    ];

    final children = <Widget>[];
    for (var i = 0; i < tiles.length; i += 2) {
      if (i + 1 < tiles.length) {
        children.add(Row(children: [
          Expanded(child: tiles[i]),
          const SizedBox(width: 10),
          Expanded(child: tiles[i + 1]),
        ]));
        children.add(const SizedBox(height: 10));
      } else {
        children.add(tiles[i]);
      }
    }

    if (permissions.can('members.freeze') || permissions.can('members.delete')) {
      children.add(const SizedBox(height: 10));
      children.add(_moreTile(frozen, permissions));
    }

    return Column(children: children);
  }

  Widget _actionTile(IconData icon, String title, String subtitle, VoidCallback onTap) {
    final t = context.tokens;
    return Pressable(radius: 16, onTap: onTap, child: Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: t.surface, borderRadius: BorderRadius.circular(16), border: Border.all(color: t.border), boxShadow: context.subtleShadow),
      child: Row(children: [
        Container(
          width: 38, height: 38,
          decoration: BoxDecoration(color: t.surfaceAlt, borderRadius: BorderRadius.circular(12), border: Border.all(color: t.border)),
          child: Icon(icon, color: t.textSecondary, size: 18),
        ),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 1),
          Text(subtitle, style: context.typo.bodySmall?.copyWith(color: t.textTertiary, fontSize: 10.5), maxLines: 1, overflow: TextOverflow.ellipsis),
        ])),
      ]),
    ));
  }

  Widget _moreTile(bool frozen, dynamic permissions) {
    final t = context.tokens;
    return Pressable(radius: 16, onTap: () => _showMemberMore(frozen, permissions), child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
      decoration: BoxDecoration(color: t.surface, borderRadius: BorderRadius.circular(16), border: Border.all(color: t.border), boxShadow: context.subtleShadow),
      child: Row(children: [
        Container(
          width: 38, height: 38,
          decoration: BoxDecoration(color: t.surfaceAlt, borderRadius: BorderRadius.circular(12), border: Border.all(color: t.border)),
          child: Icon(Icons.more_horiz_rounded, color: t.textSecondary, size: 18),
        ),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('More', style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
          const SizedBox(height: 1),
          Text('Freeze · locker · delete', style: context.typo.bodySmall?.copyWith(color: t.textTertiary, fontSize: 10.5)),
        ])),
        Icon(Icons.chevron_right_rounded, color: t.textTertiary, size: 18),
      ]),
    ));
  }

  // Quick action: assign a new diet, or open the editor if one already exists.
  void _assignDiet() async {
    final diet = _activeDiet;
    final result = await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => AssignMemberDietScreen(
        memberId: widget.memberId,
        memberName: widget.memberName,
        existingDiet: diet,
      )),
    );
    if (result == true && mounted) {
      Toast.success(context, diet == null ? 'Diet plan assigned' : 'Diet updated');
      _load();
    }
  }

  void _showMemberMore(bool frozen, dynamic permissions) {
    showAppSheet(context, child: Padding(padding: const EdgeInsets.fromLTRB(20, 8, 20, 24), child: Column(mainAxisSize: MainAxisSize.min, children: [
      Text('Member Actions', style: context.typo.titleLarge), const SizedBox(height: 12),
      if (permissions.can('members.freeze')) ListTile(leading: Icon(frozen ? Icons.play_circle_rounded : Icons.ac_unit_rounded), title: Text(frozen ? 'Unfreeze Membership' : 'Freeze Membership'), onTap: () { Navigator.pop(context); _showFreezeSheet(); }),
      ListTile(leading: const Icon(Icons.lock_outline_rounded, color: Color(0xFF10B981)), title: const Text('Assign Locker'), onTap: () { Navigator.pop(context); _showAssignLockerSheet(); }),
      if (permissions.can('members.edit')) ListTile(leading: const Icon(Icons.badge_rounded, color: Color(0xFF6366F1)), title: const Text('Documents (Aadhaar)'), onTap: () { Navigator.pop(context); _showDocumentsSheet(); }),
      if (permissions.can('members.delete')) ListTile(leading: const Icon(Icons.delete_outline_rounded, color: AppTheme.danger), title: Text('Delete Member', style: context.typo.titleMedium?.copyWith(color: AppTheme.danger)), onTap: () { Navigator.pop(context); _hardDelete(); }),
    ])));
  }

  // Diet card — same layout as the other section cards (no badges, no inline edit).
  Widget _dietSection() {
    final diet = _activeDiet;
    if (diet == null) return const SizedBox.shrink();
    return SurfaceCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        IconBadge(Icons.restaurant_menu_rounded, color: AppTheme.success, size: 36, iconSize: 18),
        const SizedBox(width: 10),
        Text('Diet Plan', style: context.typo.titleMedium),
      ]),
      const SizedBox(height: 10),
      Pressable(radius: 14, onTap: () => _showDietOptions(diet), child: Row(children: [
        Icon(Icons.restaurant_rounded, size: 18, color: context.tokens.textSecondary),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text((diet['title'] ?? 'Active Diet').toString(), style: context.typo.titleSmall?.copyWith(fontSize: 13.5), maxLines: 1, overflow: TextOverflow.ellipsis),
          if ((diet['goal'] ?? '').toString().isNotEmpty) ...[
            const SizedBox(height: 2),
            Text((diet['goal'] ?? '').toString(), style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5), maxLines: 1, overflow: TextOverflow.ellipsis),
          ],
        ])),
        Icon(Icons.chevron_right_rounded, color: context.tokens.textTertiary),
      ])),
    ]));
  }

  void _showDietOptions(Map<String, dynamic> diet) {
    final permissions = ref.read(permissionProvider);
    final canEdit = permissions.can('diets.edit');
    showAppSheet(context, child: Padding(padding: const EdgeInsets.fromLTRB(20, 8, 20, 24), child: Column(mainAxisSize: MainAxisSize.min, children: [
      Text('Diet Plan', style: context.typo.titleLarge),
      const SizedBox(height: 12),
      ListTile(
        leading: IconBadge(Icons.visibility_rounded, color: AppTheme.success),
        title: const Text('View Diet'),
        onTap: () { Navigator.pop(context); Navigator.push(context, MaterialPageRoute(builder: (_) => MemberDietDetailScreen(diet: diet, memberName: widget.memberName))); },
      ),
      if (canEdit) ListTile(
        leading: IconBadge(Icons.edit_rounded, color: AppTheme.brand),
        title: const Text('Edit Diet'),
        onTap: () { Navigator.pop(context); _editDiet(diet); },
      ),
      ListTile(
        leading: IconBadge(Icons.add_rounded, color: AppTheme.info),
        title: const Text('Assign New Diet'),
        onTap: () { Navigator.pop(context); _assignDiet(); },
      ),
    ])));
  }

  Future<void> _editDiet(Map<String, dynamic> diet) async {
    final result = await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => AssignMemberDietScreen(memberId: widget.memberId, memberName: widget.memberName, existingDiet: diet)),
    );
    if (result == true && mounted) {
      Toast.success(context, 'Diet updated');
      _load();
    }
  }

  // ── Documents (Aadhaar front/back) ────────────────────────────────
  Map<String, dynamic>? _documentOf(String docType) {
    for (final d in _documents) {
      if (d is Map && (d['doc_type'] ?? '').toString() == docType) {
        return Map<String, dynamic>.from(d);
      }
    }
    return null;
  }

  Widget _documentsSection() {
    final front = _documentOf('aadhaar_front');
    final back = _documentOf('aadhaar_back');
    final tiles = <Widget>[
      _documentTile('Aadhaar Front', front),
      _documentTile('Aadhaar Back', back),
    ];
    return SurfaceCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        IconBadge(Icons.badge_rounded, color: const Color(0xFF6366F1), size: 36, iconSize: 18),
        const SizedBox(width: 10),
        Text('Documents', style: context.typo.titleMedium),
      ]),
      const SizedBox(height: 12),
      Row(children: [
        Expanded(child: tiles[0]),
        const SizedBox(width: 12),
        Expanded(child: tiles[1]),
      ]),
    ]));
  }

  Widget _documentTile(String label, Map<String, dynamic>? doc) {
    final url = (doc?['url'] ?? '').toString();
    return Container(
      height: 130,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: context.tokens.surfaceAlt,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: context.tokens.border),
      ),
      child: url.isNotEmpty
          ? Stack(fit: StackFit.expand, children: [
              Image.network(url, fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const Icon(Icons.image_not_supported_rounded, color: Colors.black26),
                loadingBuilder: (_, child, progress) => progress == null ? child : const Center(child: SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))),
              ),
              Positioned(bottom: 0, left: 0, right: 0, child: Container(
                color: Colors.black54,
                padding: const EdgeInsets.symmetric(vertical: 5),
                child: Text(label, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w700)),
              )),
              Positioned.fill(child: Material(color: Colors.transparent, child: InkWell(onTap: () => _showDocumentViewer(url, label)))),
              Positioned(top: 4, right: 4, child: InkWell(
                onTap: () => _deleteDocument(label, doc),
                child: Container(padding: const EdgeInsets.all(5), decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle), child: const Icon(Icons.delete_outline_rounded, size: 14, color: Colors.white)),
              )),
            ])
          : Center(child: Padding(
              padding: const EdgeInsets.all(8),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.badge_outlined, size: 26, color: context.tokens.textTertiary),
                const SizedBox(height: 4),
                Text('$label — not uploaded', textAlign: TextAlign.center, style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary, fontSize: 9.5)),
              ]),
            )),
    );
  }

  void _showDocumentViewer(String url, String label) {
    showGeneralDialog(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Close',
      barrierColor: Colors.black.withOpacity(.4),
      pageBuilder: (_, __, ___) => BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 8, sigmaY: 8),
        child: GestureDetector(
          onTap: () => Navigator.pop(context),
          child: Center(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text(label, style: GoogleFonts.poppins(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w700)),
              ),
              FractionallySizedBox(
                widthFactor: .88,
                child: ClipRRect(borderRadius: BorderRadius.circular(14), child: InteractiveViewer(child: Image.network(url, fit: BoxFit.contain))),
              ),
            ]),
          ),
        ),
      ),
    );
  }

  Future<void> _deleteDocument(String label, Map<String, dynamic>? doc) async {
    final docType = (doc?['doc_type'] ?? '').toString();
    if (docType.isEmpty) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Remove $label?'),
        content: const Text('This document will be permanently deleted.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Remove')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(apiClientProvider).deleteMemberDocument(widget.memberId, docType);
      if (!mounted) return;
      setState(() => _documents = _documents.where((d) => !(d is Map && (d['doc_type'] ?? '').toString() == docType)).toList());
      Toast.success(context, 'Document removed');
    } catch (_) {
      if (mounted) Toast.error(context, 'Could not remove document');
    }
  }

  // ── Documents manager (capture/retake/remove for existing members) ─
  void _showDocumentsSheet() {
    showAppSheet(context, child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          IconBadge(Icons.badge_rounded, color: const Color(0xFF6366F1), size: 42, iconSize: 20),
          const SizedBox(width: 12),
          Expanded(child: Text('Documents (Aadhaar)', style: context.typo.titleLarge)),
        ]),
        const SizedBox(height: 6),
        Text('Capture or update the member\u2019s Aadhaar card.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
        const SizedBox(height: 16),
        Row(children: [
          Expanded(child: _docActionTile('aadhaar_front', 'Aadhaar Front')),
          const SizedBox(width: 12),
          Expanded(child: _docActionTile('aadhaar_back', 'Aadhaar Back')),
        ]),
      ]),
    ));
  }

  Widget _docMiniBtn(IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(6),
        decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
        child: Icon(icon, size: 15, color: Colors.white),
      ),
    );
  }

  Widget _docActionTile(String docType, String label) {
    final doc = _documentOf(docType);
    final url = (doc?['url'] ?? '').toString();
    return Container(
      height: 150,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: context.tokens.surfaceAlt,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: url.isNotEmpty ? AppTheme.success.withOpacity(.5) : context.tokens.border),
      ),
      child: url.isNotEmpty
          ? Stack(fit: StackFit.expand, children: [
              Image.network(url, fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const Icon(Icons.image_not_supported_rounded, color: Colors.black26),
                loadingBuilder: (_, child, progress) => progress == null ? child : const Center(child: SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))),
              ),
              Positioned.fill(child: Material(color: Colors.transparent, child: InkWell(onTap: () => _showDocumentViewer(url, label)))),
              Positioned(bottom: 0, left: 0, right: 0, child: Container(
                color: Colors.black54,
                padding: const EdgeInsets.symmetric(vertical: 5),
                child: Text(label, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 10.5, fontWeight: FontWeight.w700)),
              )),
              Positioned(top: 6, right: 6, child: Row(children: [
                _docMiniBtn(Icons.refresh_rounded, () => _captureDocument(docType, label)),
                const SizedBox(width: 6),
                _docMiniBtn(Icons.delete_outline_rounded, () => _deleteDocument(label, doc)),
              ])),
            ])
          : InkWell(
              onTap: () => _captureDocument(docType, label),
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Icon(Icons.add_a_photo_rounded, size: 30, color: context.tokens.textTertiary),
                const SizedBox(height: 8),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  child: Text(label, textAlign: TextAlign.center, style: context.typo.labelMedium?.copyWith(color: context.tokens.textSecondary, fontWeight: FontWeight.w700)),
                ),
                const SizedBox(height: 4),
                Text('Tap to capture', style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary, fontSize: 9.5)),
              ]),
            ),
    );
  }

  Future<void> _captureDocument(String docType, String label) async {
    try {
      // Compress/downscale at capture so the upload is small and fast.
      final image = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 80, maxWidth: 1600, maxHeight: 1000);
      if (image == null) return;
      final cropped = await ImageCropper().cropImage(
        sourcePath: image.path,
        aspectRatio: const CropAspectRatio(ratioX: 159, ratioY: 100),
        uiSettings: [
          AndroidUiSettings(toolbarTitle: 'Crop $label', lockAspectRatio: true),
          IOSUiSettings(title: 'Crop $label', aspectRatioLockEnabled: true),
        ],
      );
      if (cropped == null) return;
      await _uploadDocument(docType, File(cropped.path), label);
    } catch (_) {
      if (mounted) Toast.error(context, 'Could not capture document');
    }
  }

  /// Uploads a single document with a live progress dialog (back disabled).
  Future<void> _uploadDocument(String docType, File file, String label) async {
    if (!mounted) return;
    double progress = 0;
    StateSetter? setDialog;

    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (_) => PopScope(
        canPop: false,
        child: StatefulBuilder(
          builder: (ctx, setD) {
            setDialog = setD;
            final pct = (progress * 100).clamp(0, 100).toInt();
            final done = pct >= 100;
            return AlertDialog(
              title: Text(done ? 'Done' : 'Saving document'),
              content: Column(mainAxisSize: MainAxisSize.min, children: [
                Text(done ? '$label saved' : 'Uploading $label…', style: context.typo.bodySmall?.copyWith(color: context.tokens.textSecondary)),
                const SizedBox(height: 16),
                ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: LinearProgressIndicator(value: progress.clamp(0.0, 1.0), minHeight: 8, backgroundColor: context.tokens.surfaceAlt, color: AppTheme.brand),
                ),
                const SizedBox(height: 10),
                Text('$pct%', style: context.typo.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                if (!done) ...[
                  const SizedBox(height: 10),
                  Text('Please wait, do not press back', style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary)),
                ],
              ]),
            );
          },
        ),
      ),
    );

    try {
      await ref.read(apiClientProvider).uploadMemberDocument(widget.memberId, docType, file, onProgress: (sent, total) {
        final frac = total > 0 ? (sent / total) : 0;
        progress = frac.clamp(0.0, 1.0);
        setDialog?.call(() {});
      });
      progress = 1.0;
      setDialog?.call(() {});
      await Future.delayed(const Duration(milliseconds: 700));
      if (mounted) Navigator.of(context, rootNavigator: true).pop();

      // Refresh documents so the inline section + sheet update.
      try {
        final res = await ref.read(apiClientProvider).getMemberDocuments(widget.memberId);
        if (mounted) setState(() => _documents = (res['documents'] as List?) ?? []);
      } catch (_) {}
      if (mounted) Toast.success(context, '$label saved');
    } catch (_) {
      if (mounted) {
        Navigator.of(context, rootNavigator: true).pop();
        Toast.error(context, 'Could not upload document');
      }
    }
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
    final oldExpiry = DateTime.tryParse((member?['membership_expiry_date'] ?? '').toString());
    final minimumRenewalStart = (oldExpiry ?? DateTime.now()).add(const Duration(days: 1));
    DateTime? start = minimumRenewalStart;
    DateTime? expiry = minimumRenewalStart.add(const Duration(days: 30));
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
          const SizedBox(height: 8),
          Text('Renewal starts from ${DateFormatter.formatDate("${minimumRenewalStart.year}-${minimumRenewalStart.month.toString().padLeft(2, '0')}-${minimumRenewalStart.day.toString().padLeft(2, '0')}")}.', style: context.typo.bodySmall?.copyWith(color: AppTheme.info)),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: _dateField(ctx, 'Start Date', start!, (d) => setSheet(() { start = d; recalc(); }), minimumRenewalStart)),
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
  Future<void> _showWorkoutSheet() async {
    Map<String, dynamic>? existing;
    try {
      final response = await ref.read(apiClientProvider).getWorkouts(userId: widget.memberId);
      final list = (response['workouts'] as List?) ?? const [];
      if (list.isNotEmpty) existing = Map<String, dynamic>.from(list.first as Map);
    } catch (_) {}
    final notesCtrl = TextEditingController(text: existing?['notes']?.toString() ?? '');
    DateTime start = DateTime.tryParse(existing?['start_date']?.toString() ?? '') ?? DateTime.now();
    final days = <Map<String, dynamic>>[
      {'day': 'Monday', 'ctrl': TextEditingController()},
    ];
    if (existing?['workout_history'] != null) {
      try {
        final oldPlan = jsonDecode(existing!['workout_history'].toString()) as List;
        days
          ..clear()
          ..addAll(oldPlan.map((d) => {'day': d['day'] ?? 'Monday', 'ctrl': TextEditingController(text: ((d['exercises'] as List?) ?? const []).join('\n'))}));
      } catch (_) {}
    }
    const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [IconBadge(Icons.fitness_center_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text(existing == null ? 'Assign Workout' : 'Edit Workout', style: context.typo.titleLarge)]),
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
          FireButton(label: existing == null ? 'Assign Workout' : 'Save Workout', onPressed: () async {
            // Build the same JSON structure the backend expects.
            final plan = days.map((d) {
              final lines = (d['ctrl'] as TextEditingController).text.split('\n').map((l) => l.trim()).where((l) => l.isNotEmpty).toList();
              return {'day': d['day'], 'exercises': lines};
            }).where((d) => (d['exercises'] as List).isNotEmpty).toList();
            if (plan.isEmpty) { Toast.error(ctx, 'Add at least one exercise'); return; }
            try {
              final workoutData = {
                'user_id': widget.memberId,
                'workout_plan': jsonEncode(plan),
                'notes': notesCtrl.text.trim(),
                'start_date': "${start.year}-${start.month.toString().padLeft(2, '0')}-${start.day.toString().padLeft(2, '0')}",
              };
              if (existing == null) {
                await ref.read(apiClientProvider).createWorkout(workoutData);
              } else {
                await ref.read(apiClientProvider).updateWorkout(existing['id'], workoutData);
              }
              if (mounted) Navigator.pop(ctx);
              if (mounted) Toast.success(context, existing == null ? 'Workout assigned' : 'Workout updated');
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
          const SizedBox(height: 14),
          OutlinedButton.icon(onPressed: () { Navigator.pop(ctx); _showPhotoOptions(); }, icon: const Icon(Icons.camera_alt_rounded, size: 18), label: const Text('Edit Profile Photo')),
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
    final confirm1 = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Member?'),
        content: const Text('This will delete member + invoices, payments, attendance, health, lockers, workouts. Cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirm1 != true) return;

    final confirm2 = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Final confirmation'),
        content: const Text('Are you absolutely sure? This action is permanent.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Yes, Delete'),
          ),
        ],
      ),
    );
    if (confirm2 != true) return;

    try {
      await ref.read(apiClientProvider).hardDeleteMember(widget.memberId);
      if (mounted) {
        Toast.success(context, 'Member deleted');
        // Pop with a result so list screens can refresh immediately.
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) {
        final message = e.toString().contains('Permission denied')
            ? 'Your role does not allow deleting members. Please contact your Gym Owner.'
            : 'Failed to delete member';
        Toast.error(context, message);
      }
    }
  }
}

/// Skeleton that mirrors the member-detail layout (hero → action tiles →
/// section cards) using the light surface tokens.
class _MemberDetailSkeleton extends StatelessWidget {
  const _MemberDetailSkeleton();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
      children: [
        // Light hero card
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: context.tokens.surface,
            borderRadius: BorderRadius.circular(26),
            border: Border.all(color: context.tokens.border),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              const ShimmerBox(width: 46, height: 46, radius: 16),
              const SizedBox(width: 12),
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: const [
                ShimmerBox(width: 140, height: 13, radius: 6),
                SizedBox(height: 8),
                ShimmerBox(width: 90, height: 10, radius: 5),
              ]),
              const Spacer(),
              const ShimmerBox(width: 56, height: 22, radius: 999),
            ]),
            const SizedBox(height: 16),
            const ShimmerBox(width: 90, height: 11, radius: 5),
            const SizedBox(height: 8),
            const ShimmerBox(height: 8, radius: 4),
          ]),
        ),
        const SizedBox(height: 14),
        // Tonal action tiles (2x2)
        Row(children: const [
          Expanded(child: ShimmerBox(height: 62, radius: 16)),
          SizedBox(width: 10),
          Expanded(child: ShimmerBox(height: 62, radius: 16)),
        ]),
        const SizedBox(height: 10),
        Row(children: const [
          Expanded(child: ShimmerBox(height: 62, radius: 16)),
          SizedBox(width: 10),
          Expanded(child: ShimmerBox(height: 62, radius: 16)),
        ]),
        const SizedBox(height: 10),
        const ShimmerBox(height: 58, radius: 16),
        const SizedBox(height: 18),
        // Section cards
        const ShimmerBox(height: 120, radius: 18),
        const SizedBox(height: 12),
        const ShimmerBox(height: 120, radius: 18),
      ],
    );
  }
}
