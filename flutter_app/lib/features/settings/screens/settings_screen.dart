import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/theme/theme_provider.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'gym_profile_screen.dart';
import 'change_password_screen.dart';
import 'package:gymxbook/features/subscription/screens/subscription_screen.dart';
import 'package:gymxbook/features/qr/screens/admin_qr_screen.dart';
import 'package:gymxbook/features/notices/screens/notices_list_screen.dart';
import 'package:gymxbook/features/notifications/screens/notifications_screen.dart';

class SettingsScreen extends ConsumerWidget {
  static const String currentAppVersion = '1.1.1';

  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    final user = auth.user;
    final isAdmin = (user?['type'] == 'admin' || user?['type'] == 'owner');
    final mode = ref.watch(themeModeProvider);

    return Scaffold(
      body: ListView(
        padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
        children: [
          FadeInUp(child: Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)),
            child: Row(children: [
              GxAvatar(name: user?['name'] ?? 'A', size: 58),
              const SizedBox(width: 14),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(user?['name'] ?? 'Admin', style: context.typo.titleLarge?.copyWith(color: Colors.white)),
                const SizedBox(height: 2),
                Text(user?['email'] ?? '', style: context.typo.bodySmall?.copyWith(color: Colors.white.withOpacity(0.7))),
                if ((user?['phone_number'] ?? '') != '') Text(user?['phone_number'] ?? '', style: context.typo.bodySmall?.copyWith(color: Colors.white.withOpacity(0.55), fontSize: 11.5)),
              ])),
            ]),
          )),
          _updateAvailableCard(context, ref),
          const SizedBox(height: 20),
          const SectionHeader('Appearance'),
          const SizedBox(height: 10),
          FadeInUp(delayMs: 40, child: SurfaceCard(
            padding: const EdgeInsets.all(6),
            child: Row(children: [
              _themeOption(context, ref, 'Light', Icons.light_mode_rounded, ThemeMode.light, mode),
              _themeOption(context, ref, 'Dark', Icons.dark_mode_rounded, ThemeMode.dark, mode),
              _themeOption(context, ref, 'System', Icons.brightness_auto_rounded, ThemeMode.system, mode),
            ]),
          )),
          if (isAdmin) ...[
            const SizedBox(height: 10),
            FadeInUp(delayMs: 55, child: SurfaceCard(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4), child: _dashboardFinanceToggle(context, ref))),
          ],
          const SizedBox(height: 20),
          _group(context, 'Account & Security', [
            _item(context, Icons.person_rounded, AppTheme.brand, 'Personal Profile', 'Name, email and phone', () => _showProfileSheet(context, ref)),
            _item(context, Icons.lock_reset_rounded, AppTheme.info, 'Change Password', 'Update login password', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ChangePasswordScreen()))),
          ]),
          if (isAdmin) ...[
            const SizedBox(height: 16),
            _group(context, 'Business Settings', [
              _item(context, Icons.workspace_premium_rounded, AppTheme.brand, 'Subscription', 'Top-up durations & expiry', () => Navigator.push(context, MaterialPageRoute(builder: (_) => SubscriptionScreen()))),
              _item(context, Icons.qr_code_scanner_rounded, const Color(0xFF6366F1), 'Web Login', 'Scan QR to login on PC/web', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const WebLoginScanScreen()))),
              _item(context, Icons.fitness_center_rounded, AppTheme.success, 'Gym Profile', 'Business name and contact', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const GymProfileScreen()))),
              _item(context, Icons.qr_code_2_rounded, const Color(0xFF6366F1), 'Attendance QR', 'View and print gym QR', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminQRScreen(standalone: true)))),
              _item(context, Icons.campaign_rounded, const Color(0xFF8B5CF6), 'Notices', 'Manage gym notices', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesListScreen(standalone: true)))),
              _item(context, Icons.notifications_rounded, AppTheme.warning, 'Notifications', 'View all alerts', () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NotificationsScreen(standalone: true)))),
            ]),
          ],
          const SizedBox(height: 16),
          _group(context, 'App', [
            _item(context, Icons.info_outline_rounded, context.tokens.textSecondary, 'Version', '1.1.1', null, trailing: Text('v1.1.1', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))),
          ]),
          const SizedBox(height: 24),
          SizedBox(width: double.infinity, child: OutlinedButton.icon(
            style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger, side: const BorderSide(color: AppTheme.danger), padding: const EdgeInsets.symmetric(vertical: 15)),
            onPressed: () async {
              final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(title: const Text('Logout?'), content: const Text('Are you sure you want to logout?'), actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Logout'))]));
              if (ok == true) {
                await ref.read(authProvider.notifier).logout();
                if (context.mounted) Navigator.of(context).popUntil((route) => route.isFirst);
              }
            },
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Logout'),
          )),
          const SizedBox(height: 14),
          Center(child: Text('Powered by GymXBook', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 10))),
        ],
      ),
    );
  }

  Widget _updateAvailableCard(BuildContext context, WidgetRef ref) {
    return FutureBuilder<Map<String, dynamic>>(
      future: ref.read(apiClientProvider).getAppUpdateInfo(currentVersion: currentAppVersion),
      builder: (context, snapshot) {
        if (!snapshot.hasData) return const SizedBox.shrink();

        final data = snapshot.data ?? {};
        final available = data['update_available'] == true;
        if (!available) return const SizedBox.shrink();

        final latest = data['latest_version']?.toString() ?? '';
        final message = data['message']?.toString() ?? 'A new version of GymXBook is available.';
        final url = data['update_url']?.toString() ?? '';
        final force = data['force_update'] == true;

        return FadeInUp(
          delayMs: 60,
          child: Container(
            margin: const EdgeInsets.only(top: 16),
            decoration: BoxDecoration(
              gradient: AppTheme.darkHeroGradient,
              borderRadius: BorderRadius.circular(26),
              boxShadow: [
                BoxShadow(
                  color: AppTheme.brand.withOpacity(0.22),
                  blurRadius: 24,
                  offset: const Offset(0, 10),
                  spreadRadius: -6,
                ),
              ],
            ),
            child: Stack(children: [
              Positioned(right: -24, top: -26, child: Icon(Icons.system_update_alt_rounded, size: 130, color: Colors.white.withOpacity(0.055))),
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Container(
                      width: 48,
                      height: 48,
                      decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(16)),
                      child: const Icon(Icons.rocket_launch_rounded, color: Colors.white, size: 25),
                    ),
                    const SizedBox(width: 12),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(force ? 'Update Required' : 'Update Available', style: context.typo.titleLarge?.copyWith(color: Colors.white, fontWeight: FontWeight.w800)),
                      const SizedBox(height: 2),
                      Text('Current v$currentAppVersion  →  Latest v$latest', style: context.typo.bodySmall?.copyWith(color: Colors.white.withOpacity(0.66), fontWeight: FontWeight.w600)),
                    ])),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                      decoration: BoxDecoration(color: (force ? AppTheme.danger : AppTheme.warning).withOpacity(0.20), borderRadius: BorderRadius.circular(30), border: Border.all(color: (force ? AppTheme.danger : AppTheme.warning).withOpacity(0.35))),
                      child: Text(force ? 'FORCE' : 'NEW', style: context.typo.labelSmall?.copyWith(color: force ? AppTheme.danger : AppTheme.warning, fontWeight: FontWeight.w900)),
                    ),
                  ]),
                  const SizedBox(height: 16),
                  Text(message, style: context.typo.bodyMedium?.copyWith(color: Colors.white.withOpacity(0.82), height: 1.45)),
                  const SizedBox(height: 18),
                  FireButton(
                    label: 'Update Now',
                    icon: Icons.open_in_new_rounded,
                    onPressed: url.isEmpty ? null : () => _openUpdateLink(context, url),
                  ),
                ]),
              ),
            ]),
          ),
        );
      },
    );
  }

  Future<void> _openUpdateLink(BuildContext context, String url) async {
    try {
      final uri = Uri.parse(url);
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        if (context.mounted) Toast.error(context, 'Could not open update link');
      }
    } catch (_) {
      if (context.mounted) Toast.error(context, 'Invalid update link');
    }
  }

  Widget _themeOption(BuildContext context, WidgetRef ref, String label, IconData icon, ThemeMode value, ThemeMode current) {
    final active = value == current;
    return Expanded(child: Pressable(radius: 16, onTap: () => ref.read(themeModeProvider.notifier).set(value), child: AnimatedContainer(
      duration: const Duration(milliseconds: 220),
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(gradient: active ? AppTheme.fireGradient : null, borderRadius: BorderRadius.circular(16)),
      child: Column(children: [
        Icon(icon, size: 22, color: active ? Colors.white : context.tokens.textSecondary),
        const SizedBox(height: 5),
        Text(label, style: context.typo.labelMedium?.copyWith(color: active ? Colors.white : context.tokens.textSecondary, fontWeight: FontWeight.w700)),
      ]),
    )));
  }

  Widget _group(BuildContext context, String title, List<Widget> children) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      SectionHeader(title),
      const SizedBox(height: 10),
      SurfaceCard(padding: const EdgeInsets.symmetric(vertical: 4), child: Column(children: children)),
    ]);
  }

  Widget _dashboardFinanceToggle(BuildContext context, WidgetRef ref) {
    return const _DashboardFinanceToggle();
  }

  Widget _item(BuildContext context, IconData icon, Color color, String title, String subtitle, VoidCallback? onTap, {Widget? trailing}) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      leading: IconBadge(icon, color: color, size: 40, iconSize: 19),
      title: Text(title, style: context.typo.titleSmall),
      subtitle: Text(subtitle, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5)),
      trailing: trailing ?? (onTap != null ? Icon(Icons.chevron_right_rounded, size: 20, color: context.tokens.textTertiary) : null),
      onTap: onTap,
    );
  }

  void _showProfileSheet(BuildContext context, WidgetRef ref) {
    final auth = ref.read(authProvider);
    final user = auth.user;
    final nameCtrl = TextEditingController(text: user?['name'] ?? '');
    final emailCtrl = TextEditingController(text: user?['email'] ?? '');
    final phoneCtrl = TextEditingController(text: user?['phone_number'] ?? '');
    showAppSheet(context, child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [IconBadge(Icons.person_rounded, color: AppTheme.brand), const SizedBox(width: 12), Text('Personal Profile', style: context.typo.titleLarge)]),
        const SizedBox(height: 18),
        TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Full Name', prefixIcon: Icon(Icons.person_outline_rounded))),
        const SizedBox(height: 12),
        TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.email_outlined))),
        const SizedBox(height: 12),
        TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Phone', prefixIcon: Icon(Icons.phone_rounded))),
        const SizedBox(height: 20),
        FireButton(label: 'Update Profile', onPressed: () async {
          try {
            await ref.read(apiClientProvider).updateSettings({
              'name': nameCtrl.text.trim(),
              'email': emailCtrl.text.trim(),
              'phone_number': phoneCtrl.text.trim(),
            });
            if (context.mounted) Navigator.pop(context);
            ref.read(authProvider.notifier).checkAuth();
            if (context.mounted) Toast.success(context, 'Profile updated');
          } catch (e) { Toast.error(context, 'Failed to update'); }
        }),
      ]),
    ));
  }
}

