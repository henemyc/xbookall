import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hive_flutter/hive_flutter.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:gymxbook/core/theme/app_theme.dart';
import 'package:gymxbook/core/theme/theme_provider.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/screens/login_screen.dart';
import 'package:gymxbook/features/dashboard/screens/dashboard_screen.dart';
import 'package:gymxbook/features/members/screens/members_list_screen.dart';
import 'package:gymxbook/features/attendance/screens/attendance_screen.dart';
import 'package:gymxbook/features/reports/screens/reports_screen.dart';
import 'package:gymxbook/features/reports/screens/report_bug_screen.dart';
import 'package:gymxbook/features/transactions/screens/transactions_screen.dart';
import 'package:gymxbook/features/invoices/screens/invoices_list_screen.dart';
import 'package:gymxbook/features/qr/screens/admin_qr_screen.dart';
import 'package:gymxbook/features/qr/screens/member_scan_screen.dart';
import 'package:gymxbook/features/notices/screens/notices_list_screen.dart';
import 'package:gymxbook/features/notifications/screens/notifications_screen.dart';
import 'package:gymxbook/features/notifications/providers/notifications_provider.dart';
import 'package:gymxbook/features/settings/screens/settings_screen.dart';
import 'package:gymxbook/features/subscription/screens/subscription_screen.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/features/member_dashboard/screens/member_dashboard_screen.dart';
import 'package:gymxbook/features/member_attendance/screens/member_attendance_screen.dart';
import 'package:gymxbook/features/member_workout/screens/member_workout_screen.dart';
import 'package:gymxbook/features/member_bmi/screens/member_bmi_screen.dart';
import 'package:gymxbook/features/trainers/screens/trainers_list_screen.dart';
import 'package:gymxbook/features/trainer_dashboard/screens/trainer_dashboard_screen.dart';
import 'package:gymxbook/features/trainer_members/screens/trainer_members_screen.dart';
import 'package:gymxbook/features/trainer_workouts/screens/trainer_workouts_screen.dart';
import 'package:gymxbook/features/trainer_classes/screens/trainer_classes_screen.dart';
import 'package:gymxbook/features/memberships/screens/memberships_screen.dart';
import 'package:gymxbook/features/classes/screens/classes_screen.dart';
import 'package:gymxbook/features/expenses/screens/expenses_screen.dart';
import 'package:gymxbook/features/products/screens/products_screen.dart';
import 'package:gymxbook/features/lockers/screens/lockers_screen.dart';
import 'package:gymxbook/features/events/screens/events_screen.dart';
import 'package:gymxbook/core/providers/nav_provider.dart';
import 'package:gymxbook/core/storage/secure_storage.dart';

// Timezone support (Asia/Kolkata) - full IST support
import 'package:timezone/data/latest.dart' as tz;
import 'package:timezone/timezone.dart' as tz;

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Hive.initFlutter();

  // Clear restored stale login data after app reinstall before auth check runs.
  await SecureStorage.initializeFreshInstallGuard();

  // Set timezone to Asia/Kolkata (IST) for the entire app
  try {
    tz.initializeTimeZones();
    tz.setLocalLocation(tz.getLocation('Asia/Kolkata'));
  } catch (e) {
    // Fallback: timezone package not ready or error (still safe)
    print('Timezone init warning: $e');
  }

  // Lock to portrait only — no auto-rotate.
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);
  // Edge-to-edge: draw behind the system bars and let SafeArea / insets manage
  // spacing, so nothing is permanently hidden behind the Android nav buttons.
  SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  runApp(const ProviderScope(child: GymXBookApp()));
}

class GymXBookApp extends ConsumerWidget {
  const GymXBookApp({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeModeProvider);
    return MaterialApp(
      title: 'GymXBook',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: mode,
      builder: (context, child) {
        // Keep system bars in sync with the resolved theme.
        final dark = Theme.of(context).brightness == Brightness.dark;
        SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: dark ? Brightness.light : Brightness.dark,
          statusBarBrightness: dark ? Brightness.dark : Brightness.light,
          // Transparent nav bar for true edge-to-edge; our floating nav + insets
          // keep content clear of the Back/Home/Recent buttons.
          systemNavigationBarColor: Colors.transparent,
          systemNavigationBarIconBrightness: dark ? Brightness.light : Brightness.dark,
          systemNavigationBarContrastEnforced: false,
        ));

        // === GLOBAL KEYBOARD DISMISS ===
        // Pointer-down runs BEFORE TextField handles the tap. This prevents the
        // old behavior where tapping inside the middle of a sentence focused the
        // field and then the global GestureDetector immediately unfocused it,
        // which looked like text selection instead of moving the cursor.
        return Listener(
          behavior: HitTestBehavior.translucent,
          onPointerDown: (event) {
            final focus = FocusManager.instance.primaryFocus;
            final focusContext = focus?.context;
            if (focusContext != null) {
              final renderObject = focusContext.findRenderObject();
              if (renderObject is RenderBox && renderObject.attached) {
                final topLeft = renderObject.localToGlobal(Offset.zero);
                final fieldRect = (topLeft & renderObject.size).inflate(36);
                if (fieldRect.contains(event.position)) return;
              }
            }
            focus?.unfocus();
          },
          child: child!,
        );
      },
      home: const PlatformMaintenanceGate(child: AuthWrapper()),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  PLATFORM MAINTENANCE GATE — Super Admin controlled
// ══════════════════════════════════════════════════════════════════
class PlatformMaintenanceGate extends ConsumerStatefulWidget {
  final Widget child;
  const PlatformMaintenanceGate({super.key, required this.child});

