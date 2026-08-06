import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/screens/register_screen.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _emailCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  bool _obscure = true;

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final t = context.tokens;
    return Scaffold(
      body: Stack(
        children: [
          Container(
            height: MediaQuery.of(context).size.height * 0.44,
            decoration: const BoxDecoration(gradient: AppTheme.darkHeroGradient),
          ),
          Positioned(top: -60, right: -40, child: _glow(220, AppTheme.brand.withOpacity(0.5))),
          Positioned(top: 60, left: -70, child: _glow(180, AppTheme.brandDeep.withOpacity(0.35))),
          SafeArea(
            child: SingleChildScrollView(
              physics: const BouncingScrollPhysics(),
              child: Column(
                children: [
                  const SizedBox(height: 44),
                  FadeInUp(
                    child: Column(children: [
                      Container(
                        width: 78, height: 78,
                        decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(24), boxShadow: [BoxShadow(color: AppTheme.brand.withOpacity(0.5), blurRadius: 30, spreadRadius: 2)]),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(24),
                          child: Padding(
                            padding: const EdgeInsets.all(10),
                            child: Image.asset('assets/images/gymxbook_foreground_logo.png', fit: BoxFit.contain),
                          ),
                        ),
                      ),
                      const SizedBox(height: 18),
                      Text('GymXBook', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w700, letterSpacing: -0.5)),
                      const SizedBox(height: 4),
                      Text('Manage your gym like a pro', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.65), fontSize: 13.5, fontWeight: FontWeight.w500)),
                    ]),
                  ),
                  const SizedBox(height: 30),
                  FadeInUp(
                    delayMs: 120,
                    child: Container(
                      margin: const EdgeInsets.symmetric(horizontal: 18),
                      padding: const EdgeInsets.fromLTRB(22, 26, 22, 24),
                      decoration: BoxDecoration(
                        color: t.surface,
                        borderRadius: BorderRadius.circular(28),
                        border: Border.all(color: t.border),
                        boxShadow: context.softShadow,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Welcome back', style: context.typo.headlineSmall),
                          const SizedBox(height: 4),
                          Text('Sign in to continue to your dashboard', style: context.typo.bodySmall?.copyWith(color: t.textTertiary)),
                          const SizedBox(height: 22),
                          _fieldLabel('Phone Number'),
                          const SizedBox(height: 7),
                          TextField(
                            controller: _emailCtrl,
                            keyboardType: TextInputType.phone,
                            inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(10)],
                            decoration: const InputDecoration(prefixIcon: Icon(Icons.phone_rounded), hintText: '10-digit mobile number'),
                          ),
                          const SizedBox(height: 16),
                          _fieldLabel('Password'),
                          const SizedBox(height: 7),
                          TextField(
                            controller: _passCtrl,
                            obscureText: _obscure,
                            decoration: InputDecoration(
                              prefixIcon: const Icon(Icons.lock_outline_rounded),
                              hintText: '••••••••',
                              suffixIcon: IconButton(icon: Icon(_obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded, size: 20), onPressed: () => setState(() => _obscure = !_obscure)),
                            ),
                          ),
                          const SizedBox(height: 12),
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton(onPressed: () => _showForgotSheet(context), style: TextButton.styleFrom(padding: EdgeInsets.zero, minimumSize: const Size(0, 0)), child: const Text('Forgot Password?')),
                          ),
                          if (auth.error != null) ...[
                            const SizedBox(height: 16),
                            Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.10), borderRadius: BorderRadius.circular(12), border: Border.all(color: AppTheme.danger.withOpacity(0.2))),
                              child: Row(children: [const Icon(Icons.error_outline_rounded, color: AppTheme.danger, size: 18), const SizedBox(width: 8), Expanded(child: Text(auth.error!, style: context.typo.bodySmall?.copyWith(color: AppTheme.danger, fontWeight: FontWeight.w600)))]),
                            ),
                          ],
                          const SizedBox(height: 22),
                          FireButton(label: 'Sign In', icon: Icons.arrow_forward_rounded, loading: auth.isLoading, onPressed: auth.isLoading ? null : _login),
                          const SizedBox(height: 18),
                          Center(
                            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                              Text("Don't have an account? ", style: context.typo.bodySmall),
                              GestureDetector(onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RegisterScreen())), child: Text('Register', style: context.typo.bodySmall?.copyWith(color: AppTheme.brand, fontWeight: FontWeight.w700))),
                            ]),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 18),
                    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.shield_outlined, size: 14, color: t.textTertiary),
                      const SizedBox(width: 6),
                      Text('Secured with end-to-end encryption', style: context.typo.bodySmall?.copyWith(color: t.textTertiary, fontSize: 11.5)),
                    ]),
                  ),
                  const SizedBox(height: 24),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _glow(double size, Color color) => Container(width: size, height: size, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [color, color.withOpacity(0)])));
  Widget _fieldLabel(String s) => Text(s, style: context.typo.labelMedium?.copyWith(color: context.tokens.textSecondary, fontWeight: FontWeight.w600));

  String _digitsOnly(String value) => value.replaceAll(RegExp(r'[^0-9]'), '');

  String _apiError(dynamic e, {String fallback = 'Something went wrong. Please try again.'}) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) {
        final msg = data['error'] ?? data['message'];
        if (msg != null && msg.toString().trim().isNotEmpty) return msg.toString();
        if (data['errors'] is Map) {
          final errors = data['errors'] as Map;
          if (errors.isNotEmpty) {
            final first = errors.values.first;
            if (first is List && first.isNotEmpty) return first.first.toString();
            return first.toString();
          }
        }
      }
      final text = e.toString();
      if (text.contains('connection') || text.contains('SocketException')) {
        return 'No internet connection. Please check your network.';
      }
    } catch (_) {}
    return fallback;
  }

  Future<void> _login() async {
    final phone = _digitsOnly(_emailCtrl.text.trim());
    final pass = _passCtrl.text.trim();
    if (phone.isEmpty || pass.isEmpty) {
      Toast.error(context, 'Phone and password required');
      return;
    }
    if (phone.length != 10 || !RegExp(r'^[6-9]').hasMatch(phone)) {
      Toast.error(context, 'Enter valid 10-digit phone number');
      return;
    }
    final success = await ref.read(authProvider.notifier).login(email: phone, password: pass);
    if (success && mounted) {
      Toast.success(context, 'Welcome back!');
    } else {
      final currentError = ref.read(authProvider).error;
      if (currentError != null && mounted) {
        Toast.error(context, currentError);
      }
    }
  }

  void _showForgotSheet(BuildContext context) {
    final phoneCtrl = TextEditingController();
    final otpCtrl = TextEditingController();
    final newPassCtrl = TextEditingController();
    final confirmCtrl = TextEditingController();
    bool otpSent = false;
    bool otpSending = false;
    bool otpVerified = false;
    int otpRequests = 0;
    int otpCooldown = 0;
    Timer? cooldownTimer;

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      void startCooldown() {
        otpCooldown = 120;
        otpRequests++;
        cooldownTimer?.cancel();
        cooldownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
          setSheet(() {
            otpCooldown--;
            if (otpCooldown <= 0) { timer.cancel(); otpCooldown = 0; }
          });
        });
      }

      String formatCooldown() {
        final m = (otpCooldown ~/ 60).toString().padLeft(2, '0');
        final s = (otpCooldown % 60).toString().padLeft(2, '0');
        return '$m:$s';
      }

      final cleanPhone = _digitsOnly(phoneCtrl.text);
      final canSendOtp = cleanPhone.length == 10 && !otpSending && otpCooldown == 0 && otpRequests < 3;
      final otpLimitReached = otpRequests >= 3;

      return SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(children: [
              IconBadge(Icons.lock_reset_rounded, color: AppTheme.brand),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Reset Password', style: context.typo.titleLarge),
                Text('Verify via WhatsApp OTP', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
              ])),
            ]),
            const SizedBox(height: 20),
            if (!otpVerified) ...[
              TextField(
                controller: phoneCtrl,
                decoration: const InputDecoration(hintText: '10-digit phone (6-9 start)', prefixIcon: Icon(Icons.phone_rounded)),
                keyboardType: TextInputType.phone,
                maxLength: 10,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                onChanged: (_) => setSheet(() {}),
              ),
              const SizedBox(height: 6),
              if (otpLimitReached)
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.10), borderRadius: BorderRadius.circular(12)),
                  child: Row(children: [
                    const Icon(Icons.block_rounded, color: AppTheme.danger, size: 18),
                    const SizedBox(width: 8),
                    Text('OTP limit reached (3/3). Try again later.', style: context.typo.bodySmall?.copyWith(color: AppTheme.danger, fontWeight: FontWeight.w600)),
                  ]),
                )
              else
                FireButton(
                  label: otpSending
                      ? 'Sending OTP...'
                      : otpCooldown > 0
                          ? 'Resend in ${formatCooldown()}'
                          : otpSent
                              ? 'Resend OTP (${3 - otpRequests} left)'
                              : 'Send OTP to WhatsApp',
                  icon: otpSending ? null : Icons.chat_rounded,
                  loading: otpSending,
                  onPressed: canSendOtp ? () async {
                    setSheet(() => otpSending = true);
                    try {
                      await ref.read(authProvider.notifier).forgotSendOtp(cleanPhone);
                      setSheet(() { otpSent = true; otpSending = false; });
                      startCooldown();
                      // Clean toast - never echo phone or raw response
                      Toast.success(ctx, 'OTP sent to WhatsApp');
                    } catch (e) {
                      setSheet(() => otpSending = false);
                      Toast.error(ctx, _apiError(e, fallback: 'Failed to send OTP. Please try again.'));
                    }
                  } : null,
                ),
              if (otpSent) ...[
                const SizedBox(height: 14),
                TextField(
                  controller: otpCtrl,
                  decoration: const InputDecoration(hintText: 'Enter 6-digit OTP'),
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                ),
                const SizedBox(height: 6),
                FireButton(label: 'Verify OTP', onPressed: () async {
                  final otp = otpCtrl.text.trim();
                  if (otp.length != 6) {
                    Toast.error(ctx, 'Please enter the 6-digit OTP');
                    return;
                  }
                  try {
                    await ref.read(authProvider.notifier).forgotVerifyOtp(cleanPhone, otp);
                    setSheet(() => otpVerified = true);
                    Toast.success(ctx, 'OTP verified. Set your new password.');
                  } catch (e) { Toast.error(ctx, _apiError(e, fallback: 'Invalid OTP. Please try again.')); }
                }),
              ],
            ] else ...[
              TextField(controller: newPassCtrl, decoration: const InputDecoration(hintText: 'New Password (min 6)', prefixIcon: Icon(Icons.lock_rounded)), obscureText: true),
              const SizedBox(height: 12),
              TextField(controller: confirmCtrl, decoration: const InputDecoration(hintText: 'Confirm Password', prefixIcon: Icon(Icons.lock_outline_rounded)), obscureText: true),
              const SizedBox(height: 16),
              FireButton(label: 'Reset Password', onPressed: () async {
                final newPass = newPassCtrl.text.trim();
                final confirm = confirmCtrl.text.trim();
                if (newPass.length < 6) {
                  Toast.error(ctx, 'Password must be at least 6 characters');
                  return;
                }
                if (newPass != confirm) {
                  Toast.error(ctx, 'Passwords do not match');
                  return;
                }
                try {
                  await ref.read(authProvider.notifier).forgotReset(cleanPhone, otpCtrl.text.trim(), newPass, confirm);
                  if (mounted) Navigator.pop(ctx);
                  Toast.success(context, 'Password reset successful. Please login now.');
                } catch (e) { Toast.error(ctx, _apiError(e, fallback: 'Could not reset password. Please try again.')); }
              }),
            ],
          ],
        ),
      );
    })).whenComplete(() => cooldownTimer?.cancel());
  }
}
