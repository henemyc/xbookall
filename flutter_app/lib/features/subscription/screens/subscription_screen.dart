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
          ? const SkeletonGrid()
          : error != null
              ? ErrorRetry(message: 'Could not load subscription.', onRetry: _load)
              : RefreshIndicator(
                  color: AppTheme.brand,
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 40),
                    children: [
                      FadeInUp(child: _salesHero()),
                      const SizedBox(height: 14),
                      FadeInUp(delayMs: 40, child: _trustStrip()),
                      const SizedBox(height: 22),
                      Row(children: [
                        Expanded(child: Text('Choose your growth plan', style: context.typo.titleLarge?.copyWith(fontWeight: FontWeight.w900))),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(color: AppTheme.success.withOpacity(.12), borderRadius: BorderRadius.circular(999), border: Border.all(color: AppTheme.success.withOpacity(.22))),
                          child: Text('Save more yearly', style: GoogleFonts.poppins(fontSize: 11, fontWeight: FontWeight.w800, color: AppTheme.success)),
                        ),
                      ]),
                      const SizedBox(height: 6),
                      Text('Silver is recommended for gyms that want staff, trainers, lockers and faster growth.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, height: 1.4)),
                      const SizedBox(height: 14),
                      if (tiers.isEmpty)
                        const EmptyState(icon: Icons.workspace_premium_rounded, title: 'Plans not configured', subtitle: 'Please contact support or ask Super Admin to run System Update.')
                      else
                        ...tiers.asMap().entries.map((entry) {
                          final tier = Map<String, dynamic>.from(entry.value as Map);
                          final isCurrent = currentTier != null && currentTier!['id'].toString() == tier['id'].toString();
                          return FadeInUp(delayMs: 70 + entry.key * 45, child: Padding(padding: const EdgeInsets.only(bottom: 16), child: _premiumTierCard(tier, isCurrent)));
                        }),
                      const SizedBox(height: 8),
                      FadeInUp(delayMs: 220, child: _guaranteeCard()),
                    ],
                  ),
                ),
    );
  }

  Widget _salesHero() {
    final expired = isExpired || (daysLeft != null && daysLeft! < 0);
    final expiringSoon = daysLeft != null && daysLeft! >= 0 && daysLeft! <= 7;
    final title = currentTier?['name'] ?? currentLegacy?['title'] ?? 'No active plan';
    final statusColor = expired ? AppTheme.danger : (expiringSoon ? AppTheme.warning : AppTheme.success);
    final urgency = expired
        ? 'Your gym access needs renewal today'
        : expiringSoon
            ? 'Renew early to avoid business interruption'
            : 'Upgrade now to unlock faster gym operations';

    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: AppTheme.darkHeroGradient,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.25), blurRadius: 28, offset: const Offset(0, 12), spreadRadius: -8)],
      ),
      child: Stack(children: [
        Positioned(right: -28, top: -28, child: Icon(Icons.rocket_launch_rounded, size: 145, color: AppTheme.brand.withOpacity(.14))),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), decoration: BoxDecoration(color: Colors.white.withOpacity(.10), borderRadius: BorderRadius.circular(999), border: Border.all(color: Colors.white.withOpacity(.12))), child: Text('CURRENT PLAN', style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w900, color: AppTheme.brandAmber, letterSpacing: 1.2))),
            const Spacer(),
            Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), decoration: BoxDecoration(color: statusColor.withOpacity(.20), borderRadius: BorderRadius.circular(999), border: Border.all(color: statusColor.withOpacity(.35))), child: Text(expired ? 'EXPIRED' : 'ACTIVE', style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w900, color: statusColor))),
          ]),
          const SizedBox(height: 14),
          Text(title.toString(), style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w900, letterSpacing: -0.7)),
          const SizedBox(height: 6),
          Text(urgency, style: GoogleFonts.poppins(color: Colors.white.withOpacity(.74), fontSize: 13.2, height: 1.45)),
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

  Widget _premiumTierCard(Map<String, dynamic> tier, bool isCurrent) {
    final code = (tier['code'] ?? 'bronze').toString().toLowerCase();
    final color = _tierColor(code);
    final prices = (tier['prices'] as List?) ?? [];
    final bestPrice = _bestPrice(prices);
    final features = ((tier['features'] as List?) ?? []).where((f) => f is Map && (f['is_highlighted'] == true || f['is_highlighted'].toString() == '1')).take(6).toList();
    final comingSoon = tier['is_coming_soon'] == true || tier['is_coming_soon'].toString() == '1';
    final isSilver = code == 'silver';
    final isGold = code == 'gold';

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        gradient: isSilver ? LinearGradient(colors: [color.withOpacity(.20), context.tokens.surface]) : null,
        border: Border.all(color: isCurrent ? color : (isSilver ? color.withOpacity(.45) : context.tokens.border), width: isSilver ? 1.5 : 1),
        boxShadow: [BoxShadow(color: (isSilver ? color : Colors.black).withOpacity(isSilver ? .18 : .05), blurRadius: isSilver ? 26 : 16, offset: const Offset(0, 10), spreadRadius: -8)],
        color: context.tokens.surface,
      ),
      child: Stack(children: [
        if (isSilver)
          Positioned(right: -24, top: -26, child: Icon(Icons.workspace_premium_rounded, size: 128, color: color.withOpacity(.08))),
        Padding(
          padding: const EdgeInsets.all(18),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              IconBadge(isGold ? Icons.tips_and_updates_rounded : isSilver ? Icons.workspace_premium_rounded : Icons.shield_rounded, color: color, size: 50),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Flexible(child: Text(tier['name'] ?? '', style: GoogleFonts.spaceGrotesk(fontSize: 22, fontWeight: FontWeight.w900, color: context.tokens.text), overflow: TextOverflow.ellipsis)),
                  if (isCurrent) ...[const SizedBox(width: 6), StatusBadge('CURRENT', color: color)],
                ]),
                const SizedBox(height: 2),
                Text(_salesLine(code), style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, height: 1.35)),
              ])),
            ]),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [
              if (isSilver) _badge('BEST VALUE', AppTheme.success, Icons.local_fire_department_rounded),
              if (isGold) _badge('FUTURE READY', AppTheme.warning, Icons.auto_awesome_rounded),
              if (comingSoon) _badge('COMING SOON', AppTheme.warning, Icons.lock_clock_rounded),
              _badge(_proofText(code), color, Icons.verified_rounded),
            ]),
            const SizedBox(height: 14),
            if (bestPrice != null) _priceHero(bestPrice, color, isSilver),
            const SizedBox(height: 14),
            ...features.map((raw) => _featureLine(Map<String, dynamic>.from(raw as Map), color)).toList(),
            const SizedBox(height: 14),
            if (prices.isNotEmpty) _priceButtons(tier, prices, comingSoon, isCurrent, color),
            const SizedBox(height: 12),
            FireButton(
              label: comingSoon ? 'Notify Me' : isCurrent ? 'Renew ${tier['name']}' : _ctaText(code),
              icon: comingSoon ? Icons.notifications_active_rounded : Icons.arrow_forward_rounded,
              onPressed: comingSoon ? null : () => _openTier(tier, bestPrice, isCurrent),
            ),
          ]),
        ),
      ]),
    );
  }

  Widget _badge(String text, Color color, IconData icon) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(color: color.withOpacity(.12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withOpacity(.22))),
      child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 13, color: color), const SizedBox(width: 4), Text(text, style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w900, color: color))]),
    );
  }

  Widget _priceHero(Map<String, dynamic> price, Color color, bool emphasise) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: color.withOpacity(emphasise ? .14 : .08), borderRadius: BorderRadius.circular(20), border: Border.all(color: color.withOpacity(.20))),
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Starts from', style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary, fontWeight: FontWeight.w700)),
          const SizedBox(height: 2),
          Text('₹${_money(price['price'])}', style: GoogleFonts.spaceGrotesk(fontSize: 30, fontWeight: FontWeight.w900, color: color)),
        ])),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text('${price['duration_months']} month${price['duration_months'].toString() == '1' ? '' : 's'}', style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
          if ((price['discount_text'] ?? '').toString().isNotEmpty) Text(price['discount_text'].toString(), style: context.typo.labelSmall?.copyWith(color: AppTheme.success, fontWeight: FontWeight.w900)),
        ]),
      ]),
    );
  }

  Widget _featureLine(Map<String, dynamic> f, Color color) {
    final raw = f['value'] ?? f['raw_value'];
    final enabled = raw == true || raw.toString() == '1' || raw.toString().toLowerCase() == 'true';
    final isComing = raw.toString() == 'coming_soon';
    final icon = enabled ? Icons.check_circle_rounded : isComing ? Icons.schedule_rounded : Icons.cancel_rounded;
    final iconColor = enabled ? AppTheme.success : isComing ? AppTheme.warning : context.tokens.textTertiary;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(children: [
        Icon(icon, size: 18, color: iconColor),
        const SizedBox(width: 8),
        Expanded(child: Text((f['label'] ?? '').toString(), style: context.typo.bodySmall?.copyWith(fontWeight: FontWeight.w700, color: enabled ? context.tokens.text : context.tokens.textTertiary))),
      ]),
    );
  }

  Widget _priceButtons(Map<String, dynamic> tier, List prices, bool comingSoon, bool isCurrent, Color color) {
    return Wrap(spacing: 8, runSpacing: 8, children: prices.map((raw) {
      final price = Map<String, dynamic>.from(raw as Map);
      final active = price['is_active'] == true || price['is_active'].toString() == '1';
      return ChoiceChip(
        selected: false,
        label: Text('${price['duration_months']}M • ₹${_money(price['price'])}'),
        avatar: active && !comingSoon ? Icon(Icons.bolt_rounded, size: 15, color: color) : const Icon(Icons.lock_clock_rounded, size: 15),
        onSelected: active && !comingSoon ? (_) => _openTier(tier, price, isCurrent) : null,
        labelStyle: context.typo.bodySmall?.copyWith(fontWeight: FontWeight.w900, color: active && !comingSoon ? color : context.tokens.textTertiary),
        backgroundColor: context.tokens.surfaceAlt,
        side: BorderSide(color: color.withOpacity(active && !comingSoon ? .30 : .08)),
      );
    }).toList());
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

  Map<String, dynamic>? _bestPrice(List prices) {
    final active = prices.whereType<Map>().where((p) => p['is_active'] == true || p['is_active'].toString() == '1').map((p) => Map<String, dynamic>.from(p)).toList();
    if (active.isEmpty) return null;
    active.sort((a, b) => (b['duration_months'] ?? 0).toString().compareTo((a['duration_months'] ?? 0).toString()));
    return active.first;
  }

  String _salesLine(String code) {
    if (code == 'silver') return 'Most gyms choose this — staff, trainers, lockers and bulk import included.';
    if (code == 'gold') return 'Future-ready automation for premium gyms and multi-branch growth.';
    return 'Starter essentials. Upgrade to Silver when you need staff, trainers and lockers.';
  }

  String _proofText(String code) {
    if (code == 'silver') return 'Recommended';
    if (code == 'gold') return 'Premium';
    return 'Starter';
  }

  String _ctaText(String code) {
    if (code == 'silver') return 'Unlock Silver Growth';
    if (code == 'gold') return 'Reserve Gold Access';
    return 'Start With Bronze';
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
    if (code == 'silver') return AppTheme.info;
    if (code == 'gold') return AppTheme.warning;
    return AppTheme.brand;
  }

  String _money(dynamic v) {
    final n = num.tryParse(v?.toString() ?? '0') ?? 0;
    return n % 1 == 0 ? n.toInt().toString() : n.toStringAsFixed(2);
  }
}
