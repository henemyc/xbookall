import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class WebLoginScanScreen extends ConsumerStatefulWidget {
  const WebLoginScanScreen({super.key});

  @override
  ConsumerState<WebLoginScanScreen> createState() => _WebLoginScanScreenState();
}

class _WebLoginScanScreenState extends ConsumerState<WebLoginScanScreen> with WidgetsBindingObserver {
  final MobileScannerController _controller = MobileScannerController(
    facing: CameraFacing.back,
    autoStart: true,
    detectionSpeed: DetectionSpeed.noDuplicates,
    detectionTimeoutMs: 1200,
  );

  bool processing = false;
  bool success = false;
  String? message;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) return;
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      _controller.stop();
    } else if (state == AppLifecycleState.resumed && !processing && !success) {
      _controller.start();
    }
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (processing || success || capture.barcodes.isEmpty) return;
    final raw = capture.barcodes.first.rawValue?.trim();
    if (raw == null || raw.isEmpty) return;

    setState(() {
      processing = true;
      message = 'Approving web login...';
    });
    HapticFeedback.selectionClick();

    try {
      await _controller.stop();
      final res = await ref.read(apiClientProvider).approveWebLogin(token: raw);
      if (!mounted) return;
      setState(() {
        success = true;
        processing = false;
        message = res['message']?.toString() ?? 'Web login approved. Your browser will login automatically.';
      });
      HapticFeedback.heavyImpact();
      Toast.success(context, 'Web login approved');
    } catch (e) {
      if (!mounted) return;
      setState(() {
        processing = false;
        message = _friendlyError(e);
      });
      HapticFeedback.heavyImpact();
      Toast.error(context, message!);
      Future.delayed(const Duration(milliseconds: 900), () {
        if (mounted && !success) _controller.start();
      });
    }
  }

  String _friendlyError(dynamic e) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) {
        final msg = data['error'] ?? data['message'];
        if (msg != null) return msg.toString();
      }
    } catch (_) {}
    final text = e.toString().toLowerCase();
    if (text.contains('connection') || text.contains('socket')) return 'No internet connection.';
    return 'Invalid or expired web login QR.';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(children: [
        if (!success)
          MobileScanner(controller: _controller, onDetect: _onDetect)
        else
          Container(color: const Color(0xFF101827)),
        const Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: RadialGradient(radius: 1.1, colors: [Colors.transparent, Color(0xD9000000)]),
            ),
          ),
        ),
        SafeArea(
          child: Column(children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(children: [
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded, color: Colors.white),
                  style: IconButton.styleFrom(backgroundColor: Colors.white.withOpacity(0.14)),
                ),
                const SizedBox(width: 10),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Web Login', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
                  Text('Scan QR from web.gymxbook.com', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.62), fontSize: 12)),
                ])),
              ]),
            ),
            const Spacer(),
            if (!success) _scanFrame() else _successCard(),
            const Spacer(),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 0, 20, 34),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.10),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: Colors.white.withOpacity(0.16)),
                ),
                child: Row(children: [
                  if (processing) SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.brand))
                  else Icon(success ? Icons.check_circle_rounded : Icons.qr_code_scanner_rounded, color: success ? AppTheme.success : AppTheme.brand, size: 20),
                  const SizedBox(width: 10),
                  Expanded(child: Text(message ?? 'Open web.gymxbook.com → QR Login, then scan the QR.', style: GoogleFonts.poppins(color: Colors.white, fontSize: 12.5, height: 1.35))),
                ]),
              ),
            ),
          ]),
        ),
      ]),
    );
  }

  Widget _scanFrame() {
    return SizedBox(
      width: 270,
      height: 270,
      child: Stack(children: [
        ...[Alignment.topLeft, Alignment.topRight, Alignment.bottomLeft, Alignment.bottomRight]
            .map((a) => Align(alignment: a, child: _corner(a))),
        if (processing)
          Center(child: SizedBox(width: 42, height: 42, child: CircularProgressIndicator(color: AppTheme.brand))),
      ]),
    );
  }

  Widget _corner(Alignment a) {
    final top = a.y < 0;
    final left = a.x < 0;
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(border: Border(
        top: top ? BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
        bottom: !top ? BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
        left: left ? BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
        right: !left ? BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
      )),
    );
  }

  Widget _successCard() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 26),
      padding: const EdgeInsets.all(26),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(28)),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        IconBadge(Icons.verified_rounded, color: AppTheme.success, size: 74, iconSize: 38),
        const SizedBox(height: 16),
        Text('Approved', style: GoogleFonts.spaceGrotesk(fontSize: 25, fontWeight: FontWeight.w800)),
        const SizedBox(height: 8),
        Text('Your browser will login automatically. You can close this screen.', textAlign: TextAlign.center, style: context.typo.bodyMedium?.copyWith(color: context.tokens.textSecondary)),
        const SizedBox(height: 20),
        FireButton(label: 'Done', icon: Icons.check_rounded, onPressed: () => Navigator.pop(context)),
      ]),
    );
  }
}
