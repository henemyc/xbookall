import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/providers/nav_provider.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class MemberScanScreen extends ConsumerStatefulWidget {
  const MemberScanScreen({super.key});

  @override
  ConsumerState<MemberScanScreen> createState() => _MemberScanScreenState();
}

class _MemberScanScreenState extends ConsumerState<MemberScanScreen>
    with SingleTickerProviderStateMixin, WidgetsBindingObserver {
  final MobileScannerController _controller = MobileScannerController(
    facing: CameraFacing.back,
    autoStart: false,
    detectionSpeed: DetectionSpeed.noDuplicates,
    detectionTimeoutMs: 1400,
  );

  late final AnimationController _scanLine;
  ProviderSubscription<int>? _navSub;

  bool _starting = false;
  bool _cameraRunning = false;
  bool _processing = false;
  bool _torchOn = false;
  String? _error;
  String? _lastValue;
  DateTime? _lastScanAt;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _scanLine = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1700),
    )..repeat(reverse: true);

    _navSub = ref.listenManual<int>(navIndexProvider, (previous, next) {
      _syncCameraWithNavigation(next);
    });

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _syncCameraWithNavigation(ref.read(navIndexProvider));
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _navSub?.close();
    _scanLine.dispose();
    _controller.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) return;

    if (state == AppLifecycleState.resumed) {
      _syncCameraWithNavigation(ref.read(navIndexProvider));
    } else if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.paused ||
        state == AppLifecycleState.detached) {
      _stopCamera();
    }
  }

  void _syncCameraWithNavigation(int navIndex) {
    if (!mounted) return;

    // Rewritten clean flow: in member shell, Scan QR tab is index 2.
    if (navIndex == 2) {
      _startCamera();
    } else {
      _stopCamera();
    }
  }

  Future<void> _startCamera() async {
    if (!mounted || _cameraRunning || _starting || _processing) return;

    setState(() {
      _starting = true;
      _error = null;
      _lastValue = null;
      _lastScanAt = null;
    });

    try {
      await _controller.start();
      if (!mounted) return;
      _scanLine.repeat(reverse: true);
      setState(() {
        _cameraRunning = true;
        _starting = false;
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _cameraRunning = false;
        _starting = false;
        _error = _friendlyCameraError(e);
      });
    }
  }

  Future<void> _stopCamera() async {
    if (!_cameraRunning && !_starting) return;

    try {
      await _controller.stop();
    } catch (_) {}

    if (!mounted) return;
    _scanLine.stop();
    setState(() {
      _cameraRunning = false;
      _starting = false;
      _torchOn = false;
    });
  }

  Future<void> _restartCamera() async {
    _lastValue = null;
    _lastScanAt = null;
    try {
      await _controller.stop();
    } catch (_) {}
    if (!mounted) return;
    setState(() {
      _cameraRunning = false;
      _processing = false;
      _error = null;
    });
    await Future.delayed(const Duration(milliseconds: 250));
    await _startCamera();
  }

  Future<void> _toggleTorch() async {
    if (!_cameraRunning || _processing) return;
    try {
      await _controller.toggleTorch();
      if (mounted) setState(() => _torchOn = !_torchOn);
    } catch (_) {
      if (mounted) Toast.error(context, 'Torch not available');
    }
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (!mounted || _processing || !_cameraRunning) return;
    if (ref.read(navIndexProvider) != 2) return;
    if (capture.barcodes.isEmpty) return;

    final value = capture.barcodes.first.rawValue?.trim();
    if (value == null || value.isEmpty) return;

    // Debounce duplicate frames from the camera.
    final now = DateTime.now();
    if (_lastValue == value &&
        _lastScanAt != null &&
        now.difference(_lastScanAt!).inSeconds < 4) {
      return;
    }

    _lastValue = value;
    _lastScanAt = now;

    HapticFeedback.selectionClick();
    setState(() {
      _processing = true;
      _error = null;
    });

    // Stop camera while API request is running. This prevents duplicate check-ins.
    try {
      await _controller.stop();
    } catch (_) {}
    if (mounted) setState(() => _cameraRunning = false);

    try {
      final api = ref.read(apiClientProvider);
      final res = await api.scanAttendance(
        qrToken: value,
        type: 'checkin',
        notes: 'QR Scan - Member App',
      );

      final type = (res['type'] ?? '').toString();
      final message = (res['message'] ??
              (type == 'checkout'
                  ? 'Checked out successfully'
                  : 'Checked in successfully'))
          .toString();

      if (!mounted) return;
      setState(() => _processing = false);
      HapticFeedback.heavyImpact();
      _showResultDialog(
        success: true,
        title: type == 'checkout' ? 'Checked Out' : 'Checked In',
        message: message,
      );
    } catch (e) {
      if (!mounted) return;
      final msg = _friendlyApiError(e);
      setState(() {
        _processing = false;
        _error = msg;
      });
      HapticFeedback.heavyImpact();
      _showResultDialog(success: false, title: 'Scan Failed', message: msg);
    }
  }

  String _friendlyCameraError(dynamic e) {
    final text = e.toString().toLowerCase();
    if (text.contains('permission')) {
      return 'Camera permission is required. Please allow camera permission and try again.';
    }
    if (text.contains('in use')) {
      return 'Camera is already in use. Close other camera apps and try again.';
    }
    return 'Could not start camera. Please try again.';
  }

  String _friendlyApiError(dynamic e) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) {
        final msg = data['error'] ?? data['message'];
        if (msg != null && msg.toString().trim().isNotEmpty) return msg.toString();
      }
      final code = (e as dynamic).response?.statusCode;
      if (code == 401) return 'Session expired. Please login again.';
      if (code == 403) return 'Attendance not allowed. Please contact your gym.';
      if (code == 404) return 'QR scan API not found. Please update backend routes.';
    } catch (_) {}

    final text = e.toString().toLowerCase();
    if (text.contains('connection') || text.contains('socket') || text.contains('network')) {
      return 'No internet connection. Please try again.';
    }
    return 'Could not mark attendance. Please try again.';
  }

  void _showResultDialog({required bool success, required String title, required String message}) {
    if (!mounted) return;

    final color = success ? AppTheme.success : AppTheme.danger;
    final icon = success ? Icons.check_circle_rounded : Icons.error_outline_rounded;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => Dialog(
        backgroundColor: Colors.transparent,
        elevation: 0,
        child: Container(
          padding: const EdgeInsets.all(26),
          decoration: BoxDecoration(
            color: const Color(0xFF171923),
            borderRadius: BorderRadius.circular(28),
            border: Border.all(color: color.withOpacity(0.35)),
            boxShadow: [
              BoxShadow(color: Colors.black.withOpacity(0.35), blurRadius: 24, offset: const Offset(0, 12)),
            ],
          ),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 76,
              height: 76,
              decoration: BoxDecoration(color: color.withOpacity(0.16), shape: BoxShape.circle),
              child: Icon(icon, color: color, size: 50),
            ),
            const SizedBox(height: 18),
            Text(
              title,
              textAlign: TextAlign.center,
              style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.72), fontSize: 13, height: 1.45),
            ),
            const SizedBox(height: 24),
            Row(children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    Navigator.pop(ctx);
                    _restartCamera();
                  },
                  style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.white,
                    side: BorderSide(color: Colors.white.withOpacity(0.25)),
                    padding: const EdgeInsets.symmetric(vertical: 13),
                  ),
                  child: Text(success ? 'Scan Again' : 'Try Again'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.pop(ctx);
                    ref.read(navIndexProvider.notifier).state = 0;
                  },
                  style: ElevatedButton.styleFrom(backgroundColor: color, padding: const EdgeInsets.symmetric(vertical: 13)),
                  child: const Text('Done', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                ),
              ),
            ]),
          ]),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(children: [
        Positioned.fill(
          child: MobileScanner(
            controller: _controller,
            onDetect: _onDetect,
          ),
        ),
        const Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: RadialGradient(
                radius: 1.05,
                colors: [Colors.transparent, Color(0xCC000000)],
              ),
            ),
          ),
        ),
        SafeArea(
          child: Column(children: [
            _topBar(),
            const Spacer(),
            _scanFrame(),
            const Spacer(),
            _bottomStatus(),
          ]),
        ),
      ]),
    );
  }

  Widget _topBar() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
      child: Row(children: [
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Scan Gym QR', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 21, fontWeight: FontWeight.w800)),
          const SizedBox(height: 2),
          Text('Check-in / check-out attendance', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.62), fontSize: 12)),
        ]),
        const Spacer(),
        _roundButton(_torchOn ? Icons.flash_on_rounded : Icons.flash_off_rounded, _toggleTorch),
        const SizedBox(width: 8),
        _roundButton(Icons.refresh_rounded, _restartCamera),
      ]),
    );
  }

  Widget _roundButton(IconData icon, VoidCallback onTap) {
    return Material(
      color: Colors.white.withOpacity(0.14),
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Icon(icon, color: Colors.white, size: 22),
        ),
      ),
    );
  }

  Widget _scanFrame() {
    return SizedBox(
      width: 272,
      height: 272,
      child: Stack(children: [
        ...[Alignment.topLeft, Alignment.topRight, Alignment.bottomLeft, Alignment.bottomRight]
            .map((a) => Align(alignment: a, child: _corner(a))),
        if (_cameraRunning && !_processing)
          AnimatedBuilder(
            animation: _scanLine,
            builder: (_, __) => Align(
              alignment: Alignment(0, -1 + (_scanLine.value * 2)),
              child: Container(
                width: 238,
                height: 3,
                decoration: BoxDecoration(
                  gradient: LinearGradient(colors: [Colors.transparent, AppTheme.brand, Colors.transparent]),
                  boxShadow: [BoxShadow(color: AppTheme.brand.withOpacity(0.9), blurRadius: 12)],
                ),
              ),
            ),
          ),
        if (_processing)
          Center(
            child: Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: Colors.black.withOpacity(0.58), borderRadius: BorderRadius.circular(18)),
              child: SizedBox(width: 34, height: 34, child: CircularProgressIndicator(color: AppTheme.brand, strokeWidth: 3)),
            ),
          ),
      ]),
    );
  }

  Widget _corner(Alignment a) {
    final top = a.y < 0;
    final left = a.x < 0;
    return Container(
      width: 38,
      height: 38,
      decoration: BoxDecoration(
        border: Border(
          top: top ? BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
          bottom: !top ? BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
          left: left ? BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
          right: !left ? BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
        ),
      ),
    );
  }

  Widget _bottomStatus() {
    String text;
    IconData icon;
    Color color;

    if (_processing) {
      text = 'Processing attendance...';
      icon = Icons.sync_rounded;
      color = AppTheme.brand;
    } else if (_starting) {
      text = 'Starting camera...';
      icon = Icons.camera_alt_rounded;
      color = AppTheme.brand;
    } else if (_error != null) {
      text = _error!;
      icon = Icons.error_outline_rounded;
      color = AppTheme.danger;
    } else if (_cameraRunning) {
      text = 'Point camera at the gym QR code.';
      icon = Icons.qr_code_scanner_rounded;
      color = AppTheme.success;
    } else {
      text = 'Camera is paused. Tap Start Camera.';
      icon = Icons.videocam_off_rounded;
      color = AppTheme.warning;
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 36),
      child: Column(children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white.withOpacity(0.10),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: color.withOpacity(0.35)),
          ),
          child: Row(children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 10),
            Expanded(child: Text(text, style: GoogleFonts.poppins(color: Colors.white, fontSize: 12.5, height: 1.35))),
          ]),
        ),
        if (!_cameraRunning && !_starting && !_processing) ...[
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _startCamera,
              icon: const Icon(Icons.camera_alt_rounded, color: Colors.white),
              label: const Text('Start Camera', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
              style: ElevatedButton.styleFrom(backgroundColor: AppTheme.brand, padding: const EdgeInsets.symmetric(vertical: 14)),
            ),
          ),
        ],
      ]),
    );
  }
}
