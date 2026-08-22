import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'subscription_detail_screen.dart';

class SubscriptionScreen extends ConsumerStatefulWidget {
  const SubscriptionScreen({super.key});
  @override
  ConsumerState<SubscriptionScreen> createState() => _SubscriptionScreenState();
}

class _SubscriptionScreenState extends ConsumerState<SubscriptionScreen> {
  List tiers = [];
  Map<String, dynamic>? currentTier;
  Map<String, dynamic>? currentLegacy;
  int? daysLeft;
  bool isExpired = false;
  bool loading = true;
  String? error;
  String? expiryDate;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final res = await ref.read(apiClientProvider).getSubscriptionPlans();
      if (mounted) {
        setState(() {
          tiers = res['tiers'] ?? [];
          currentTier = res['current_tier'] is Map ? Map<String, dynamic>.from(res['current_tier']) : null;
          currentLegacy = res['current_subscription'] is Map ? Map<String, dynamic>.from(res['current_subscription']) : null;
          daysLeft = res['days_left'] == null ? null : int.tryParse(res['days_left'].toString());
          isExpired = res['is_expired'] == true;
          expiryDate = res['subscription_expire_date']?.toString();
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
      appBar: AppBar(title: const Text('Subscription')),
      body: loading
          ? const _SubscriptionSkeleton()
          : error != null
              ? ErrorRetry(message: 'Could not load subscription.', onRetry: _load)
              : RefreshIndicator(
                  color: AppTheme.brand,
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
                    children: [
                      FadeInUp(child: _hero()),
                      const SizedBox(height: 14),
                      FadeInUp(delayMs: 40, child: _trustStrip()),
                      const SizedBox(height: 26),
                      FadeInUp(delayMs: 60, child: _sectionTitle()),
                      const SizedBox(height: 14),
                      if (tiers.isEmpty)
                        const EmptyState(icon: Icons.workspace_premium_rounded, title: 'Plans not configured', subtitle: 'Please contact support or ask Super Admin to run System Update.')
                      else
                        ...tiers.asMap().entries.map((entry) {
                          final tier = Map<String, dynamic>.from(entry.value as Map);
                          final isCurrent = currentTier != null && currentTier!['id'].toString() == tier['id'].toString();
                          return FadeInUp(delayMs: 80 + entry.key * 50, child: Padding(padding: const EdgeInsets.only(bottom: 16), child: _tierCard(tier, isCurrent)));
                        }),
                      const SizedBox(height: 6),
                      FadeInUp(delayMs: 240, child: _guaranteeCard()),
                    ],
                  ),
                ),
    );
  }

