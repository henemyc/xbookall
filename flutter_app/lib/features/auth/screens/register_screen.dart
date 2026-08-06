import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/widgets/ui.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});
  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _businessCtrl = TextEditingController();
  final _nameCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  final _otpCtrl = TextEditingController();

  bool _phoneVerified = false;
  bool _otpSent = false;
  bool _otpSending = false;
  bool _obscure = true;
  int _step = 1;
  int _otpRequests = 0;
  int _otpCooldown = 0;
  Timer? _cooldownTimer;

  @override
  void dispose() {
    _cooldownTimer?.cancel();
    _businessCtrl.dispose();
    _nameCtrl.dispose();
    _emailCtrl.dispose();
    _phoneCtrl.dispose();
    _passCtrl.dispose();
    _otpCtrl.dispose();
    super.dispose();
  }

  void _startCooldown() {
    _otpCooldown = 120;
    _otpRequests++;
    _cooldownTimer?.cancel();
    _cooldownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      setState(() {
        _otpCooldown--;
        if (_otpCooldown <= 0) {
          timer.cancel();
          _otpCooldown = 0;
        }
      });
    });
  }

  String _formatCooldown() {
    final m = (_otpCooldown ~/ 60).toString().padLeft(2, '0');
    final s = (_otpCooldown % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => _step == 2 ? setState(() => _step = 1) : Navigator.pop(context),
        ),
        title: const Text('Create Gym Account'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _stepper(),
            const SizedBox(height: 24),
            _step == 1 ? _buildStep1() : _buildStep2(auth),
          ],
        ),
      ),
    );
  }

  Widget _stepper() {
    return Row(children: [
      _stepDot(1, 'Gym'),
      Expanded(
        child: Container(
          height: 3,
          margin: const EdgeInsets.symmetric(horizontal: 8),
          decoration: BoxDecoration(
            gradient: _step == 2 ? AppTheme.fireGradient : null,
            color: _step == 2 ? null : context.tokens.border,
            borderRadius: BorderRadius.circular(4),
          ),
        ),
      ),
      _stepDot(2, 'Verify & Create'),
    ]);
  }

  Widget _stepDot(int n, String label) {
    final active = _step >= n;
    return Column(children: [
      Container(
        width: 34,
        height: 34,
        decoration: BoxDecoration(
          gradient: active ? AppTheme.fireGradient : null,
          color: active ? null : context.tokens.surfaceAlt,
          shape: BoxShape.circle,
          border: Border.all(color: active ? Colors.transparent : context.tokens.border),
        ),
        alignment: Alignment.center,
        child: _step > n
            ? const Icon(Icons.check_rounded, size: 18, color: Colors.white)
            : Text('$n', style: TextStyle(color: active ? Colors.white : context.tokens.textTertiary, fontWeight: FontWeight.w700)),
      ),
      const SizedBox(height: 5),
      Text(label, style: context.typo.labelSmall?.copyWith(color: active ? AppTheme.brand : context.tokens.textTertiary)),
    ]);
  }

  Widget _buildStep1() {
    return FadeInUp(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Center(child: IconBadge(Icons.storefront_rounded, color: AppTheme.brand, size: 68, iconSize: 34)),
        const SizedBox(height: 16),
        Center(child: Text('Your Gym Details', style: context.typo.headlineSmall)),
        const SizedBox(height: 4),
        Center(child: Text('Start your 7-day free trial', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary))),
        const SizedBox(height: 24),
        _label('Business / Gym Name'),
        const SizedBox(height: 7),
        TextField(
          controller: _businessCtrl,
          decoration: const InputDecoration(prefixIcon: Icon(Icons.store_rounded), hintText: 'e.g. Iron Paradise Gym'),
        ),
        const SizedBox(height: 24),
        FireButton(
          label: 'Continue',
          icon: Icons.arrow_forward_rounded,
          onPressed: () {
            if (_businessCtrl.text.trim().isEmpty) {
              Toast.error(context, 'Business name required');
              return;
            }
            setState(() => _step = 2);
          },
        ),
      ]),
    );
  }

  Widget _buildStep2(dynamic _) {
    final state = ref.watch(authProvider);
    final cleanPhone = _digitsOnly(_phoneCtrl.text);
    final canSendOtp = cleanPhone.length == 10 && !_otpSending && _otpCooldown == 0 && _otpRequests < 3;
    final otpLimitReached = _otpRequests >= 3;

    return FadeInUp(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _label('Full Name'),
          const SizedBox(height: 7),
          TextField(controller: _nameCtrl, decoration: const InputDecoration(prefixIcon: Icon(Icons.person_outline_rounded), hintText: 'Your full name')),

          const SizedBox(height: 16),
          _label('Phone Number (for WhatsApp OTP verification)'),
          const SizedBox(height: 7),
          TextField(
            controller: _phoneCtrl,
            keyboardType: TextInputType.phone,
            maxLength: 10,
            inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            enabled: !_phoneVerified,
            onChanged: (_) => setState(() {
              _otpSent = false;
              _otpCtrl.clear();
            }),
            decoration: InputDecoration(
              prefixIcon: const Icon(Icons.phone_rounded),
              hintText: '10 digits (6-9 start)',
              suffixIcon: _phoneVerified
                  ? const Icon(Icons.verified_rounded, color: AppTheme.success)
                  : null,
            ),
          ),

          if (!_phoneVerified) ...[
            if (otpLimitReached)
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.10), borderRadius: BorderRadius.circular(12)),
                child: Row(children: [
                  const Icon(Icons.block_rounded, color: AppTheme.danger, size: 18),
                  const SizedBox(width: 8),
                  Expanded(child: Text('OTP limit reached (3/3). Please try again later.', style: context.typo.bodySmall?.copyWith(color: AppTheme.danger, fontWeight: FontWeight.w600))),
                ]),
              )
            else
              FireButton(
                label: _otpSending
                    ? 'Sending OTP...'
                    : _otpCooldown > 0
                        ? 'Resend in ${_formatCooldown()}'
                        : _otpSent
                            ? 'Resend OTP (${3 - _otpRequests} left)'
                            : 'Check & Send OTP',
                icon: _otpSending ? null : Icons.chat_rounded,
                gradient: AppTheme.amberGradient,
                loading: _otpSending,
                onPressed: canSendOtp ? _sendOtp : null,
              ),

            if (_otpSent && !_phoneVerified) ...[
              const SizedBox(height: 14),
              TextField(
                controller: _otpCtrl,
                decoration: const InputDecoration(hintText: 'Enter 6-digit OTP received on WhatsApp'),
                keyboardType: TextInputType.number,
                maxLength: 6,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              ),
              const SizedBox(height: 4),
              FireButton(label: 'Verify OTP', onPressed: _verifyOtp),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                decoration: BoxDecoration(
                  color: Colors.blue.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.content_copy_rounded, size: 15, color: Colors.blue),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        'Tip: Tap the "Copy Code" button in WhatsApp to copy the OTP automatically.',
                        style: context.typo.bodySmall?.copyWith(color: Colors.blue.shade700, fontSize: 12),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],

          if (_phoneVerified)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: AppTheme.success.withOpacity(0.10),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppTheme.success.withOpacity(0.2)),
              ),
              child: Row(children: [
                const Icon(Icons.verified_user_rounded, color: AppTheme.success, size: 18),
                const SizedBox(width: 8),
                Expanded(child: Text('Phone number verified via WhatsApp', style: context.typo.bodySmall?.copyWith(color: AppTheme.success, fontWeight: FontWeight.w700))),
              ]),
            ),

          const SizedBox(height: 16),
          _label('Email Address'),
          const SizedBox(height: 7),
          TextField(
            controller: _emailCtrl,
            decoration: const InputDecoration(prefixIcon: Icon(Icons.email_outlined), hintText: 'you@gym.com'),
            keyboardType: TextInputType.emailAddress,
          ),

          const SizedBox(height: 16),
          _label('Password'),
          const SizedBox(height: 7),
          TextField(
            controller: _passCtrl,
            obscureText: _obscure,
            decoration: InputDecoration(
              prefixIcon: const Icon(Icons.lock_outline_rounded),
              hintText: 'Min 6 characters',
              suffixIcon: IconButton(
                icon: Icon(_obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded, size: 20),
                onPressed: () => setState(() => _obscure = !_obscure),
              ),
            ),
          ),

          const SizedBox(height: 20),

          if (state.error != null)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.10), borderRadius: BorderRadius.circular(12)),
              child: Text(state.error!, style: context.typo.bodySmall?.copyWith(color: AppTheme.danger, fontWeight: FontWeight.w600)),
            ),

          const SizedBox(height: 8),

          FireButton(
            label: 'Create Gym Account',
            loading: state.isLoading,
            onPressed: (!_phoneVerified || state.isLoading) ? null : _register,
          ),

          if (!_phoneVerified)
            Padding(
              padding: const EdgeInsets.only(top: 10),
              child: Center(
                child: Text(
                  'We check if this phone is already registered before sending OTP',
                  style: context.typo.bodySmall?.copyWith(color: AppTheme.warning, fontSize: 11.5),
                  textAlign: TextAlign.center,
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _label(String s) => Text(s, style: context.typo.labelMedium?.copyWith(color: context.tokens.textSecondary, fontWeight: FontWeight.w600));

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

  Future<void> _sendOtp() async {
    final phone = _digitsOnly(_phoneCtrl.text);
    if (phone.length != 10) {
      Toast.error(context, 'Enter a valid 10-digit phone number');
      return;
    }

    setState(() => _otpSending = true);

    try {
      // PWA-aligned flow: always call sendOtp, ignore any phone in responses for toasts.
      // Force clean, user-friendly message. Never echo phone number.
      await ref.read(authProvider.notifier).sendOtp(phone);

      setState(() {
        _otpSent = true;
        _otpSending = false;
      });
      _startCooldown();

      // STRICT CLEAN FLOW: Never echo phone. Always show fixed user-friendly message.
      // Even if backend returns phone or raw data, we ignore it here.
      Toast.success(context, 'OTP sent to WhatsApp');
      HapticFeedback.lightImpact();

      // Optional: log for debugging (remove in prod)
      // print('OTP response: $res');
    } catch (e) {
      setState(() => _otpSending = false);
      var msg = _apiError(e, fallback: 'Failed to send OTP. Please try again.');
      // Never echo the actual phone number back into the UI.
      if (msg.contains(phone)) {
        msg = msg.replaceAll(phone, 'this number');
      }
      Toast.error(context, msg);
    }
  }

  Future<void> _verifyOtp() async {
    final phone = _digitsOnly(_phoneCtrl.text);
    final otp = _otpCtrl.text.trim();

    if (otp.length != 6) {
      Toast.error(context, 'Please enter 6-digit OTP');
      return;
    }

    try {
      await ref.read(authProvider.notifier).verifyOtp(phone, otp);
      setState(() => _phoneVerified = true);
      HapticFeedback.heavyImpact();
      Toast.success(context, 'Phone verified successfully via WhatsApp');
    } catch (e) {
      Toast.error(context, _apiError(e, fallback: 'Invalid OTP. Please try again.'));
    }
  }

  Future<void> _register() async {
    if (_emailCtrl.text.trim().isEmpty || !_emailCtrl.text.trim().contains('@')) {
      Toast.error(context, 'Valid email is required');
      return;
    }
    final phone = _digitsOnly(_phoneCtrl.text);
    if (phone.length != 10) {
      Toast.error(context, 'Phone must be 10 digits');
      return;
    }
    if (_passCtrl.text.trim().length < 6) {
      Toast.error(context, 'Password must be at least 6 characters');
      return;
    }

    final success = await ref.read(authProvider.notifier).register(
          businessName: _businessCtrl.text.trim(),
          name: _nameCtrl.text.trim(),
          email: _emailCtrl.text.trim(),
          phone: phone,
          password: _passCtrl.text.trim(),
        );

    if (success && mounted) {
      Toast.success(context, 'Gym account created! Welcome to GymXBook.');
      // RegisterScreen was pushed over LoginScreen. Pop back to the root so
      // AuthWrapper can reveal MainShell immediately after auto-login.
      Navigator.of(context).popUntil((route) => route.isFirst);
    } else if (mounted) {
      final msg = ref.read(authProvider).error ?? 'Could not create gym account. Please try again.';
      Toast.error(context, msg);
    }
  }
}