class _DashboardFinanceToggle extends ConsumerStatefulWidget {
  const _DashboardFinanceToggle();

  @override
  ConsumerState<_DashboardFinanceToggle> createState() => _DashboardFinanceToggleState();
}

class _DashboardFinanceToggleState extends ConsumerState<_DashboardFinanceToggle> {
  bool enabled = false;
  bool loading = true;
  bool saving = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await ref.read(apiClientProvider).getSettings();
      final settings = res['settings'];
      final raw = settings is Map ? settings['show_revenue_expense_card'] : null;
      if (mounted) {
        setState(() {
          enabled = raw != null && raw.toString() == '1';
          loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> _save(bool value) async {
    final old = enabled;
    setState(() {
      enabled = value;
      saving = true;
    });

    try {
      await ref.read(apiClientProvider).updateSettings({
        'show_revenue_expense_card': value ? '1' : '0',
      });
      if (mounted) Toast.success(context, value ? 'Dashboard card enabled' : 'Dashboard card hidden');
    } catch (_) {
      if (mounted) {
        setState(() => enabled = old);
        Toast.error(context, 'Could not update setting');
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      leading: IconBadge(Icons.payments_rounded, color: AppTheme.success, size: 40, iconSize: 19),
      title: Text('Revenue & Expense Card', style: context.typo.titleSmall),
      subtitle: Text(
        loading ? 'Loading dashboard setting...' : 'Show this month revenue/expense on dashboard',
        style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5),
      ),
      trailing: saving
          ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))
          : Switch.adaptive(
              value: enabled,
              activeColor: AppTheme.success,
              onChanged: loading ? null : _save,
            ),
    );
  }
}

class WebLoginScanScreen extends ConsumerStatefulWidget {
  const WebLoginScanScreen({super.key});