  // ── Hero: current plan status ────────────────────────────────────
  Widget _hero() {
    final expired = isExpired || (daysLeft != null && daysLeft! < 0);
    final expiringSoon = daysLeft != null && daysLeft! >= 0 && daysLeft! <= 7;
    final title = currentTier?['name'] ?? currentLegacy?['title'] ?? 'No active plan';
    final statusColor = expired ? AppTheme.danger : (expiringSoon ? AppTheme.warning : AppTheme.success);
    final statusLabel = expired ? 'EXPIRED' : (expiringSoon ? 'EXPIRING SOON' : 'ACTIVE');
    final urgency = expired
        ? 'Your gym access needs renewal today'
        : expiringSoon
            ? 'Renew early to avoid business interruption'
            : 'Manage your plan and grow with GymXBook';

    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: AppTheme.darkHeroGradient,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.25), blurRadius: 28, offset: const Offset(0, 12), spreadRadius: -8)],
      ),
      child: Stack(children: [
        Positioned(right: -28, top: -28, child: Icon(Icons.workspace_premium_rounded, size: 150, color: AppTheme.brand.withOpacity(.14))),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(color: Colors.white.withOpacity(.10), borderRadius: BorderRadius.circular(999), border: Border.all(color: Colors.white.withOpacity(.12))),
              child: Text('CURRENT PLAN', style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w900, color: AppTheme.brandAmber, letterSpacing: 1.2)),
            ),
            const Spacer(),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(color: statusColor.withOpacity(.20), borderRadius: BorderRadius.circular(999), border: Border.all(color: statusColor.withOpacity(.35))),
              child: Text(statusLabel, style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w900, color: statusColor, letterSpacing: 1.1)),
            ),
          ]),
          const SizedBox(height: 16),
          Text(title.toString(), style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900, letterSpacing: -0.6)),
          const SizedBox(height: 6),
          Text(urgency, style: GoogleFonts.poppins(color: Colors.white.withOpacity(.74), fontSize: 13, height: 1.45)),
          const SizedBox(height: 16),
          Wrap(spacing: 8, runSpacing: 8, children: [
            _heroPill(Icons.shield_rounded, daysLeft == null ? 'No expiry set' : expired ? 'Expired ${daysLeft!.abs()} days ago' : '$daysLeft days left', statusColor),
            if (expiryDate != null && expiryDate!.isNotEmpty) _heroPill(Icons.event_rounded, 'Valid till ${DateFormatter.formatDate(expiryDate)}', AppTheme.info),
          ]),
        ]),
      ]),
    );
  }

  Widget _heroPill(IconData icon, String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
      decoration: BoxDecoration(color: color.withOpacity(.18), borderRadius: BorderRadius.circular(14), border: Border.all(color: color.withOpacity(.32))),
      child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 15, color: color), const SizedBox(width: 6), Text(text, style: GoogleFonts.poppins(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700))]),
    );
  }

  Widget _trustStrip() {
    return Row(children: [
      Expanded(child: _trustItem(Icons.flash_on_rounded, 'Instant', 'activation')),
      const SizedBox(width: 8),
      Expanded(child: _trustItem(Icons.lock_rounded, 'Secure', 'payments')),
      const SizedBox(width: 8),
      Expanded(child: _trustItem(Icons.support_agent_rounded, 'Support', 'included')),
    ]);
  }

  Widget _trustItem(IconData icon, String title, String subtitle) {
    return SurfaceCard(
      padding: const EdgeInsets.all(11),
      radius: 16,
      child: Column(children: [
        Icon(icon, color: AppTheme.brand, size: 20),
        const SizedBox(height: 5),
        Text(title, style: context.typo.labelLarge?.copyWith(fontWeight: FontWeight.w900)),
        Text(subtitle, style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary, fontSize: 10)),
      ]),
    );
  }

  Widget _sectionTitle() {
    return Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Choose your plan', style: context.typo.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
        const SizedBox(height: 3),
        Text('Flexible durations · cancel-friendly · upgrade anytime', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
      ])),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(color: AppTheme.success.withOpacity(.12), borderRadius: BorderRadius.circular(999), border: Border.all(color: AppTheme.success.withOpacity(.22))),
        child: Text('Save more yearly', style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w800, color: AppTheme.success)),
      ),
    ]);
  }

  // ── Tier card ─────────────────────────────────────────────────────
  Widget _tierCard(Map<String, dynamic> tier, bool isCurrent) {
    final code = (tier['code'] ?? 'bronze').toString().toLowerCase();
    final color = _tierColor(code);
    final prices = (tier['prices'] as List?) ?? [];
    final bestPrice = _bestPrice(prices);
    final comingSoon = tier['is_coming_soon'] == true || tier['is_coming_soon'].toString() == '1';
    final recommended = code == 'silver';
    final cardFeatures = _cardFeatures(tier);

    final icon = code == 'gold'
        ? Icons.workspace_premium_rounded
        : code == 'silver'
            ? Icons.auto_awesome_rounded
            : Icons.shield_rounded;

    return Container(
      decoration: BoxDecoration(
        // Soft plan-tinted background: bronze → warm brown, silver → blue,
        // gold → amber.
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            color.withOpacity(.10),
            color.withOpacity(.035),
            context.tokens.surface,
          ],
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: isCurrent ? color : (recommended ? color.withOpacity(.5) : color.withOpacity(.25)), width: recommended || isCurrent ? 1.6 : 1),
        boxShadow: [BoxShadow(color: (recommended ? color : Colors.black).withOpacity(recommended ? .16 : .05), blurRadius: recommended ? 24 : 14, offset: const Offset(0, 8), spreadRadius: -8)],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(children: [
        // Top accent strip
        Positioned(left: 0, right: 0, top: 0, child: Container(height: 5, decoration: BoxDecoration(gradient: LinearGradient(colors: [color, color.withOpacity(.55)])))),
        if (recommended)
          Positioned(
            right: 0, top: 5,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(color: color, borderRadius: const BorderRadius.only(bottomLeft: Radius.circular(12))),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.local_fire_department_rounded, size: 13, color: Colors.white),
                const SizedBox(width: 4),
                Text('MOST POPULAR', style: GoogleFonts.poppins(fontSize: 9.5, fontWeight: FontWeight.w900, color: Colors.white, letterSpacing: .8)),
              ]),
            ),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(18, 22, 18, 18),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Container(
                width: 50, height: 50,
                decoration: BoxDecoration(color: color.withOpacity(.13), borderRadius: BorderRadius.circular(16), border: Border.all(color: color.withOpacity(.2))),
                child: Icon(icon, color: color, size: 25),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Flexible(child: Text(tier['name'] ?? '', style: GoogleFonts.spaceGrotesk(fontSize: 20, fontWeight: FontWeight.w900, color: context.tokens.text), overflow: TextOverflow.ellipsis)),
                  if (isCurrent) ...[const SizedBox(width: 7), StatusBadge('CURRENT', color: color)],
                ]),
                const SizedBox(height: 3),
                Text((tier['badge_text'] ?? code.toUpperCase()).toString(), style: GoogleFonts.poppins(fontSize: 11, fontWeight: FontWeight.w700, color: color, letterSpacing: .4)),
              ])),
            ]),
            if ((tier['description'] ?? '').toString().isNotEmpty) ...[
              const SizedBox(height: 12),
              Text((tier['description'] ?? '').toString(), style: context.typo.bodySmall?.copyWith(color: context.tokens.textSecondary, height: 1.45)),
            ],
            const SizedBox(height: 16),
            // Price hero
            if (bestPrice != null && !comingSoon)
              Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                Text('₹${_money(bestPrice['price'])}', style: GoogleFonts.spaceGrotesk(fontSize: 30, fontWeight: FontWeight.w900, color: color, height: 1)),
                Padding(
                  padding: const EdgeInsets.only(bottom: 3, left: 4),
                  child: Text('/ ${bestPrice['duration_months']} mo', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                ),
                const Spacer(),
                if ((bestPrice['discount_text'] ?? '').toString().isNotEmpty)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                    decoration: BoxDecoration(color: AppTheme.success.withOpacity(.12), borderRadius: BorderRadius.circular(8)),
                    child: Text(bestPrice['discount_text'].toString(), style: context.typo.labelSmall?.copyWith(color: AppTheme.success, fontWeight: FontWeight.w900)),
                  ),
              ])
            else if (comingSoon)
              Text('Coming soon', style: GoogleFonts.spaceGrotesk(fontSize: 24, fontWeight: FontWeight.w900, color: context.tokens.textTertiary)),
            const SizedBox(height: 14),
            // Duration pills
            if (prices.isNotEmpty && !comingSoon) ...[
              Wrap(spacing: 8, runSpacing: 8, children: prices.map((raw) {
                final p = Map<String, dynamic>.from(raw as Map);
                final active = p['is_active'] == true || p['is_active'].toString() == '1';
                return _durationPill(p, active, color);
              }).toList()),
              const SizedBox(height: 14),
            ],
            Divider(height: 1, color: context.tokens.border),
            const SizedBox(height: 14),
            // Features — card features from Super Admin (not backend limits)
            if (cardFeatures.isNotEmpty)
              ...cardFeatures.map((cf) => _featureLine(cf)).toList()
            else
              Padding(padding: const EdgeInsets.only(bottom: 8), child: Text('Feature list coming soon.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: Pressable(
                radius: 16,
                onTap: comingSoon ? () {} : () => _openTier(tier, bestPrice, isCurrent),
                child: Container(
                  height: 52,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    gradient: comingSoon ? null : LinearGradient(colors: [color, color.withOpacity(.8)]),
                    color: comingSoon ? context.tokens.surfaceAlt : null,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: comingSoon ? null : [BoxShadow(color: color.withOpacity(.35), blurRadius: 16, offset: const Offset(0, 8), spreadRadius: -6)],
                  ),
                  child: Text(
                    comingSoon ? 'Notify Me' : (isCurrent ? 'Manage ${tier['name']}' : 'Choose ${tier['name']}'),
                    style: GoogleFonts.poppins(fontSize: 15, fontWeight: FontWeight.w800, color: comingSoon ? context.tokens.textTertiary : Colors.white),
                  ),
                ),
              ),
            ),
          ]),
        ),
      ]),
    );
  }

  Widget _durationPill(Map<String, dynamic> price, bool active, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: active ? color.withOpacity(.08) : context.tokens.surfaceAlt,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: active ? color.withOpacity(.3) : context.tokens.border),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Text('${price['duration_months']} mo', style: context.typo.labelMedium?.copyWith(fontWeight: FontWeight.w800, color: active ? color : context.tokens.textSecondary)),
        const SizedBox(width: 6),
        Text('₹${_money(price['price'])}', style: context.typo.labelMedium?.copyWith(fontWeight: FontWeight.w700, color: context.tokens.text)),
      ]),
    );
  }

  Widget _featureLine(Map<String, dynamic> f) {
    final included = f['is_included'] == true || f['is_included'].toString() == '1';
    final tooltip = (f['tooltip'] ?? '').toString().trim();
    final label = (f['label'] ?? '').toString();
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(included ? Icons.check_circle_rounded : Icons.cancel_rounded, size: 19, color: included ? AppTheme.success : context.tokens.textTertiary.withOpacity(.7)),
        const SizedBox(width: 9),
        Expanded(child: Text(
          label,
          style: context.typo.bodySmall?.copyWith(fontWeight: FontWeight.w600, height: 1.35, color: included ? context.tokens.text : context.tokens.textTertiary),
        )),
        if (tooltip.isNotEmpty)
          Pressable(
            radius: 10,
            onTap: () => _showFeatureInfo(label, tooltip),
            child: Padding(padding: const EdgeInsets.only(left: 6, top: 1), child: Icon(Icons.info_outline_rounded, size: 16, color: context.tokens.textTertiary)),
          ),
      ]),
    );
  }

  /// Tap on the info icon → opens a small bottom sheet (replaces the tooltip).
  void _showFeatureInfo(String label, String tooltip) {
    showAppSheet(
      context,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            IconBadge(Icons.info_outline_rounded, color: AppTheme.info, size: 42, iconSize: 20),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: context.typo.titleLarge, maxLines: 2, overflow: TextOverflow.ellipsis)),
          ]),
          const SizedBox(height: 14),
          Text(tooltip, style: context.typo.bodyLarge?.copyWith(color: context.tokens.textSecondary, height: 1.55)),
        ]),
      ),
    );
  }

  Widget _guaranteeCard() {
    return SurfaceCard(
      padding: const EdgeInsets.all(15),
      color: AppTheme.success.withOpacity(.06),
      border: Border.all(color: AppTheme.success.withOpacity(.16)),
      child: Row(children: [
        IconBadge(Icons.verified_user_rounded, color: AppTheme.success, size: 40),
        const SizedBox(width: 12),
        Expanded(child: Text('Secure checkout, instant activation and support included. Upgrade anytime as your gym grows.', style: context.typo.bodySmall?.copyWith(height: 1.4, fontWeight: FontWeight.w700, color: AppTheme.success))),
      ]),
    );
  }

  // ── Helpers ───────────────────────────────────────────────────────
  List<Map<String, dynamic>> _cardFeatures(Map<String, dynamic> tier) {
    return ((tier['card_features'] as List?) ?? []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
  }

  Map<String, dynamic>? _bestPrice(List prices) {
    final active = prices.whereType<Map>().where((p) => p['is_active'] == true || p['is_active'].toString() == '1').map((p) => Map<String, dynamic>.from(p)).toList();
    if (active.isEmpty) return null;
    active.sort((a, b) => (int.tryParse((a['duration_months'] ?? 0).toString()) ?? 0).compareTo(int.tryParse((b['duration_months'] ?? 0).toString()) ?? 0));
    return active.first;
  }

  Future<void> _openTier(Map<String, dynamic> tier, Map<String, dynamic>? price, bool isCurrent) async {
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => SubscriptionDetailScreen(tier: tier, selectedPrice: price, isCurrent: isCurrent)),
    );
    if (changed == true && mounted) {
      await ref.read(authProvider.notifier).checkAuth();
      _load();
    }
  }

  Color _tierColor(String code) {
    if (code == 'silver') return const Color(0xFF2563EB);
    if (code == 'gold') return const Color(0xFFD97706);
    return const Color(0xFFB45309);
  }

  String _money(dynamic v) {
    final n = num.tryParse(v?.toString() ?? '0') ?? 0;
    return n % 1 == 0 ? n.toInt().toString() : n.toStringAsFixed(2);
  }
}

