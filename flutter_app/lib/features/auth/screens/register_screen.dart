import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key});

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _gymCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _otpCtrl = TextEditingController();
  final _nameCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _cityCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  final _sourceDetailCtrl = TextEditingController();
  int _step = 0;
  bool _otpSent = false;
  bool _verified = false;
  bool _sending = false;
  bool _obscure = true;
  int _cooldown = 0;
  Timer? _timer;
  String? _source;

  static const _sources = [
    ('google_search', 'Google Search', Icons.travel_explore_rounded),
    ('play_store', 'Google Play Store', Icons.play_circle_outline_rounded),
    ('social_media', 'Instagram / Facebook', Icons.share_rounded),
    ('youtube', 'YouTube', Icons.ondemand_video_rounded),
    ('chatgpt_ai', 'ChatGPT / AI', Icons.auto_awesome_rounded),
    ('referral', 'Friend / Gym Owner Referral', Icons.people_alt_rounded),
    ('sales_team', 'GymXBook Team / Sales Person', Icons.support_agent_rounded),
    ('other', 'Other', Icons.more_horiz_rounded),
  ];

  @override
  void dispose() {
    _timer?.cancel();
    for (final controller in [_gymCtrl, _phoneCtrl, _otpCtrl, _nameCtrl, _addressCtrl, _cityCtrl, _passwordCtrl, _sourceDetailCtrl]) {
      controller.dispose();
    }
    super.dispose();
  }

  String get _phone => _phoneCtrl.text.replaceAll(RegExp(r'[^0-9]'), '');

  void _startCooldown() {
    _cooldown = 120;
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return timer.cancel();
      setState(() {
        _cooldown--;
        if (_cooldown <= 0) {
          _cooldown = 0;
          timer.cancel();
        }
      });
    });
  }

  Future<void> _sendOtp() async {
    if (!RegExp(r'^[6-9]\d{9}$').hasMatch(_phone)) {
      Toast.error(context, 'Enter a valid 10-digit phone number');
      return;
    }
    setState(() => _sending = true);
    try {
      await ref.read(authProvider.notifier).sendOtp(_phone);
      if (!mounted) return;
      setState(() { _otpSent = true; _sending = false; });
      _startCooldown();
      Toast.success(context, 'OTP sent to WhatsApp');
    } catch (e) {
      if (mounted) setState(() => _sending = false);
      if (mounted) Toast.error(context, _apiError(e, 'Could not send OTP'));
    }
  }

  Future<void> _verifyOtp() async {
    if (!RegExp(r'^\d{6}$').hasMatch(_otpCtrl.text.trim())) {
      Toast.error(context, 'Enter the 6-digit OTP');
      return;
    }
    try {
      await ref.read(authProvider.notifier).verifyOtp(_phone, _otpCtrl.text.trim());
      if (!mounted) return;
      setState(() { _verified = true; _step = 2; });
      Toast.success(context, 'Phone verified successfully');
    } catch (e) {
      Toast.error(context, _apiError(e, 'Invalid OTP'));
    }
  }

  Future<void> _register() async {
    if (_source == null) { Toast.error(context, 'Select how you discovered GymXBook'); return; }
    final ok = await ref.read(authProvider.notifier).register(
      businessName: _gymCtrl.text.trim(),
      name: _nameCtrl.text.trim(),
      phone: _phone,
      password: _passwordCtrl.text.trim(),
      address: _addressCtrl.text.trim(),
      city: _cityCtrl.text.trim(),
      acquisitionSource: _source!,
      acquisitionDetail: _sourceDetailCtrl.text.trim(),
    );
    if (!mounted) return;
    if (ok) {
      Toast.success(context, 'Your gym is ready!');
      Navigator.of(context).popUntil((route) => route.isFirst);
    } else {
      Toast.error(context, ref.read(authProvider).error ?? 'Could not create account');
    }
  }

  String _apiError(Object error, String fallback) {
    try {
      final data = (error as dynamic).response?.data;
      if (data is Map) return (data['error'] ?? data['message'] ?? fallback).toString();
    } catch (_) {}
    return fallback;
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(onPressed: () => _step == 0 ? Navigator.pop(context) : setState(() => _step--), icon: const Icon(Icons.arrow_back_rounded)),
        title: const Text('Register New Business'),
      ),
      body: SafeArea(
        child: Column(children: [
          _progress(),
          Expanded(child: SingleChildScrollView(padding: const EdgeInsets.fromLTRB(22, 22, 22, 28), child: _body(auth))),
        ]),
      ),
    );
  }

  Widget _progress() => Padding(
        padding: const EdgeInsets.fromLTRB(22, 10, 22, 0),
        child: Row(
          children: List.generate(
            4,
            (index) => Expanded(
              child: Container(
                height: 5,
                margin: EdgeInsets.only(right: index == 3 ? 0 : 7),
                decoration: BoxDecoration(
                  color: index <= _step ? AppTheme.brand : context.tokens.border,
                  borderRadius: BorderRadius.circular(9),
                ),
              ),
            ),
          ),
        ),
      );

  Widget _body(dynamic auth) {
    switch (_step) {
      case 0: return _gymStep();
      case 1: return _phoneStep();
      case 2: return _ownerStep();
      default: return _sourceStep(auth);
    }
  }

  Widget _heading(String title, String subtitle) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: context.typo.headlineSmall), const SizedBox(height: 6), Text(subtitle, style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary))]);

  Widget _gymStep() => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    _heading('Tell us about your gym', 'Start your GymXBook setup in a few simple steps.'),
    const SizedBox(height: 28),
    TextField(controller: _gymCtrl, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Gym / Business Name', prefixIcon: Icon(Icons.storefront_rounded), hintText: 'e.g. Nexa Fitness')),
    const SizedBox(height: 24),
    FireButton(label: 'Continue', icon: Icons.arrow_forward_rounded, onPressed: () { if (_gymCtrl.text.trim().isEmpty) { Toast.error(context, 'Gym name is required'); return; } setState(() => _step = 1); }),
  ]);

  Widget _phoneStep() => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    _heading('Verify your phone', 'We will send a secure OTP to your WhatsApp.'),
    const SizedBox(height: 28),
    TextField(controller: _phoneCtrl, enabled: !_verified, keyboardType: TextInputType.phone, inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(10)], decoration: InputDecoration(labelText: 'Phone Number', prefixIcon: const Icon(Icons.phone_rounded), suffixIcon: _verified ? const Icon(Icons.verified_rounded, color: AppTheme.success) : null)),
    const SizedBox(height: 14),
    if (!_verified) FireButton(label: _otpSent ? (_cooldown > 0 ? 'Resend in ${(_cooldown ~/ 60).toString().padLeft(2, '0')}:${(_cooldown % 60).toString().padLeft(2, '0')}' : 'Resend OTP') : 'Send OTP to WhatsApp', icon: Icons.chat_rounded, loading: _sending, onPressed: _sending || _cooldown > 0 ? null : _sendOtp),
    if (_otpSent && !_verified) ...[
      const SizedBox(height: 18),
      TextField(controller: _otpCtrl, keyboardType: TextInputType.number, inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(6)], decoration: const InputDecoration(labelText: '6-digit OTP', prefixIcon: Icon(Icons.password_rounded))),
      const SizedBox(height: 14),
      FireButton(label: 'Verify OTP', icon: Icons.verified_rounded, onPressed: _verifyOtp),
    ],
    if (_verified) ...[const SizedBox(height: 22), FireButton(label: 'Continue', icon: Icons.arrow_forward_rounded, onPressed: () => setState(() => _step = 2))],
  ]);

  Widget _ownerStep() => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    _heading('Create your account', 'Tell us who will manage this gym.'),
    const SizedBox(height: 24),
    TextField(controller: _nameCtrl, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Your Full Name', prefixIcon: Icon(Icons.person_outline_rounded))),
    const SizedBox(height: 14),
    TextField(controller: _addressCtrl, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Address', prefixIcon: Icon(Icons.location_on_outlined))),
    const SizedBox(height: 14),
    TextField(controller: _cityCtrl, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'City', prefixIcon: Icon(Icons.location_city_rounded))),
    const SizedBox(height: 14),
    TextField(controller: _passwordCtrl, obscureText: _obscure, decoration: InputDecoration(labelText: 'Create Password', helperText: 'Minimum 6 characters', prefixIcon: const Icon(Icons.lock_outline_rounded), suffixIcon: IconButton(onPressed: () => setState(() => _obscure = !_obscure), icon: Icon(_obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded)))),
    const SizedBox(height: 24),
    FireButton(label: 'Continue', icon: Icons.arrow_forward_rounded, onPressed: () { if (_nameCtrl.text.trim().isEmpty) { Toast.error(context, 'Your name is required'); return; } if (_passwordCtrl.text.trim().length < 6) { Toast.error(context, 'Password must be at least 6 characters'); return; } setState(() => _step = 3); }),
  ]);

  Widget _sourceStep(dynamic auth) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _heading('How did you find GymXBook?', 'This helps us improve the ways we reach gym owners.'),
          const SizedBox(height: 20),
          ..._sources.map(
            (item) => Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: Pressable(
                radius: 16,
                onTap: () => setState(() => _source = item.$1),
                child: Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: _source == item.$1 ? AppTheme.brand.withOpacity(.10) : context.tokens.surfaceAlt,
                    border: Border.all(color: _source == item.$1 ? AppTheme.brand : Colors.transparent),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Row(
                    children: [
                      Icon(item.$3, color: _source == item.$1 ? AppTheme.brand : context.tokens.textSecondary),
                      const SizedBox(width: 12),
                      Expanded(child: Text(item.$2, style: context.typo.titleSmall)),
                      if (_source == item.$1) const Icon(Icons.check_circle_rounded, color: AppTheme.brand),
                    ],
                  ),
                ),
              ),
            ),
          ),
          if (_source == 'referral' || _source == 'sales_team' || _source == 'other') ...[
            const SizedBox(height: 8),
            TextField(
              controller: _sourceDetailCtrl,
              decoration: InputDecoration(
                labelText: _source == 'referral'
                    ? 'Who referred you? (optional)'
                    : _source == 'sales_team'
                        ? 'Sales person name or code (optional)'
                        : 'Tell us more (optional)',
              ),
            ),
          ],
          const SizedBox(height: 22),
          FireButton(
            label: 'Create My Gym Account',
            icon: Icons.rocket_launch_rounded,
            loading: auth.isLoading,
            onPressed: auth.isLoading ? null : _register,
          ),
        ],
      );
}