  @override
  ConsumerState<WebLoginScanScreen> createState() => _WebLoginScanScreenState();
}

class _WebLoginScanScreenState extends ConsumerState<WebLoginScanScreen> with WidgetsBindingObserver {
  final MobileScannerController _controller = MobileScannerController(
    facing: CameraFacing.back,
    autoStart: false,
    detectionSpeed: DetectionSpeed.noDuplicates,
    detectionTimeoutMs: 1200,
  );

  bool processing = false;
  bool success = false;
  bool loadingSessions = true;
  List activeSessions = [];
  String? message;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadSessions();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller.dispose();
    super.dispose();
  }

  Future<void> _loadSessions() async {
    setState(() => loadingSessions = true);
    try {
      final res = await ref.read(apiClientProvider).getWebLoginSessions();
      final list = res['active_sessions'] as List? ?? [];
      if (!mounted) return;
      setState(() {
        activeSessions = list;
        loadingSessions = false;
        message = list.isEmpty
            ? 'Open web.gymxbook.com → App QR and scan the QR.'
            : 'You are logged in on ${list.length} PC session${list.length == 1 ? '' : 's'}.';
      });
      if (list.isEmpty && !success) {
        try { await _controller.start(); } catch (_) {}
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        activeSessions = [];
        loadingSessions = false;
        message = 'Open web.gymxbook.com → App QR and scan the QR.';
      });
      try { await _controller.start(); } catch (_) {}
    }
  }

  Future<void> _logoutPcSession(int? id) async {
    setState(() => processing = true);
    try {
      await ref.read(apiClientProvider).logoutWebLoginSession(sessionId: id);
      if (!mounted) return;
      Toast.success(context, id == null ? 'All PC sessions logged out' : 'PC session logged out');
      setState(() {
        activeSessions = [];
        processing = false;
        success = false;
        message = 'Open web.gymxbook.com → App QR and scan the QR.';
      });
      try { await _controller.start(); } catch (_) {}
    } catch (e) {
      if (!mounted) return;
      setState(() => processing = false);
      Toast.error(context, _friendlyError(e));
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!mounted) return;
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      _controller.stop();
    } else if (state == AppLifecycleState.resumed && !processing && !success && activeSessions.isEmpty) {
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
        if (!success && activeSessions.isEmpty && !loadingSessions)
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
            if (loadingSessions)
              const SizedBox(width: 42, height: 42, child: CircularProgressIndicator(color: AppTheme.brand))
            else if (activeSessions.isNotEmpty)
              _activeSessionsCard()
            else if (!success)
              _scanFrame()
            else
              _successCard(),
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
                  if (processing) const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.brand))
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
          const Center(child: SizedBox(width: 42, height: 42, child: CircularProgressIndicator(color: AppTheme.brand))),
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
        top: top ? const BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
        bottom: !top ? const BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
        left: left ? const BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
        right: !left ? const BorderSide(color: AppTheme.brand, width: 4) : BorderSide.none,
      )),
    );
  }

  Widget _activeSessionsCard() {
    final session = activeSessions.first as Map;
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 24),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(28)),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        IconBadge(Icons.desktop_windows_rounded, color: AppTheme.success, size: 74, iconSize: 36),
        const SizedBox(height: 16),
        Text('PC Session Active', style: GoogleFonts.spaceGrotesk(fontSize: 24, fontWeight: FontWeight.w800)),
        const SizedBox(height: 8),
        Text(
          'Logged in ${session['login_time_human'] ?? 'recently'}',
          textAlign: TextAlign.center,
          style: context.typo.bodyMedium?.copyWith(color: context.tokens.textSecondary),
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(14)),
          child: Row(children: [
            const Icon(Icons.language_rounded, size: 18, color: AppTheme.info),
            const SizedBox(width: 8),
            Expanded(child: Text('${session['browser_user_agent'] ?? 'Web Browser'} • ${session['browser_ip'] ?? ''}', style: context.typo.bodySmall)),
          ]),
        ),
        const SizedBox(height: 18),
        FireButton(
          label: processing ? 'Logging out...' : 'Logout from PC',
          icon: Icons.logout_rounded,
          loading: processing,
          gradient: const LinearGradient(colors: [AppTheme.danger, Color(0xFFB91C1C)]),
          onPressed: processing ? null : () => _logoutPcSession(int.tryParse(session['id'].toString())),
        ),
        if (activeSessions.length > 1) ...[
          const SizedBox(height: 10),
          TextButton(
            onPressed: processing ? null : () => _logoutPcSession(null),
            child: const Text('Logout all PC sessions'),
          ),
        ],
      ]),
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