/// Skeleton that mirrors the subscription layout: dark hero → trust strip →
/// section title → tier cards.
class _SubscriptionSkeleton extends StatelessWidget {
  const _SubscriptionSkeleton();

  Widget _tierCard(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: context.tokens.surface,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: context.tokens.border),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const ShimmerBox(width: 50, height: 50, radius: 16),
          const SizedBox(width: 12),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: const [
            ShimmerBox(width: 110, height: 16, radius: 6),
            SizedBox(height: 8),
            ShimmerBox(width: 70, height: 11, radius: 5),
          ]),
        ]),
        const SizedBox(height: 16),
        const ShimmerBox(width: 120, height: 26, radius: 8),
        const SizedBox(height: 14),
        Row(children: const [
          Expanded(child: ShimmerBox(height: 34, radius: 12)),
          SizedBox(width: 8),
          Expanded(child: ShimmerBox(height: 34, radius: 12)),
        ]),
        const SizedBox(height: 14),
        const Divider(),
        const SizedBox(height: 14),
        const ShimmerBox(height: 14, radius: 6),
        const SizedBox(height: 10),
        const ShimmerBox(height: 14, radius: 6),
        const SizedBox(height: 10),
        const ShimmerBox(width: 200, height: 14, radius: 6),
        const SizedBox(height: 16),
        const ShimmerBox(height: 52, radius: 16),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
      children: [
        // Light hero card
        Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            color: context.tokens.surface,
            borderRadius: BorderRadius.circular(26),
            border: Border.all(color: context.tokens.border),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: const [
              ShimmerBox(width: 76, height: 22, radius: 999),
              Spacer(),
              ShimmerBox(width: 60, height: 22, radius: 999),
            ]),
            const SizedBox(height: 16),
            const ShimmerBox(width: 170, height: 22, radius: 7),
            const SizedBox(height: 10),
            const ShimmerBox(width: 240, height: 12, radius: 5),
          ]),
        ),
        const SizedBox(height: 14),
        // Trust strip
        Row(children: const [
          Expanded(child: ShimmerBox(height: 74, radius: 16)),
          SizedBox(width: 8),
          Expanded(child: ShimmerBox(height: 74, radius: 16)),
          SizedBox(width: 8),
          Expanded(child: ShimmerBox(height: 74, radius: 16)),
        ]),
        const SizedBox(height: 26),
        const ShimmerBox(width: 150, height: 18, radius: 6),
        const SizedBox(height: 14),
        _tierCard(context),
        const SizedBox(height: 16),
        _tierCard(context),
      ],
    );
  }
}