  @override
  ConsumerState<PlatformMaintenanceGate> createState() => _PlatformMaintenanceGateState();
}

class _PlatformMaintenanceGateState extends ConsumerState<PlatformMaintenanceGate> with WidgetsBindingObserver {
  Map<String, dynamic>? maintenance;
  Timer? _timer;
  int? secondsRemaining;
  bool liveNow = false;
  int _statusPollTick = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    Future.microtask(_loadStatus);
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _tick());
  }

  Future<void> _loadStatus() async {
    try {
      final res = await ref.read(apiClientProvider).getSystemStatus();
      final m = res['maintenance'];
      if (!mounted || m is! Map) return;
      final mapped = Map<String, dynamic>.from(m);
      setState(() {
        maintenance = mapped;
        secondsRemaining = _readRemainingSeconds(mapped);
        liveNow = false;
      });
    } catch (_) {
      // Never block the app if status check itself fails/offline.
    }
  }

  int? _readRemainingSeconds(Map<String, dynamic> mapped) {
    final raw = mapped['seconds_remaining'];
    if (raw is int) return raw;
    if (raw is num) return raw.ceil();
    final parsed = num.tryParse(raw?.toString() ?? '');
    if (parsed != null) return parsed.ceil();

    // Fallback: calculate locally from end_at if server sends a string date but
    // seconds_remaining is missing/decimal-formatted.
    final endAt = mapped['end_at']?.toString();
    if (endAt != null && endAt.isNotEmpty) {
      try {
        final end = DateTime.parse(endAt).toLocal();
        return end.difference(DateTime.now()).inSeconds.clamp(0, 9999999).toInt();
      } catch (_) {}
    }
    return null;
  }

  void _tick() {
    _statusPollTick++;
    // Poll periodically so a Super Admin scheduled/started maintenance is
    // picked up even while the user is already inside the app.
    if (_statusPollTick % 30 == 0) _loadStatus();

    final active = maintenance?['active'] == true;
    if (!active) return;

    if (secondsRemaining == null) return;
    if (secondsRemaining! > 0) {
      if (mounted) setState(() => secondsRemaining = secondsRemaining! - 1);
      return;
    }

    if (!liveNow && mounted) {
      setState(() => liveNow = true);
      Future.delayed(const Duration(seconds: 3), () {
        if (mounted) _loadStatus();
      });
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _loadStatus();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final active = maintenance?['active'] == true;
    if (!active) return widget.child;
    return _MaintenanceScreen(
      maintenance: maintenance ?? {},
      secondsRemaining: secondsRemaining,
      liveNow: liveNow,
      onRefresh: _loadStatus,
    );
  }
}

class _MaintenanceScreen extends StatelessWidget {
  final Map<String, dynamic> maintenance;
  final int? secondsRemaining;
  final bool liveNow;
  final Future<void> Function() onRefresh;

  const _MaintenanceScreen({required this.maintenance, required this.secondsRemaining, required this.liveNow, required this.onRefresh});

  String _two(int v) => v.toString().padLeft(2, '0');

  String _dateLabel(dynamic value) {
    if (value == null || value.toString().isEmpty) return 'Soon';
    try {
      final dt = DateTime.parse(value.toString()).toLocal();
      final hour = dt.hour % 12 == 0 ? 12 : dt.hour % 12;
      final ampm = dt.hour >= 12 ? 'PM' : 'AM';
      return '${_two(dt.day)}-${_two(dt.month)}-${dt.year} ${_two(hour)}:${_two(dt.minute)} $ampm';
    } catch (_) {
      return value.toString();
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = maintenance['title']?.toString() ?? 'GymXBook is under maintenance';
    final message = maintenance['message']?.toString() ?? 'We are upgrading GymXBook to serve you better. Please wait until maintenance is complete.';
    final total = ((secondsRemaining ?? 0).clamp(0, 9999999) as num).toInt();
    final h = total ~/ 3600;
    final m = (total % 3600) ~/ 60;
    final s = total % 60;

    return Scaffold(
      body: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [const Color(0xFFFFF7ED), const Color(0xFFF8FAFC), AppTheme.brand.withOpacity(0.12)],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(22),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    gradient: AppTheme.darkHeroGradient,
                    borderRadius: BorderRadius.circular(30),
                    boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.18), blurRadius: 30, offset: const Offset(0, 14))],
                  ),
                  child: Stack(children: [
                    Positioned(right: -24, top: -28, child: Icon(Icons.build_circle_rounded, size: 132, color: AppTheme.brand.withOpacity(0.14))),
                    Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Container(
                          width: 56,
                          height: 56,
                          decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(18)),
                          child: const Icon(Icons.fitness_center_rounded, color: Colors.white, size: 28),
                        ),
                        const SizedBox(width: 12),
                        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('GymXBook', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
                          Text('Platform maintenance', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.62), fontSize: 12)),
                        ])),
                      ]),
                      const SizedBox(height: 22),
                      Text(liveNow ? 'We are live now' : title, style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w900, height: 1.05, letterSpacing: -0.8)),
                      const SizedBox(height: 10),
                      Text(liveNow ? 'Maintenance is complete. Tap refresh to continue using GymXBook.' : message, style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.72), fontSize: 13.5, height: 1.55)),
                    ]),
                  ]),
                ),
                const SizedBox(height: 18),
                SurfaceCard(
                  radius: 28,
                  child: Column(children: [
                    Row(children: [
                      IconBadge(liveNow ? Icons.check_circle_rounded : Icons.timer_rounded, color: liveNow ? AppTheme.success : AppTheme.brand, size: 50, iconSize: 25),
                      const SizedBox(width: 12),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(liveNow ? 'Ready to refresh' : 'Estimated time remaining', style: context.typo.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                        Text('Expected back: ${_dateLabel(maintenance['end_at'])}', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                      ])),
                    ]),
                    const SizedBox(height: 20),
                    if (!liveNow) Row(children: [
                      Expanded(child: _timeBox(context, _two(h), 'Hours')),
                      const SizedBox(width: 10),
                      Expanded(child: _timeBox(context, _two(m), 'Minutes')),
                      const SizedBox(width: 10),
                      Expanded(child: _timeBox(context, _two(s), 'Seconds')),
                    ]) else SurfaceCard(
                      color: AppTheme.success.withOpacity(0.08),
                      border: Border.all(color: AppTheme.success.withOpacity(0.2)),
                      shadow: false,
                      child: Row(children: [
                        const Icon(Icons.check_circle_rounded, color: AppTheme.success),
                        const SizedBox(width: 10),
                        Expanded(child: Text('We are live now. Please refresh.', style: context.typo.titleSmall?.copyWith(color: AppTheme.success))),
                      ]),
                    ),
                    const SizedBox(height: 22),
                    FireButton(label: liveNow ? 'Refresh App' : 'Check Status', icon: Icons.refresh_rounded, onPressed: onRefresh),
                  ]),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }

  Widget _timeBox(BuildContext context, String value, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(18), border: Border.all(color: context.tokens.border)),
      child: Column(children: [
        Text(value, style: GoogleFonts.spaceGrotesk(fontSize: 28, fontWeight: FontWeight.w900, color: AppTheme.brand)),
        const SizedBox(height: 4),
        Text(label, style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary)),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  AUTH WRAPPER + ANIMATED SPLASH
// ══════════════════════════════════════════════════════════════════
class AuthWrapper extends ConsumerStatefulWidget {
  const AuthWrapper({super.key});
  @override
  ConsumerState<AuthWrapper> createState() => _AuthWrapperState();
}

class _AuthWrapperState extends ConsumerState<AuthWrapper> {
  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    if (auth.isLoading) return const _SplashScreen();
    if (!auth.isLoggedIn) return const LoginScreen();
    return const MainShell();
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            TweenAnimationBuilder<double>(
              tween: Tween(begin: 0.8, end: 1),
              duration: const Duration(milliseconds: 600),
              curve: Curves.easeOutCubic,
              builder: (_, v, child) => Transform.scale(scale: v, child: child),
              child: Image.asset(
                'assets/images/gymxbook_logo_icon.png',
                width: 120,
                height: 120,
              ),
            ),
            const SizedBox(height: 20),
            Text('GymXBook', style: GoogleFonts.spaceGrotesk(color: const Color(0xFF1A1210), fontSize: 28, fontWeight: FontWeight.w700, letterSpacing: -0.5)),
            const SizedBox(height: 32),
            const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.4, color: AppTheme.brand)),
          ],
        ),
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  MAIN SHELL
// ══════════════════════════════════════════════════════════════════
class MainShell extends ConsumerStatefulWidget {
  const MainShell({super.key});
  @override
  ConsumerState<MainShell> createState() => _MainShellState();
}

