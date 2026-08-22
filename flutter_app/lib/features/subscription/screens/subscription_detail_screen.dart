import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class SubscriptionDetailScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> tier;
  final Map<String, dynamic>? selectedPrice;
  final bool isCurrent;

  const SubscriptionDetailScreen({super.key, required this.tier, this.selectedPrice, this.isCurrent = false});

  @override
  ConsumerState<SubscriptionDetailScreen> createState() => _SubscriptionDetailScreenState();
}

class _SubscriptionDetailScreenState extends ConsumerState<SubscriptionDetailScreen> {
  Map<String, dynamic>? selectedPrice;
  bool processing = false;
  bool waiting = false;
  String? orderId;
  String? paymentLink;
  Timer? pollTimer;

  @override
  void initState() {
    super.initState();
    final prices = (widget.tier['prices'] as List?) ?? [];
    final activePrices = prices.where((p) => p is Map && (p['is_active'] == true || p['is_active'].toString() == '1')).cast<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
    selectedPrice = widget.selectedPrice ?? (activePrices.isNotEmpty ? activePrices.first : null);
  }

  @override
  void dispose() {
    pollTimer?.cancel();
    super.dispose();
  }

  Map<String, dynamic> get tier => widget.tier;
  bool get comingSoon => tier['is_coming_soon'] == true || tier['is_coming_soon'].toString() == '1';
  Color get color => _tierColor(tier['code']?.toString() ?? 'bronze');

  Future<void> _checkout() async {
    final price = selectedPrice;
    if (price == null || processing || waiting) return;
    setState(() => processing = true);
    try {
      final res = await ref.read(apiClientProvider).createSubscriptionPaymentLink(
        tierId: int.tryParse(tier['id'].toString()),
        tierPriceId: int.tryParse(price['id'].toString()),
        type: widget.isCurrent ? 'renew' : 'upgrade',
      );
      final link = res['payment_link']?.toString();
      final oid = res['order_id']?.toString();
      if (link == null || link.isEmpty || oid == null || oid.isEmpty) {
        Toast.error(context, (res['error'] ?? res['message'] ?? 'Could not create payment link').toString());
        return;
      }
      orderId = oid;
      paymentLink = link;
      final opened = await launchUrl(Uri.parse(link), mode: LaunchMode.externalApplication);
      if (!opened) {
        Toast.error(context, 'Could not open payment page');
        return;
      }
      if (!mounted) return;
      setState(() { waiting = true; processing = false; });
      _startPolling();
    } catch (e) {
      if (mounted) Toast.error(context, _friendly(e));
    } finally {
      if (mounted && !waiting) setState(() => processing = false);
    }
  }

  void _startPolling() {
    pollTimer?.cancel();
    pollTimer = Timer.periodic(const Duration(seconds: 5), (_) => _checkNow(silent: true));
  }

  Future<void> _checkNow({bool silent = false}) async {
    if (orderId == null) return;
    try {
      final res = await ref.read(apiClientProvider).verifySubscriptionPayment(orderId: orderId!);
      final status = (res['status'] ?? '').toString().toUpperCase();
      if (status == 'PAID' || res['new_expiry'] != null) {
        pollTimer?.cancel();
        if (!mounted) return;
        await ref.read(authProvider.notifier).checkAuth();
        setState(() => waiting = false);
        Toast.success(context, 'Subscription activated successfully');
        Navigator.pop(context, true);
      } else if (['FAILED', 'CANCELLED', 'EXPIRED', 'USER_DROPPED'].contains(status)) {
        pollTimer?.cancel();
        if (mounted) setState(() => waiting = false);
        if (mounted) Toast.error(context, 'Payment ${status.toLowerCase()}');
      } else if (!silent && mounted) {
        Toast.info(context, 'Payment is still pending');
      }
    } catch (e) {
      if (!silent && mounted) Toast.error(context, _friendly(e));
    }
  }

