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
    final features = ((tier['features'] as List?) ?? []).where((f) => f is Map).map((f) => Map<String, dynamic>.from(f as Map)).toList();
    final price = selectedPrice;
    final priceActive = price != null && (price['is_active'] == true || price['is_active'].toString() == '1');

    return Scaffold(
      appBar: AppBar(title: const Text('Plan Details')),
      body: Stack(children: [
        ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 120),
          children: [
          _hero(),
          const SizedBox(height: 18),
          const SectionHeader('Choose Duration'),
          const SizedBox(height: 12),
          if (prices.isEmpty)
            SurfaceCard(child: Text('No pricing options configured yet.', style: context.typo.bodyMedium))
          else
            Wrap(spacing: 10, runSpacing: 10, children: prices.map((p) {
              final active = p['is_active'] == true || p['is_active'].toString() == '1';
              final selected = selectedPrice != null && selectedPrice!['id'].toString() == p['id'].toString();
              return ChoiceChip(
                selected: selected,
                onSelected: active && !comingSoon ? (_) => setState(() => selectedPrice = p) : null,
                label: Text('${p['duration_months']} Month${p['duration_months'].toString() == '1' ? '' : 's'} • ₹${_money(p['price'])}'),
                avatar: active && !comingSoon ? Icon(Icons.calendar_month_rounded, size: 16, color: selected ? Colors.white : color) : const Icon(Icons.lock_clock_rounded, size: 16),
                selectedColor: color,
                backgroundColor: context.tokens.surfaceAlt,
                labelStyle: context.typo.bodySmall?.copyWith(fontWeight: FontWeight.w800, color: selected ? Colors.white : (active ? color : context.tokens.textTertiary)),
                side: BorderSide(color: selected ? color : context.tokens.border),
              );
            }).toList()),
          const SizedBox(height: 22),
          const SectionHeader("What's included"),
          const SizedBox(height: 12),
          SurfaceCard(child: Column(children: features.map((f) => _featureRow(f)).toList())),
          const SizedBox(height: 18),
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
      bottomNavigationBar: waiting ? null : SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: FireButton(
            label: comingSoon ? 'Coming Soon' : price == null ? 'Select Duration' : processing ? 'Creating secure link...' : 'Pay • ₹${_money(price['price'])}',
            icon: comingSoon ? Icons.lock_clock_rounded : Icons.workspace_premium_rounded,
            loading: processing,
            onPressed: (!comingSoon && priceActive && !processing) ? _checkout : null,
          ),
        ),
      ),
    );
  }

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
    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(26), boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.25), blurRadius: 24, offset: const Offset(0, 10), spreadRadius: -6)]),
      child: Stack(children: [
        Positioned(right: -10, top: -20, child: Icon(Icons.workspace_premium_rounded, size: 110, color: color.withOpacity(0.20))),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text((tier['badge_text'] ?? tier['code'] ?? '').toString().toUpperCase(), style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w800, letterSpacing: 2, color: AppTheme.brandAmber)),
          const SizedBox(height: 8),
          Text(tier['name'] ?? '', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 31, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          Text((tier['description'] ?? '').toString(), style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.72), fontSize: 13.5, height: 1.5)),
          if (widget.isCurrent) ...[
            const SizedBox(height: 14),
            StatusBadge('CURRENT PLAN', color: color, icon: Icons.check_circle_rounded),
          ],
        ]),
      ]),
    );
  }

  Widget _featureRow(Map<String, dynamic> f) {
    final text = _featureText(f);
    final enabled = !text.toLowerCase().contains('no') && !text.toLowerCase().contains('0 ');
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(enabled ? Icons.check_circle_rounded : Icons.cancel_rounded, color: enabled ? AppTheme.success : context.tokens.textTertiary, size: 20),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(f['label']?.toString() ?? '', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
          const SizedBox(height: 2),
          Text(text, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
        ])),
      ]),
    );
  }

  String _featureText(Map<String, dynamic> f) {
    final type = f['value_type']?.toString();
    if (type == 'bool') {
      final enabled = f['value'] == true || f['value'].toString() == '1' || f['raw_value'].toString() == '1';
      return enabled ? 'Available' : 'Not available';
    }
    final value = (f['value'] ?? f['raw_value'] ?? '').toString();
    if (value == 'coming_soon') return 'Coming soon';
    return value.isEmpty ? '-' : value;
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