class _MainShellState extends ConsumerState<MainShell> with WidgetsBindingObserver {
  int _index = 0;
  DateTime? _lastBackPress;
  final PageController _pageController = PageController();
  int _navCount = 5; // number of swipeable primary tabs
  bool _isAnimating = false; // suppress intermediate onPageChanged haptics
  DateTime? _lastPaused;
  DateTime? _lastSubWarning;
  DateTime? _lastExpiredOverlay;
  bool _forceUpdateShown = false;
  static const String currentAppVersion = '1.1.1';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    Future.microtask(() {
      ref.listenManual(navIndexProvider, (prev, next) {
        if (next != _index && mounted) _goTo(next, fromNav: false);
      });
      _checkSubscription();
      _checkForceUpdate();

      // Eagerly load notifications so the app bar badge shows the count
      // immediately (without requiring the user to open the notifications page first).
      if (ref.read(authProvider).isLoggedIn) {
        ref.read(notificationsProvider.notifier).load();
      }
    });
  }

  void _checkSubscription() {
    final auth = ref.read(authProvider);
    final days = auth.subscriptionDaysLeft;
    final expired = auth.subscriptionExpired;
    if (days == null) return;

    // Skip warnings for weekly/free trial plans
    final interval = (auth.subscription?['interval'] ?? '').toString().toLowerCase();
    if (interval.contains('week')) return;

    if (expired || days < 0) {
      _showExpiredOverlay();
    } else if (days <= 7) {
      _showExpiryWarning(days);
    }
  }

  Future<void> _checkForceUpdate() async {
    if (_forceUpdateShown) return;
    try {
      final data = await ref.read(apiClientProvider).getAppUpdateInfo(currentVersion: currentAppVersion);
      final updateAvailable = data['update_available'] == true;
      final forceUpdate = data['force_update'] == true;
      if (!mounted || !updateAvailable || !forceUpdate) return;

      _forceUpdateShown = true;
      Future.delayed(const Duration(milliseconds: 600), () {
        if (mounted) _showForceUpdateSheet(data);
      });
    } catch (_) {
      // Never block the app if update check itself fails.
    }
  }

  void _showForceUpdateSheet(Map<String, dynamic> data) {
    final latest = data['latest_version']?.toString() ?? '';
    final message = data['message']?.toString() ?? 'A new version of GymXBook is available.';
    final url = data['update_url']?.toString() ?? '';

    showModalBottomSheet(
      context: context,
      isDismissible: false,
      enableDrag: false,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => WillPopScope(
        onWillPop: () async => false,
        child: Container(
          padding: const EdgeInsets.fromLTRB(22, 14, 22, 26),
          decoration: BoxDecoration(
            color: context.tokens.surface,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(30)),
            border: Border(top: BorderSide(color: context.tokens.border)),
            boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.18), blurRadius: 24, offset: const Offset(0, -8))],
          ),
          child: SafeArea(
            top: false,
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Container(width: 48, height: 5, decoration: BoxDecoration(color: context.tokens.border, borderRadius: BorderRadius.circular(10))),
              const SizedBox(height: 18),
              IconBadge(Icons.system_update_alt_rounded, color: AppTheme.warning, size: 72, iconSize: 36),
              const SizedBox(height: 16),
              Text('Update Required', style: GoogleFonts.spaceGrotesk(fontSize: 24, fontWeight: FontWeight.w800)),
              const SizedBox(height: 8),
              Text('Current v$currentAppVersion • Latest v$latest', textAlign: TextAlign.center, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
              const SizedBox(height: 10),
              Text(message, textAlign: TextAlign.center, style: context.typo.bodyMedium?.copyWith(height: 1.45)),
              const SizedBox(height: 22),
              FireButton(
                label: 'Update Now',
                icon: Icons.open_in_new_rounded,
                onPressed: url.isEmpty ? null : () async {
                  final uri = Uri.parse(url);
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                },
              ),
              const SizedBox(height: 8),
              Text('This update is required to continue using the app.', textAlign: TextAlign.center, style: context.typo.bodySmall?.copyWith(color: AppTheme.danger, fontWeight: FontWeight.w600)),
            ]),
          ),
        ),
      ),
    );
  }

  void _showExpiryWarning(int days) {
    final now = DateTime.now();
    final todayKey = '${now.year}-${now.month}-${now.day}';
    // Max 3 times per day — check shared preferences or simple in-memory
    if (_lastSubWarning != null) {
      final lastKey = '${_lastSubWarning!.year}-${_lastSubWarning!.month}-${_lastSubWarning!.day}';
      if (lastKey == todayKey && now.difference(_lastSubWarning!).inMinutes < 300) return; // 5hr gap = ~3 times/day
    }
    _lastSubWarning = now;
    Future.delayed(const Duration(seconds: 2), () {
      if (!mounted) return;
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
          title: Row(children: [
            const Icon(Icons.warning_amber_rounded, color: AppTheme.warning),
            const SizedBox(width: 8),
            const Text('Subscription Expiring'),
          ]),
          content: Text('Your plan expires in $days ${days == 1 ? 'day' : 'days'}. Top-up now to avoid service interruption.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Later')),
            ElevatedButton(
              onPressed: () { Navigator.pop(ctx); Navigator.push(context, MaterialPageRoute(builder: (_) => SubscriptionScreen())); },
              child: const Text('Top-up Now'),
            ),
          ],
        ),
      );
    });
  }

  void _showExpiredOverlay() {
    final now = DateTime.now();
    if (_lastExpiredOverlay != null && now.difference(_lastExpiredOverlay!).inSeconds < 15) return;
    _lastExpiredOverlay = now;
    // Schedule next check
    Future.delayed(const Duration(seconds: 15), () {
      if (mounted) _checkSubscription();
    });
    Future.delayed(const Duration(milliseconds: 500), () {
      if (!mounted) return;
      _showExpiredDialog();
    });
  }

  void _showExpiredDialog() {
    final auth = ref.read(authProvider);
    final days = auth.subscriptionDaysLeft ?? 0;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => WillPopScope(
        onWillPop: () async => false,
        child: Dialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Container(
                width: 64, height: 64,
                decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.12), shape: BoxShape.circle),
                child: const Icon(Icons.error_outline_rounded, color: AppTheme.danger, size: 36),
              ),
              const SizedBox(height: 16),
              Text('Subscription Expired', style: GoogleFonts.spaceGrotesk(fontSize: 22, fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              Text(
                'Your plan expired ${days.abs()} ${days.abs() == 1 ? 'day' : 'days'} ago. Top-up to continue using GymXBook.',
                textAlign: TextAlign.center,
                style: context.typo.bodyMedium?.copyWith(color: context.tokens.textSecondary),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: FireButton(
                  label: 'Renew Subscription',
                  icon: Icons.workspace_premium_rounded,
                  onPressed: () {
                    Navigator.pop(ctx);
                    Navigator.push(context, MaterialPageRoute(builder: (_) => SubscriptionScreen()));
                  },
                ),
              ),
              const SizedBox(height: 8),
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: Text('Later', style: TextStyle(color: context.tokens.textTertiary)),
              ),
            ]),
          ),
        ),
      ),
    );
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _pageController.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState lifecycleState) {
    if (lifecycleState == AppLifecycleState.paused) {
      _lastPaused = DateTime.now();
    }
    if (lifecycleState == AppLifecycleState.resumed) {
      // Only silently refresh if app was backgrounded for 5+ minutes
      // Short switches (phone call, notification, 1-second home press) are ignored
      if (_lastPaused != null && DateTime.now().difference(_lastPaused!).inMinutes >= 5) {
        ref.read(authProvider.notifier).silentRefresh();
      }
      _lastPaused = null;
    }
  }

  /// Navigate to [index]. Primary tabs (< _navCount) animate the PageView;
  /// deeper drawer pages jump instantly.
  void _goTo(int index, {bool fromNav = true}) {
    final wasDeep = _index >= _navCount;
    setState(() => _index = index);
    if (fromNav) ref.read(navIndexProvider.notifier).state = index;
    if (index < _navCount) {
      if (_pageController.hasClients && !wasDeep) {
        // Suppress intermediate onPageChanged haptics during programmatic animation
        _isAnimating = true;
        _pageController.animateToPage(index, duration: const Duration(milliseconds: 200), curve: Curves.easeOutCubic).then((_) {
          _isAnimating = false;
        });
      } else {
        // Coming back from a deep page: PageView is (re)mounting this frame.
        _isAnimating = true;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (_pageController.hasClients) _pageController.jumpToPage(index);
          _isAnimating = false;
        });
      }
    }
  }

  final _adminPages = const [
    DashboardScreen(),
    MembersListScreen(),
    AttendanceScreen(),
    ReportsScreen(),
    TransactionsScreen(),
    TrainersListScreen(),
    MembershipsScreen(),
    ClassesScreen(),
    ExpensesScreen(),
    ProductsScreen(),
    NoticesListScreen(),
    NotificationsScreen(),
    AdminQRScreen(),
    InvoicesListScreen(),
    SettingsScreen(),
    LockersScreen(),
    EventsScreen(),
  ];
  final _adminTitles = ['', 'Members', 'Attendance', 'Reports', 'History', 'Trainers', 'Plans', 'Classes', 'Expenses', 'Products', 'Notices', 'Notifications', 'Gym QR Code', 'Invoices', 'Settings', 'Lockers', 'Events'];

  bool _planFeatureEnabled(String key, {bool legacyDefault = true}) {
    final user = ref.read(authProvider).user;
    final tier = user?['current_tier'];
    final features = user?['plan_features'];
    if (features is Map && features.containsKey(key)) {
      final value = features[key];
      if (value is bool) return value;
      final text = value.toString().toLowerCase();
      return !['0', 'false', 'no', 'disabled', 'coming_soon'].contains(text);
    }
    final tierCode = tier is Map ? (tier['code'] ?? '').toString().toLowerCase() : '';
    if (tierCode == 'bronze' && (key == 'trainers_enabled' || key == 'lockers_enabled')) return false;
    return legacyDefault;
  }

  bool get _showTrainersForPlan => _planFeatureEnabled('trainers_enabled');
  bool get _showLockersForPlan => _planFeatureEnabled('lockers_enabled');

  bool _planCanIndex(int index) {
    if (index == 5 && !_showTrainersForPlan) return false;
    if (index == 15 && !_showLockersForPlan) return false;
    return true;
  }

  final _memberPages = const [
    MemberDashboardScreen(),
    MemberAttendanceScreen(),
    MemberScanScreen(),
    MemberWorkoutScreen(),
    SettingsScreen(),
    NoticesListScreen(),
    NotificationsScreen(),
    MemberBmiScreen(),
  ];
  final _memberTitles = ['Home', 'My Attendance', 'Scan QR', 'Workout', 'Settings', 'Notices', 'Notifications', 'BMI Calculator'];

  final _trainerPages = const [
    TrainerDashboardScreen(),
    TrainerMembersScreen(),
    TrainerWorkoutsScreen(),
    TrainerClassesScreen(),
    SettingsScreen(),
    MemberBmiScreen(),
  ];
  final _trainerTitles = ['Trainer Home', 'Assigned Members', 'Workouts', 'Classes', 'Settings', 'BMI Calculator'];

  bool _can(String permission) {
    final user = ref.read(authProvider).user;
    final type = (user?['type'] ?? 'admin').toString();
    if (type != 'staff') return true;
    final raw = user?['permissions'];
    if (raw is List) return raw.map((e) => e.toString()).contains(permission);
    return false;
  }

  bool _canAny(List<String> permissions) => permissions.any(_can);

  int _staffDefaultIndex() {
    final candidates = <MapEntry<int, String>>[
      const MapEntry(0, 'dashboard.view'),
      const MapEntry(1, 'members.view'),
      const MapEntry(2, 'attendance.view'),
      const MapEntry(3, 'reports.view'),
      const MapEntry(4, 'transactions.view'),
      const MapEntry(13, 'invoices.view'),
      const MapEntry(8, 'expenses.view'),
      const MapEntry(14, 'settings.view'),
    ];
    for (final entry in candidates) {
      if (_can(entry.value)) return entry.key;
    }
    return 0;
  }

  bool _staffCanIndex(int index) {
    const permissionByIndex = <int, String>{
      0: 'dashboard.view',
      1: 'members.view',
      2: 'attendance.view',
      3: 'reports.view',
      4: 'transactions.view',
      5: 'trainers.view',
      6: 'plans.view',
      7: 'classes.view',
      8: 'expenses.view',
      9: 'products.view',
      10: 'notices.view',
      11: 'dashboard.view',
      12: 'attendance.qr',
      13: 'invoices.view',
      14: 'settings.view',
      15: 'lockers.view',
      16: 'events.view',
    };
    if (!_planCanIndex(index)) return false;
    final permission = permissionByIndex[index];
    return permission == null || _can(permission);
  }

  Widget _staffGuard(String permission, Widget child) {
    if (_can(permission)) return child;
    return StaffAccessDeniedPage(permission: permission);
  }

  List<Widget> _staffAdminPages() {
    return [
      _staffGuard('dashboard.view', const DashboardScreen()),
      _staffGuard('members.view', const MembersListScreen()),
      _staffGuard('attendance.view', const AttendanceScreen()),
      _staffGuard('reports.view', const ReportsScreen()),
      _staffGuard('transactions.view', const TransactionsScreen()),
      _staffGuard('trainers.view', const TrainersListScreen()),
      _staffGuard('plans.view', const MembershipsScreen()),
      _staffGuard('classes.view', const ClassesScreen()),
      _staffGuard('expenses.view', const ExpensesScreen()),
      _staffGuard('products.view', const ProductsScreen()),
      _staffGuard('notices.view', const NoticesListScreen()),
      _staffGuard('dashboard.view', const NotificationsScreen()),
      _staffGuard('attendance.qr', const AdminQRScreen()),
      _staffGuard('invoices.view', const InvoicesListScreen()),
      _staffGuard('settings.view', const SettingsScreen()),
      _staffGuard('lockers.view', const LockersScreen()),
      _staffGuard('events.view', const EventsScreen()),
    ];
  }

  List<_NavSpec> _staffNavItems() {
    final items = <_NavSpec>[];
    if (_can('dashboard.view')) items.add(const _NavSpec(Icons.space_dashboard_outlined, Icons.space_dashboard_rounded, 'Home', 0));
    if (_can('members.view')) items.add(const _NavSpec(Icons.people_outline_rounded, Icons.people_rounded, 'Members', 1));
    if (_can('attendance.view')) items.add(const _NavSpec(Icons.qr_code_scanner_rounded, Icons.qr_code_scanner_rounded, 'Check In', 2));
    if (_can('reports.view')) items.add(const _NavSpec(Icons.bar_chart_rounded, Icons.bar_chart_rounded, 'Reports', 3));
    if (_can('transactions.view')) items.add(const _NavSpec(Icons.swap_horiz_rounded, Icons.swap_horiz_rounded, 'History', 4));
    if (items.length < 5 && _can('invoices.view')) items.add(const _NavSpec(Icons.receipt_long_outlined, Icons.receipt_long_rounded, 'Invoices', 13));
    if (items.length < 5 && _can('expenses.view')) items.add(const _NavSpec(Icons.account_balance_wallet_outlined, Icons.account_balance_wallet_rounded, 'Expenses', 8));
    if (items.length < 5 && _can('settings.view')) items.add(const _NavSpec(Icons.settings_outlined, Icons.settings_rounded, 'Settings', 14));
    if (items.isEmpty) items.add(const _NavSpec(Icons.lock_outline_rounded, Icons.lock_rounded, 'No Access', 0));
    return items.take(5).toList();
  }

  List<Widget> _staffDrawerItems() {
    return [
      _label('STAFF AREA'),
      if (_can('dashboard.view')) _item(Icons.space_dashboard_rounded, 'Dashboard', 0, AppTheme.brand),
      if (_can('members.view')) _item(Icons.people_rounded, 'Members', 1, AppTheme.info),
      if (_can('attendance.view')) _item(Icons.fact_check_rounded, 'Attendance', 2, AppTheme.warning),
      if (_can('trainers.view') && _showTrainersForPlan) _item(Icons.sports_martial_arts_rounded, 'Trainers', 5, AppTheme.success),
      if (_can('plans.view')) _item(Icons.card_membership_rounded, 'Plans', 6, const Color(0xFF8B5CF6)),
      if (_can('classes.view')) _item(Icons.self_improvement_rounded, 'Classes', 7, const Color(0xFFEC4899)),
      if (_can('reports.view')) _item(Icons.bar_chart_rounded, 'Reports', 3, const Color(0xFF06B6D4)),
      if (_can('attendance.qr')) _item(Icons.qr_code_2_rounded, 'Gym QR Code', 12, const Color(0xFF6366F1)),
      if (_canAny(['invoices.view', 'transactions.view', 'expenses.view'])) _label('FINANCE'),
      if (_can('invoices.view')) _item(Icons.receipt_long_rounded, 'Invoices', 13, AppTheme.success),
      if (_can('transactions.view')) _item(Icons.swap_horiz_rounded, 'History', 4, AppTheme.info),
      if (_can('expenses.view')) _item(Icons.account_balance_wallet_rounded, 'Expenses', 8, AppTheme.danger),
      if (_canAny(['products.view', 'lockers.view', 'events.view', 'notices.view'])) _label('OTHER'),
      if (_can('products.view')) _item(Icons.storefront_rounded, 'Products', 9, AppTheme.warning),
      if (_can('lockers.view') && _showLockersForPlan) _item(Icons.lock_outline_rounded, 'Lockers', 15, const Color(0xFF10B981)),
      if (_can('events.view')) _item(Icons.event_rounded, 'Events', 16, const Color(0xFFF59E0B)),
      if (_can('notices.view')) _item(Icons.campaign_rounded, 'Notices', 10, const Color(0xFF8B5CF6)),
      _label('ACCOUNT'),
      _pushItem(Icons.bug_report_rounded, 'Report a Bug', const ReportBugScreen(), AppTheme.danger),
      if (_can('settings.view')) _item(Icons.settings_rounded, 'Settings', 14, AppTheme.info),
    ];
  }

  void _navigateTo(int index) {
    HapticFeedback.lightImpact();
    Navigator.pop(context);
    _goTo(index);
  }

  Future<bool> _onWillPop() async {
    if (_index != 0) {
      _goTo(0);
      return false;
    }
    final now = DateTime.now();
    if (_lastBackPress == null || now.difference(_lastBackPress!) > const Duration(seconds: 2)) {
      _lastBackPress = now;
      Toast.info(context, 'Press back again to exit');
      return false;
    }
    SystemNavigator.pop();
    return true;
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final userType = auth.user?['type'] ?? 'admin';
    final isMember = userType == 'trainee';
    final isTrainer = userType == 'trainer';
    final isStaff = userType == 'staff';

    final pages = isTrainer ? _trainerPages : (isMember ? _memberPages : (isStaff ? _staffAdminPages() : _adminPages));
    final titles = isTrainer ? _trainerTitles : (isMember ? _memberTitles : _adminTitles);
    if (_index >= pages.length) _index = 0;
    if (!_planCanIndex(_index)) _index = 0;
    if (isStaff && !_staffCanIndex(_index)) _index = _staffDefaultIndex();

    final t = context.tokens;
    // Staff bottom-nav can point to non-sequential modules (e.g. Invoices index 13),
    // so staff uses simple indexed pages instead of a swipe PageView.
    _navCount = isStaff ? 0 : 5;
    final navItems = isStaff ? _staffNavItems() : isTrainer
        ? const [
            _NavSpec(Icons.space_dashboard_outlined, Icons.space_dashboard_rounded, 'Home', 0),
            _NavSpec(Icons.people_outline_rounded, Icons.people_rounded, 'Members', 1),
            _NavSpec(Icons.fitness_center_outlined, Icons.fitness_center_rounded, 'Workouts', 2),
            _NavSpec(Icons.self_improvement_outlined, Icons.self_improvement_rounded, 'Classes', 3),
            _NavSpec(Icons.settings_outlined, Icons.settings_rounded, 'Settings', 4),
          ]
        : isMember
        ? const [
            _NavSpec(Icons.space_dashboard_outlined, Icons.space_dashboard_rounded, 'Home', 0),
            _NavSpec(Icons.fact_check_outlined, Icons.fact_check_rounded, 'Visits', 1),
            _NavSpec(Icons.qr_code_scanner_rounded, Icons.qr_code_scanner_rounded, 'Scan', 2),
            _NavSpec(Icons.fitness_center_outlined, Icons.fitness_center_rounded, 'Workout', 3),
            _NavSpec(Icons.settings_outlined, Icons.settings_rounded, 'Settings', 4),
          ]
        : const [
            _NavSpec(Icons.space_dashboard_outlined, Icons.space_dashboard_rounded, 'Home', 0),
            _NavSpec(Icons.people_outline_rounded, Icons.people_rounded, 'Members', 1),
            _NavSpec(Icons.qr_code_scanner_rounded, Icons.qr_code_scanner_rounded, 'Check In', 2),
            _NavSpec(Icons.bar_chart_rounded, Icons.bar_chart_rounded, 'Reports', 3),
            _NavSpec(Icons.swap_horiz_rounded, Icons.swap_horiz_rounded, 'History', 4),
          ];

    return PopScope(
      canPop: false,
      onPopInvoked: (didPop) async {
        if (didPop) return;
        final shouldExit = await _onWillPop();
        if (shouldExit && context.mounted) SystemNavigator.pop();
      },
      child: Scaffold(
        drawer: _buildDrawer(auth, isMember, isTrainer, isStaff),
        appBar: _buildAppBar(titles, isMember, isTrainer, isStaff),
        body: SafeArea(
          bottom: false,
          child: _index < _navCount
              // Primary tabs → swipeable PageView with smooth animation.
              ? PageView.builder(
                  controller: _pageController,
                  physics: const ClampingScrollPhysics(),
                  allowImplicitScrolling: false,
                  itemCount: _navCount,
                  onPageChanged: (i) {
                    // Only vibrate on user swipe, not during programmatic animation
                    if (!_isAnimating) HapticFeedback.lightImpact();
                    setState(() => _index = i);
                    ref.read(navIndexProvider.notifier).state = i;
                  },
                  itemBuilder: (_, i) => _KeepAlivePage(child: pages[i]),
                )
              // Deeper drawer pages → simple fade, not part of the swipe set.
              : AnimatedSwitcher(
                  duration: const Duration(milliseconds: 260),
                  transitionBuilder: (child, anim) => FadeTransition(opacity: anim, child: child),
                  child: KeyedSubtree(key: ValueKey(_index), child: pages[_index]),
                ),
        ),
        bottomNavigationBar: _buildBottomNav(navItems, t),
      ),
    );
  }

  PreferredSizeWidget _buildAppBar(List<String> titles, bool isMember, bool isTrainer, bool isStaff) {
    final title = titles[_index];
    return AppBar(
      leading: Builder(
        builder: (ctx) => Padding(
          padding: const EdgeInsets.only(left: 12),
          child: IconButton(
            onPressed: () => Scaffold.of(ctx).openDrawer(),
            icon: const Icon(Icons.menu_rounded),
            style: IconButton.styleFrom(backgroundColor: context.tokens.surface, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
          ),
        ),
      ),
      titleSpacing: 8,
      title: _index == 0
          ? Row(children: [
              SizedBox(width: 30, height: 30, child: ClipRRect(borderRadius: BorderRadius.circular(9), child: Image.asset('assets/images/gymxbook_logo_icon.png', fit: BoxFit.cover))),
              const SizedBox(width: 8),
              Text('GymXBook', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w700, fontSize: 19, letterSpacing: -0.4, color: context.tokens.text)),
            ])
          : Text(title, style: context.typo.titleLarge),
      actions: [
        _ThemeToggleButton(),
        if (!isTrainer && !isStaff)
          Padding(
          padding: const EdgeInsets.only(right: 12),
          child: Consumer(
            builder: (context, ref, _) {
              final notif = ref.watch(notificationsProvider);
              final count = notif.unreadCount;
              final hasUnread = count > 0;
              final notificationIndex = isMember ? 6 : 11;

              return IconButton(
                onPressed: () => _goTo(notificationIndex),
                tooltip: 'Notifications',
                icon: hasUnread
                    ? Badge(
                        label: Text(count > 9 ? '9+' : count.toString()),
                        backgroundColor: AppTheme.danger,
                        child: const Icon(Icons.notifications_none_rounded),
                      )
                    : const Icon(Icons.notifications_none_rounded),
                style: IconButton.styleFrom(backgroundColor: context.tokens.surface, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
              );
            },
          ),
        ),
      ],
    );
  }

  // ── DOCKED BOTTOM NAV — Indicator design (permanent) ──────────────
  Widget _buildBottomNav(List<_NavSpec> items, dynamic t) {
    return Container(
      decoration: BoxDecoration(
        color: context.tokens.surface,
        border: Border(top: BorderSide(color: context.tokens.border)),
        boxShadow: [BoxShadow(color: context.tokens.shadow, blurRadius: 20, offset: const Offset(0, -4), spreadRadius: -8)],
      ),
      // Reserve the Android system-nav inset so the bar is never hidden.
      child: SafeArea(top: false, child: _navIndicator(items)),
    );
  }

  bool _isSel(int i) => _index == i;

  void _tap(int i) {
    HapticFeedback.lightImpact();
    _goTo(i);
  }

  Widget _navIndicator(List<_NavSpec> items) {
    return SizedBox(
      height: 62,
      child: Row(
        children: items.map((it) {
          final selected = _isSel(it.index);
          return Expanded(
            child: GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () => _tap(it.index),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 260),
                    height: 3,
                    width: selected ? 26 : 0,
                    decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(4)),
                  ),
                  const SizedBox(height: 7),
                  Icon(selected ? it.active : it.icon, size: 23, color: selected ? AppTheme.brand : context.tokens.textTertiary),
                  const SizedBox(height: 4),
                  Text(it.label, overflow: TextOverflow.ellipsis, maxLines: 1, style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: selected ? FontWeight.w700 : FontWeight.w500, color: selected ? AppTheme.brand : context.tokens.textTertiary)),
                ],
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  // ── DRAWER ───────────────────────────────────────────────────────
  Widget _buildDrawer(auth, bool isMember, bool isTrainer, bool isStaff) {
    final t = context.tokens;
    return Drawer(
      backgroundColor: t.bg,
      width: 306,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.horizontal(right: Radius.circular(28))),
      child: SafeArea(
        child: Column(
          children: [
            // Hero profile header
            Container(
              margin: const EdgeInsets.all(14),
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(22), boxShadow: [BoxShadow(color: AppTheme.brand.withOpacity(0.35), blurRadius: 20, offset: const Offset(0, 8))]),
              child: Row(children: [
                Stack(children: [
                  CircleAvatar(radius: 27, backgroundColor: Colors.white.withOpacity(0.15), backgroundImage: const AssetImage('assets/images/gymxbook_logo_icon.png'), child: null),
                  Positioned(bottom: 0, right: 0, child: Container(width: 14, height: 14, decoration: BoxDecoration(color: AppTheme.success, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2)))),
                ]),
                const SizedBox(width: 14),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(auth.user?['company_name'] ?? auth.user?['name'] ?? (isMember ? 'Member' : 'Gym Owner'), style: GoogleFonts.poppins(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16), overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 2),
                  Text(auth.user?['email'] ?? '', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.8), fontSize: 11.5), overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 8),
                  _subscriptionBadge(auth, isMember, isTrainer),
                ])),
              ]),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(10, 4, 10, 8),
                children: isTrainer
                    ? [
                        _label('TRAINER AREA'),
                        _item(Icons.space_dashboard_rounded, 'Home', 0, AppTheme.brand),
                        _item(Icons.people_rounded, 'Assigned Members', 1, AppTheme.info),
                        _item(Icons.fitness_center_rounded, 'Workouts', 2, AppTheme.warning),
                        _item(Icons.self_improvement_rounded, 'Classes', 3, AppTheme.success),
                        _item(Icons.calculate_rounded, 'BMI Calculator', 5, const Color(0xFF8B5CF6)),
                        _label('ACCOUNT'),
                        _item(Icons.settings_rounded, 'Settings', 4, context.tokens.textSecondary),
                      ]
                    : isMember
                    ? [
                        _label('MEMBER AREA'),
                        _item(Icons.space_dashboard_rounded, 'Home', 0, AppTheme.brand),
                        _item(Icons.fact_check_rounded, 'My Attendance', 1, AppTheme.info),
                        _item(Icons.qr_code_scanner_rounded, 'Scan QR Attendance', 2, AppTheme.success),
                        _item(Icons.fitness_center_rounded, 'Workout Plan', 3, AppTheme.warning),
                        _item(Icons.calculate_rounded, 'BMI Calculator', 7, AppTheme.brand),
                        _item(Icons.campaign_rounded, 'Notices', 5, const Color(0xFF8B5CF6)),
                        _item(Icons.notifications_none_rounded, 'Notifications', 6, AppTheme.danger),
                        _label('ACCOUNT'),
                        _item(Icons.settings_rounded, 'Settings', 4, context.tokens.textSecondary),
                      ]
                    : isStaff
                    ? _staffDrawerItems()
                    : [
                        _label('MAIN'),
                        _item(Icons.space_dashboard_rounded, 'Dashboard', 0, AppTheme.brand),
                        _item(Icons.people_rounded, 'Members', 1, AppTheme.info),
                        if (_showTrainersForPlan) _item(Icons.sports_martial_arts_rounded, 'Trainers', 5, AppTheme.success),
                        _item(Icons.fact_check_rounded, 'Attendance', 2, AppTheme.warning),
                        _item(Icons.card_membership_rounded, 'Plans', 6, const Color(0xFF8B5CF6)),
                        _item(Icons.self_improvement_rounded, 'Classes', 7, const Color(0xFFEC4899)),
                        _item(Icons.bar_chart_rounded, 'Reports', 3, const Color(0xFF06B6D4)),
                        _item(Icons.qr_code_2_rounded, 'Gym QR Code', 12, const Color(0xFF6366F1)),
                        _label('MANAGE'),
                        _item(Icons.receipt_long_rounded, 'Invoices', 13, AppTheme.success),
                        _item(Icons.swap_horiz_rounded, 'History', 4, AppTheme.info),
                        _item(Icons.account_balance_wallet_rounded, 'Expenses', 8, AppTheme.danger),
                        _item(Icons.storefront_rounded, 'Products', 9, AppTheme.warning),
                        if (_showLockersForPlan) _item(Icons.lock_outline_rounded, 'Lockers', 15, const Color(0xFF10B981)),
                        _item(Icons.event_rounded, 'Events', 16, const Color(0xFFF59E0B)),
                        _item(Icons.campaign_rounded, 'Notices', 10, const Color(0xFF8B5CF6)),
                        _item(Icons.notifications_none_rounded, 'Notifications', 11, const Color(0xFF6366F1)),
                        _label('OTHER'),
                        _pushItem(Icons.workspace_premium_rounded, 'Subscription', SubscriptionScreen(), AppTheme.brand),
                        _pushItem(Icons.qr_code_scanner_rounded, 'Web Login', const WebLoginScanScreen(), const Color(0xFF6366F1)),
                        _pushItem(Icons.bug_report_rounded, 'Report a Bug', ReportBugScreen(), AppTheme.danger),
                        _item(Icons.settings_rounded, 'Settings', 14, AppTheme.info),
                      ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 6, 16, 12),
              child: Row(children: [
                Text('GymXBook', style: context.typo.labelSmall?.copyWith(color: t.textTertiary, letterSpacing: 0.5)),
                const Spacer(),
                Text('v1.1.1', style: context.typo.labelSmall?.copyWith(color: t.textTertiary)),
              ]),
            ),
          ],
        ),
      ),
    );
  }

  // Sidebar subscription pill — shows "Expires in N days" and opens the
  // subscription page on tap (admins only). Members keep the "Member" badge.
  Widget _subscriptionBadge(auth, bool isMember, bool isTrainer) {
    if ((auth.user?['type'] ?? '').toString() == 'staff') {
      final role = auth.user?['staff_role'];
      final roleName = role is Map ? (role['name'] ?? 'Staff') : 'Staff';
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: Colors.white.withOpacity(0.22), borderRadius: BorderRadius.circular(20)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.shield_rounded, size: 12, color: Colors.white),
          const SizedBox(width: 5),
          Text(roleName.toString(), style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w700, color: Colors.white)),
        ]),
      );
    }
    if (isTrainer) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: Colors.white.withOpacity(0.22), borderRadius: BorderRadius.circular(20)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.sports_martial_arts_rounded, size: 12, color: Colors.white),
          const SizedBox(width: 5),
          Text('Trainer', style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w700, color: Colors.white)),
        ]),
      );
    }
    if (isMember) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: Colors.white.withOpacity(0.22), borderRadius: BorderRadius.circular(20)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.verified_rounded, size: 12, color: Colors.white),
          const SizedBox(width: 5),
          Text('Member', style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w700, color: Colors.white)),
        ]),
      );
    }
    final days = auth.subscriptionDaysLeft as int?;
    final expired = auth.subscriptionExpired == true || (days != null && days < 0);
    String label;
    IconData icon;
    if (days == null) {
      label = 'View Subscription';
      icon = Icons.workspace_premium_rounded;
    } else if (expired) {
      label = 'Expired • Renew';
      icon = Icons.error_outline_rounded;
    } else {
      label = 'Expires in $days ${days == 1 ? 'day' : 'days'}';
      icon = Icons.schedule_rounded;
    }
    return GestureDetector(
      onTap: () {
        HapticFeedback.selectionClick();
        Navigator.pop(context);
        Navigator.push(context, MaterialPageRoute(builder: (_) => SubscriptionScreen()));
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 4),
        decoration: BoxDecoration(color: Colors.white.withOpacity(0.22), borderRadius: BorderRadius.circular(20)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 12, color: Colors.white),
          const SizedBox(width: 5),
          Text(label, style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w700, color: Colors.white)),
          const SizedBox(width: 3),
          const Icon(Icons.chevron_right_rounded, size: 13, color: Colors.white),
        ]),
      ),
    );
  }

  Widget _label(String title) => Padding(
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 5),
        child: Text(title, style: context.typo.labelSmall?.copyWith(letterSpacing: 1.2)),
      );

  // Colorful icons for each drawer item.
  Widget _item(IconData icon, String title, int index, Color color) {
    final selected = _index == index;
    final t = context.tokens;
    final iconColor = selected ? AppTheme.brand : color;
    final iconBg = selected ? AppTheme.brand.withOpacity(0.16) : color.withOpacity(context.isDark ? 0.12 : 0.10);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 1),
      child: Material(
        color: selected ? AppTheme.brand.withOpacity(0.10) : Colors.transparent,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () => _navigateTo(index),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: Row(children: [
              Container(width: 34, height: 34, decoration: BoxDecoration(color: iconBg, borderRadius: BorderRadius.circular(10)), child: Icon(icon, size: 18, color: iconColor)),
              const SizedBox(width: 12),
              Expanded(child: Text(title, style: context.typo.titleSmall?.copyWith(fontSize: 13.5, fontWeight: selected ? FontWeight.w700 : FontWeight.w500, color: selected ? t.text : t.textSecondary))),
              if (selected) Container(width: 6, height: 6, decoration: const BoxDecoration(color: AppTheme.brand, shape: BoxShape.circle)),
            ]),
          ),
        ),
      ),
    );
  }

  /// Drawer item that pushes a standalone page instead of switching a shell tab.
  Widget _pushItem(IconData icon, String title, Widget page, Color color) {
    final t = context.tokens;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 1),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: () {
            HapticFeedback.selectionClick();
            Navigator.pop(context);
            Navigator.push(context, MaterialPageRoute(builder: (_) => page));
          },
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: Row(children: [
              Container(width: 34, height: 34, decoration: BoxDecoration(color: color.withOpacity(context.isDark ? 0.16 : 0.12), borderRadius: BorderRadius.circular(10)), child: Icon(icon, size: 18, color: color)),
              const SizedBox(width: 12),
              Expanded(child: Text(title, style: context.typo.titleSmall?.copyWith(fontSize: 13.5, fontWeight: FontWeight.w500, color: t.textSecondary))),
            ]),
          ),
        ),
      ),
    );
  }
}