  String _friendly(dynamic e) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) return (data['error'] ?? data['message'] ?? 'Payment failed').toString();
    } catch (_) {}
    return e.toString().contains('connection') ? 'No internet connection' : 'Payment failed. Please try again.';
  }

  @override
  Widget build(BuildContext context) {
    final prices = ((tier['prices'] as List?) ?? []).where((p) => p is Map).map((p) => Map<String, dynamic>.from(p as Map)).toList();
    final cardFeatures = _cardFeatures();
    final price = selectedPrice;
    final priceActive = price != null && (price['is_active'] == true || price['is_active'].toString() == '1');

    return Scaffold(
      appBar: AppBar(title: Text(tier['name']?.toString() ?? 'Plan Details')),
      backgroundColor: context.tokens.bg,
      body: Stack(children: [
        ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 130),
          children: [
            _hero(),
            const SizedBox(height: 20),
            _sectionTitle('Choose Duration'),
            const SizedBox(height: 12),
            if (prices.isEmpty)
              SurfaceCard(child: Text('No pricing options configured yet.', style: context.typo.bodyMedium))
            else
              ...prices.map((p) => _durationCard(p, price)),
            const SizedBox(height: 22),
            _sectionTitle("What's included"),
            const SizedBox(height: 12),
            SurfaceCard(child: Column(children: cardFeatures.isNotEmpty
                ? cardFeatures.map((cf) => _cardFeatureRow(cf)).toList()
                : [Padding(padding: const EdgeInsets.symmetric(vertical: 8), child: Text('Feature list coming soon.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)))])),
            const SizedBox(height: 16),
            SurfaceCard(
              color: AppTheme.info.withOpacity(0.08),
              border: Border.all(color: AppTheme.info.withOpacity(0.20)),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                IconBadge(Icons.info_outline_rounded, color: AppTheme.info, size: 40, iconSize: 19),
                const SizedBox(width: 12),
                Expanded(child: Text(
                  comingSoon
                      ? 'This plan is marked as Coming Soon by Super Admin. Pricing can be managed now and payment activation will be enabled later.'
                      : priceActive
                          ? 'Payment activation for tier pricing will be connected in the next phase. This screen is ready for the new Bronze/Silver/Gold flow.'
                          : 'Select an active duration to continue once payments are enabled.',
                  style: context.typo.bodySmall?.copyWith(color: AppTheme.info, height: 1.45),
                )),
              ]),
            ),
          ],
        ),
        if (waiting) _waitingOverlay(),
      ]),
      bottomNavigationBar: waiting ? null : Container(
        decoration: BoxDecoration(
          color: context.tokens.surface,
          border: Border(top: BorderSide(color: context.tokens.border)),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(.06), blurRadius: 20, offset: const Offset(0, -6), spreadRadius: -8)],
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
            child: Row(children: [
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                Text(comingSoon ? tier['name'] ?? '' : 'Total', style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary, fontWeight: FontWeight.w700)),
                Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  Text(
                    comingSoon ? 'Coming soon' : '₹${price != null ? _money(price['price']) : '—'}',
                    style: GoogleFonts.spaceGrotesk(fontSize: 20, fontWeight: FontWeight.w900, color: context.tokens.text),
                  ),
                  if (!comingSoon && price != null && _hasStrike(price)) ...[
                    const SizedBox(width: 7),
                    Padding(
                      padding: const EdgeInsets.only(bottom: 2),
                      child: Text(
                        '₹${_money(price['strike_price'])}',
                        style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, decoration: TextDecoration.lineThrough),
                      ),
                    ),
                  ],
                ]),
              ])),
              SizedBox(
                width: 190,
                child: Pressable(
                  radius: 16,
                  onTap: (!comingSoon && priceActive && !processing) ? _checkout : () {},
                  child: Container(
                    height: 52,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      gradient: (!comingSoon && priceActive) ? LinearGradient(colors: [color, color.withOpacity(.8)]) : null,
                      color: (!comingSoon && priceActive) ? null : context.tokens.surfaceAlt,
                      borderRadius: BorderRadius.circular(16),
                    ),
                    child: processing
                        ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
                        : Text(
                            comingSoon ? 'Coming Soon' : price == null ? 'Select Duration' : 'Pay ₹${_money(price['price'])}',
                            style: GoogleFonts.poppins(fontSize: 15, fontWeight: FontWeight.w800, color: (!comingSoon && priceActive) ? Colors.white : context.tokens.textTertiary),
                          ),
                  ),
                ),
              ),
            ]),
          ),
        ),
      ),
    );
  }

  Widget _sectionTitle(String text) => Row(children: [Text(text, style: context.typo.titleMedium?.copyWith(fontWeight: FontWeight.w800))]);

  Widget _waitingOverlay() {
    return Container(
      color: Theme.of(context).scaffoldBackgroundColor.withOpacity(0.96),
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: SurfaceCard(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              IconBadge(Icons.payment_rounded, color: AppTheme.brand, size: 72, iconSize: 34),
              const SizedBox(height: 18),
              Text('Waiting for Payment', style: GoogleFonts.spaceGrotesk(fontSize: 22, fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              Text('Complete the payment in your browser. We will verify it automatically.', textAlign: TextAlign.center, style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary, height: 1.45)),
              const SizedBox(height: 18),
              FireButton(label: 'Check Now', icon: Icons.refresh_rounded, onPressed: () => _checkNow()),
              const SizedBox(height: 8),
              TextButton(onPressed: () { pollTimer?.cancel(); setState(() => waiting = false); }, child: const Text('Cancel waiting')),
            ]),
          ),
        ),
      ),
    );
  }

  Widget _hero() {
    final gradientColors = code == 'silver'
        ? [const Color(0xFF1E3A8A), const Color(0xFF2563EB)]
        : code == 'gold'
            ? [const Color(0xFF78350F), const Color(0xFFD97706)]
            : [const Color(0xFF451A03), const Color(0xFFB45309)];
    return Container(
      padding: const EdgeInsets.fromLTRB(22, 24, 22, 22),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: gradientColors, begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(26),
        boxShadow: [BoxShadow(color: color.withOpacity(.35), blurRadius: 24, offset: const Offset(0, 10), spreadRadius: -6)],
      ),
      child: Stack(children: [
        Positioned(right: -14, top: -20, child: Icon(Icons.workspace_premium_rounded, size: 120, color: Colors.white.withOpacity(.14))),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(
              width: 48, height: 48,
              decoration: BoxDecoration(color: Colors.white.withOpacity(.18), borderRadius: BorderRadius.circular(15)),
              child: Icon(icon, color: Colors.white, size: 24),
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text((tier['badge_text'] ?? tier['code'] ?? '').toString().toUpperCase(), style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w800, letterSpacing: 2, color: Colors.white.withOpacity(.85))),
              const SizedBox(height: 3),
              Text(tier['name'] ?? '', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900)),
            ])),
          ]),
          const SizedBox(height: 14),
          Text((tier['description'] ?? '').toString(), style: GoogleFonts.poppins(color: Colors.white.withOpacity(.85), fontSize: 13, height: 1.5)),
          if (widget.isCurrent) ...[
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
              decoration: BoxDecoration(color: Colors.white.withOpacity(.16), borderRadius: BorderRadius.circular(999), border: Border.all(color: Colors.white.withOpacity(.3))),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.check_circle_rounded, size: 15, color: Colors.white),
                const SizedBox(width: 6),
                Text('CURRENT PLAN', style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: 1)),
              ]),
            ),
          ],
        ]),
      ]),
    );
  }

  Widget _durationCard(Map<String, dynamic> p, Map<String, dynamic>? selected) {
    final active = p['is_active'] == true || p['is_active'].toString() == '1';
    final isSelected = selected != null && selected['id'].toString() == p['id'].toString();
    final strike = p['strike_price'];
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Pressable(
        radius: 16,
        onTap: (active && !comingSoon) ? () => setState(() => selectedPrice = p) : () {},
        child: Container(
          padding: const EdgeInsets.all(15),
          decoration: BoxDecoration(
            color: isSelected ? color.withOpacity(.08) : context.tokens.surface,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: isSelected ? color : context.tokens.border, width: isSelected ? 1.6 : 1),
          ),
          child: Row(children: [
            Container(
              width: 22, height: 22,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isSelected ? color : Colors.transparent,
                border: Border.all(color: isSelected ? color : context.tokens.borderStrong, width: 1.6),
              ),
              child: isSelected ? const Icon(Icons.check_rounded, size: 14, color: Colors.white) : null,
            ),
            const SizedBox(width: 13),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('${p['duration_months']} Month${p['duration_months'].toString() == '1' ? '' : 's'}', style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
              const SizedBox(height: 2),
              Text(active ? _ucfirst((p['billing_cycle'] ?? '').toString()) : 'Inactive', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11)),
            ])),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text('₹${_money(p['price'])}', style: GoogleFonts.spaceGrotesk(fontSize: 18, fontWeight: FontWeight.w900, color: active ? color : context.tokens.textTertiary)),
              if (strike != null && strike.toString() != '0') Text('₹${_money(strike)}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, decoration: TextDecoration.lineThrough, fontSize: 11)),
              if ((p['discount_text'] ?? '').toString().isNotEmpty) ...[
                const SizedBox(height: 2),
                Text(p['discount_text'].toString(), style: context.typo.labelSmall?.copyWith(color: AppTheme.success, fontWeight: FontWeight.w900)),
              ],
            ]),
          ]),
        ),
      ),
    );
  }

  Widget _cardFeatureRow(Map<String, dynamic> f) {
    final included = f['is_included'] == true || f['is_included'].toString() == '1';
    final tooltip = (f['tooltip'] ?? '').toString().trim();
    return Padding(
      padding: const EdgeInsets.only(bottom: 13),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(included ? Icons.check_circle_rounded : Icons.cancel_rounded, color: included ? AppTheme.success : context.tokens.textTertiary.withOpacity(.7), size: 20),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(f['label']?.toString() ?? '', style: context.typo.titleSmall?.copyWith(fontSize: 13.5, fontWeight: FontWeight.w600, color: included ? context.tokens.text : context.tokens.textTertiary)),
          if (tooltip.isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(tooltip, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5)),
          ],
        ])),
      ]),
    );
  }

  List<Map<String, dynamic>> _cardFeatures() {
    return ((tier['card_features'] as List?) ?? []).whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList();
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

  String _ucfirst(String s) => s.isEmpty ? '' : s[0].toUpperCase() + s.substring(1);

  bool _hasStrike(Map<String, dynamic> p) {
    final s = p['strike_price'];
    if (s == null) return false;
    final n = num.tryParse(s.toString());
    return n != null && n > 0;
  }

  String get code => (tier['code'] ?? 'bronze').toString().toLowerCase();
  IconData get icon => code == 'gold' ? Icons.workspace_premium_rounded : code == 'silver' ? Icons.auto_awesome_rounded : Icons.shield_rounded;
}
