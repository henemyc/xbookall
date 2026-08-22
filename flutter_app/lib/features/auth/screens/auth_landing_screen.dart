import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/storage/secure_storage.dart';
import 'package:gymxbook/features/auth/screens/register_screen.dart';

/// Public auth entry point. Forms live in bottom offcanvas sheets so the first
/// screen stays calm and product-like rather than immediately showing a form.
class AuthLandingScreen extends ConsumerStatefulWidget {
  const AuthLandingScreen({super.key});

  @override
  ConsumerState<AuthLandingScreen> createState() => _AuthLandingScreenState();
}

class _AuthLandingScreenState extends ConsumerState<AuthLandingScreen> {
  final _phoneCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    // A successful provider update can remove this landing widget before the
    // submit callback resumes. Close any open bottom sheet from the root
    // navigator first so it never remains over MainShell/Dashboard.
    ref.listenManual(authProvider, (previous, next) {
      if (next.isLoggedIn && mounted) {
        Navigator.of(context, rootNavigator: true).popUntil((route) => route.isFirst);
      }
    });
    Future.microtask(() async {
      final notice = await SecureStorage.consumeAuthNotice();
      if (mounted && notice != null && notice.isNotEmpty) {
        _nativeToast(notice);
      }
    });
  }

  static const MethodChannel _nativeToastChannel = MethodChannel('com.gymxbook.app/native_toast');

  Future<void> _nativeToast(String message, {bool error = false}) async {
    try {
      await _nativeToastChannel.invokeMethod<void>('show', {'message': message});
    } catch (_) {
      // Keeps login feedback available on non-Android platforms or if a custom
      // Android build has not included MainActivity yet.
      if (mounted) {
        if (error) {
          Toast.error(context, message);
        } else {
          Toast.success(context, message);
        }
      }
    }
  }

  @override
  void dispose() {
    _phoneCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  String _digitsOnly(String value) => value.replaceAll(RegExp(r'[^0-9]'), '');

  Future<void> _login() async {
    final phone = _digitsOnly(_phoneCtrl.text);
    final password = _passwordCtrl.text.trim();
    if (phone.length != 10 || !RegExp(r'^[6-9]').hasMatch(phone)) {
      _nativeToast('Enter a valid 10-digit phone number', error: true);
      return;
    }
    if (password.isEmpty) {
      _nativeToast('Password is required', error: true);
      return;
    }

    final ok = await ref.read(authProvider.notifier).login(email: phone, password: password);
    if (!mounted) return;
    if (ok) {
      _nativeToast('Welcome back!');
      // The auth listener closes the sheet before MainShell is displayed.
    } else {
      _nativeToast(ref.read(authProvider).error ?? 'Could not sign in. Please try again.', error: true);
    }
  }

  void _showLoginSheet() {
    bool obscure = true;
    bool otpMode = false;
    bool otpSent = false;
    bool otpBusy = false;
    bool checking = false;
    int step = 0;
    final otpCtrl = TextEditingController();
    final passFocus = FocusNode();

    showAppSheet(
      context,
      child: StatefulBuilder(
        builder: (sheetContext, setSheetState) => Consumer(
          builder: (context, sheetRef, _) {
            final auth = sheetRef.watch(authProvider);
            final phone = _digitsOnly(_phoneCtrl.text);
            Future<void> sendOtp() async {
              if (!RegExp(r'^[6-9]\d{9}$').hasMatch(phone)) { _nativeToast('Enter a valid 10-digit phone number', error: true); return; }
              setSheetState(() => otpBusy = true);
              try {
                await sheetRef.read(authProvider.notifier).sendLoginOtp(phone);
                if (!mounted) return;
                setSheetState(() { otpBusy = false; otpSent = true; });
                _nativeToast('OTP sent to WhatsApp');
              } catch (e) {
                setSheetState(() => otpBusy = false);
                _nativeToast(_apiError(e), error: true);
              }
            }
            Future<void> verifyOtp() async {
              if (!RegExp(r'^\d{6}$').hasMatch(otpCtrl.text.trim())) { _nativeToast('Enter the 6-digit OTP', error: true); return; }
              final ok = await sheetRef.read(authProvider.notifier).loginWithOtp(phone: phone, otp: otpCtrl.text.trim());
              if (!mounted || ok) return;
              _nativeToast(sheetRef.read(authProvider).error ?? 'OTP login failed', error: true);
            }
            return SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                Row(children: [
                  IconBadge(otpMode ? Icons.chat_rounded : Icons.login_rounded, color: AppTheme.brand, size: 48, iconSize: 23),
                  const SizedBox(width: 12),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(otpMode ? 'Login with WhatsApp' : 'Welcome back', style: context.typo.titleLarge),
                    Text(otpMode ? 'Verify a secure code to login.' : 'Sign in to manage your gym.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                  ])),
                ]),
                const SizedBox(height: 26),
                if (step == 0) ...[
                  Text('Phone Number', style: context.typo.labelMedium?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 7),
                  TextField(controller: _phoneCtrl, keyboardType: TextInputType.phone, inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(10)], decoration: const InputDecoration(prefixIcon: Icon(Icons.phone_rounded), hintText: '10-digit mobile number')),
                  const SizedBox(height: 24),
                  FireButton(
                    label: 'Continue',
                    icon: Icons.arrow_forward_rounded,
                    loading: checking,
                    onPressed: checking ? null : () async {
                      final enteredPhone = _digitsOnly(_phoneCtrl.text);
                      if (!RegExp(r'^[6-9]\d{9}$').hasMatch(enteredPhone)) { _nativeToast('Enter a valid 10-digit phone number', error: true); return; }
                      // Check the account exists BEFORE asking for a password.
                      setSheetState(() => checking = true);
                      try {
                        final exists = await sheetRef.read(apiClientProvider).checkAccountExists(phone: enteredPhone);
                        if (!mounted) return;
                        if (exists) {
                          setSheetState(() { checking = false; step = 1; });
                          // Auto-open the keyboard for the password field.
                          WidgetsBinding.instance.addPostFrameCallback((_) {
                            if (mounted) passFocus.requestFocus();
                          });
                        } else {
                          setSheetState(() => checking = false);
                          _nativeToast('No account found with this phone number. Please register first.', error: true);
                        }
                      } catch (_) {
                        setSheetState(() => checking = false);
                        _nativeToast('Could not verify this number. Please check your connection.', error: true);
                      }
                    },
                  ),
                ] else if (!otpMode) ...[
                  Text('Phone: $phone', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                  const SizedBox(height: 16),
                  Text('Password', style: context.typo.labelMedium?.copyWith(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 7),
                  TextField(controller: _passwordCtrl, focusNode: passFocus, obscureText: obscure, onSubmitted: (_) => auth.isLoading ? null : _login(), decoration: InputDecoration(prefixIcon: const Icon(Icons.lock_outline_rounded), hintText: 'Enter your password', suffixIcon: IconButton(icon: Icon(obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded, size: 20), onPressed: () => setSheetState(() => obscure = !obscure)))),
                  const SizedBox(height: 10),
                  Align(alignment: Alignment.centerRight, child: TextButton(onPressed: () => setSheetState(() { otpMode = true; otpSent = false; }), child: const Text('Login with WhatsApp OTP'))),
                  const SizedBox(height: 12),
                  FireButton(label: 'Login', icon: Icons.login_rounded, loading: auth.isLoading, onPressed: auth.isLoading ? null : _login),
                ] else ...[
                  Text('Phone: $phone', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                  const SizedBox(height: 16),
                  if (!otpSent) FireButton(label: 'Send OTP to WhatsApp', icon: Icons.chat_rounded, loading: otpBusy, onPressed: otpBusy ? null : sendOtp),
                  if (otpSent) ...[
                    TextField(controller: otpCtrl, keyboardType: TextInputType.number, inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)], decoration: const InputDecoration(prefixIcon: Icon(Icons.password_rounded), hintText: '6-digit OTP')),
                    const SizedBox(height: 14),
                    FireButton(label: 'Verify & Login', icon: Icons.verified_rounded, loading: auth.isLoading, onPressed: auth.isLoading ? null : verifyOtp),
                    TextButton(onPressed: otpBusy ? null : sendOtp, child: const Text('Resend OTP')),
                  ],
                  TextButton(onPressed: () => setSheetState(() { otpMode = false; otpSent = false; }), child: const Text('Use password instead')),
                ],
                const SizedBox(height: 6),
                Center(child: Text('Your account is protected and secure.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11))),
              ]),
            );
          },
        ),
      ),
    ).whenComplete(() {
      otpCtrl.dispose();
      passFocus.dispose();
    });
  }

  String _apiError(Object error) {
    try {
      final data = (error as dynamic).response?.data;
      if (data is Map) return (data['error'] ?? data['message'] ?? 'Request failed').toString();
    } catch (_) {}
    return 'Request failed. Please try again.';
  }

  void _showRegisterSheet() {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      // Lock the registration sheet: no swipe-down or outside-tap dismiss, so
      // users can't accidentally lose their half-filled registration.
      isDismissible: false,
      enableDrag: false,
      builder: (_) => FractionallySizedBox(
        heightFactor: .82,
        child: ClipRRect(
          borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
          child: const RegisterScreen(),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Scaffold(
      backgroundColor: t.bg,
      body: Stack(children: [
        Container(color: t.bg),
        Positioned(top: -90, right: -60, child: _glow(260, AppTheme.brand.withOpacity(.15))),
        Positioned(bottom: 80, left: -90, child: _glow(220, AppTheme.brandAmber.withOpacity(.10))),
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(24, 26, 24, 28),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Container(width: 54, height: 54, padding: const EdgeInsets.all(8), decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(18)), child: Image.asset('assets/images/gymxbook_foreground_logo.png')),
                const SizedBox(width: 12),
                Text('GymXBook', style: GoogleFonts.spaceGrotesk(color: t.text, fontWeight: FontWeight.w700, fontSize: 24, letterSpacing: -.6)),
              ]),
              const Spacer(),
              Text('Your gym.\nRunning beautifully.', style: GoogleFonts.spaceGrotesk(color: t.text, fontSize: 39, height: 1.0, fontWeight: FontWeight.w700, letterSpacing: -1.6)),
              const SizedBox(height: 14),
              Text('One place for members, attendance, payments and progress.', style: GoogleFonts.poppins(color: t.textSecondary, fontSize: 14, height: 1.5)),
              const SizedBox(height: 34),
              FireButton(label: 'Login', icon: Icons.login_rounded, onPressed: _showLoginSheet),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                height: 54,
                child: OutlinedButton.icon(
                  onPressed: _showRegisterSheet,
                  icon: const Icon(Icons.storefront_rounded),
                  label: const Text('Register New Business'),
                  style: OutlinedButton.styleFrom(foregroundColor: t.text, side: BorderSide(color: t.borderStrong), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16))),
                ),
              ),
              const SizedBox(height: 24),
              Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                Icon(Icons.shield_outlined, color: t.textTertiary, size: 15),
                const SizedBox(width: 6),
                Text('Built for modern gyms', style: GoogleFonts.poppins(color: t.textTertiary, fontSize: 11.5)),
              ]),
            ]),
          ),
        ),
      ]),
    );
  }

  Widget _glow(double size, Color color) => Container(width: size, height: size, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [color, color.withOpacity(0)])));
}