class _TrainerComingSoonPage extends StatelessWidget {
  final String title;
  final IconData icon;
  const _TrainerComingSoonPage({required this.title, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: SurfaceCard(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              IconBadge(icon, color: AppTheme.brand, size: 74, iconSize: 36),
              const SizedBox(height: 18),
              Text(title, style: context.typo.titleLarge, textAlign: TextAlign.center),
              const SizedBox(height: 8),
              Text('This trainer module will be completed in the next phase.', style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary), textAlign: TextAlign.center),
            ]),
          ),
        ),
      ),
    );
  }
}

class StaffAccessDeniedPage extends StatelessWidget {
  final String permission;
  const StaffAccessDeniedPage({super.key, required this.permission});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: SurfaceCard(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              IconBadge(Icons.lock_rounded, color: AppTheme.danger, size: 78, iconSize: 38),
              const SizedBox(height: 18),
              Text('Access Denied', style: context.typo.titleLarge, textAlign: TextAlign.center),
              const SizedBox(height: 8),
              Text('Your staff role does not have permission to open this module.', style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary), textAlign: TextAlign.center),
              const SizedBox(height: 14),
              StatusBadge(permission, color: AppTheme.danger, icon: Icons.key_rounded),
            ]),
          ),
        ),
      ),
    );
  }
}

class _NavSpec {
  final IconData icon;
  final IconData active;
  final String label;
  final int index;
  const _NavSpec(this.icon, this.active, this.label, this.index);
}

/// Keeps a PageView tab alive so swiping between the 5 primary tabs is instant
/// and doesn't re-run initState / network calls each time (smoother slider).
class _KeepAlivePage extends StatefulWidget {
  final Widget child;
  const _KeepAlivePage({required this.child});
  @override
  State<_KeepAlivePage> createState() => _KeepAlivePageState();
}

class _KeepAlivePageState extends State<_KeepAlivePage> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;
  @override
  Widget build(BuildContext context) {
    super.build(context);
    return widget.child;
  }
}

class _ThemeToggleButton extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dark = context.isDark;
    return Padding(
      padding: const EdgeInsets.only(right: 4),
      child: IconButton(
        onPressed: () => ref.read(themeModeProvider.notifier).toggle(Theme.of(context).brightness),
        icon: AnimatedSwitcher(
          duration: const Duration(milliseconds: 300),
          transitionBuilder: (c, a) => RotationTransition(turns: a, child: FadeTransition(opacity: a, child: c)),
          child: Icon(dark ? Icons.light_mode_rounded : Icons.dark_mode_rounded, key: ValueKey(dark), color: dark ? AppTheme.brandAmber : context.tokens.textSecondary),
        ),
        style: IconButton.styleFrom(backgroundColor: context.tokens.surface, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
      ),
    );
  }
}
