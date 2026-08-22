import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:gymxbook/core/theme/app_theme.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/screens/auth_landing_screen.dart';

class AuthOnboardingGate extends StatefulWidget {
  const AuthOnboardingGate({super.key});

  @override
  State<AuthOnboardingGate> createState() => _AuthOnboardingGateState();
}

class _AuthOnboardingGateState extends State<AuthOnboardingGate> {
  // Bumped to v2 so the redesigned onboarding shows again for existing installs.
  static const _seenKey = 'has_seen_auth_onboarding_v2';
  final _controller = PageController();
  bool _checking = true;
  bool _showLanding = false;
  int _page = 0;

  final _pages = const [
    _OnboardingPage(
      image: 'assets/images/onboarding_welcome.png',
      tag: null,
      title: 'Built for everyone\nat your gym',
      subtitle: 'One app that keeps gym owners, members, trainers and staff in perfect sync — every day.',
      audience: ['Gym Owners', 'Members', 'Trainers', 'Staff'],
      feature: null,
    ),
    _OnboardingPage(
      image: 'assets/images/onboarding_qr.png',
      tag: 'QR Attendance',
      title: 'Check-ins in\none scan',
      subtitle: 'Members scan the gym QR and attendance logs itself — with live in and out times.',
      audience: null,
      feature: 'Members scan · time auto-logged · no paper registers',
    ),
    _OnboardingPage(
      image: 'assets/images/onboarding_reminder.png',
      tag: 'Auto Reminders',
      title: 'Reminders that\nnever sleep',
      subtitle: 'Automatic WhatsApp reminders for renewals, expiring plans and due payments.',
      audience: null,
      feature: 'Renewals · expiring plans · payment nudges',
    ),
    _OnboardingPage(
      image: 'assets/images/onboarding_reports.png',
      tag: 'Detailed Reports',
      title: 'Know exactly how\nyour gym performs',
      subtitle: 'Revenue, expenses, attendance and growth — clear reports at a glance.',
      audience: null,
      feature: 'Revenue · expenses · attendance · growth',
    ),
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    if (!mounted) return;
    final seen = prefs.getBool(_seenKey) ?? false;
    setState(() {
      _checking = false;
      _showLanding = seen;
    });
  }

  Future<void> _finish() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_seenKey, true);
    if (mounted) setState(() => _showLanding = true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_checking) return Scaffold(body: Center(child: CircularProgressIndicator(color: AppTheme.brand)));
    if (_showLanding) return const AuthLandingScreen();

    final t = context.tokens;
    final isLast = _page == _pages.length - 1;
    final page = _pages[_page];

    return Scaffold(
      backgroundColor: t.bg,
      body: Stack(children: [
        // Soft brand glows behind everything (matches the login screen).
        Positioned(top: -70, right: -50, child: _glow(230, AppTheme.brand.withOpacity(.28))),
        Positioned(bottom: -90, left: -60, child: _glow(240, AppTheme.brandDeep.withOpacity(.20))),
        SafeArea(
          child: Column(children: [
            // Top bar — brand mark + skip
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 6, 12, 0),
              child: Row(children: [
                Container(
                  width: 30, height: 30,
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(10)),
                  child: Image.asset('assets/images/gymxbook_foreground_logo.png', fit: BoxFit.contain),
                ),
                const SizedBox(width: 8),
                Text('GymXBook', style: GoogleFonts.spaceGrotesk(fontSize: 16, fontWeight: FontWeight.w800, color: t.text, letterSpacing: -.3)),
                const Spacer(),
                if (!isLast)
                  TextButton(onPressed: _finish, child: Text('Skip', style: context.typo.labelLarge?.copyWith(color: t.textSecondary))),
              ]),
            ),
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: _pages.length,
                onPageChanged: (value) => setState(() => _page = value),
                itemBuilder: (_, index) => _pageView(index),
              ),
            ),
            // Progress indicator
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(_pages.length, (index) {
                final active = index == _page;
                return AnimatedContainer(
                  duration: const Duration(milliseconds: 240),
                  curve: Curves.easeOutCubic,
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  width: active ? 26 : 8,
                  height: 8,
                  decoration: BoxDecoration(
                    gradient: active ? AppTheme.fireGradient : null,
                    color: active ? null : t.borderStrong,
                    borderRadius: BorderRadius.circular(10),
                  ),
                );
              }),
            ),
            const SizedBox(height: 22),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 26),
              child: FireButton(
                label: isLast ? 'Get Started' : 'Continue',
                icon: Icons.arrow_forward_rounded,
                onPressed: () {
                  if (isLast) {
                    _finish();
                  } else {
                    _controller.nextPage(duration: const Duration(milliseconds: 380), curve: Curves.easeOutCubic);
                  }
                },
              ),
            ),
          ]),
        ),
      ]),
    );
  }

  Widget _pageView(int index) {
    final page = _pages[index];
    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 8),
      child: Column(children: [
        const Spacer(),
        // Illustration — floating gently
        _Reveal(delayMs: 40, offset: 26, child: _Floating(
          amplitude: 7,
          child: Container(
            clipBehavior: Clip.antiAlias,
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [Color(0xFFFFF3E9), Color(0xFFFFE4CE)], begin: Alignment.topLeft, end: Alignment.bottomRight),
              borderRadius: BorderRadius.circular(30),
              border: Border.all(color: AppTheme.brand.withOpacity(.12)),
              boxShadow: [BoxShadow(color: AppTheme.brand.withOpacity(.16), blurRadius: 28, offset: const Offset(0, 14), spreadRadius: -8)],
            ),
            child: AspectRatio(
              aspectRatio: 1.55,
              child: Padding(
                padding: const EdgeInsets.all(10),
                child: Image.asset(page.image, fit: BoxFit.contain),
              ),
            ),
          ),
        )),
        const SizedBox(height: 26),
        // Feature tag
        if (page.tag != null) ...[
          _Reveal(delayMs: 120, child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(color: AppTheme.brand.withOpacity(.12), borderRadius: BorderRadius.circular(30), border: Border.all(color: AppTheme.brand.withOpacity(.25))),
            child: Text(page.tag!.toUpperCase(), style: context.typo.labelSmall?.copyWith(color: AppTheme.brandDeep, fontWeight: FontWeight.w800, letterSpacing: 1.4, fontSize: 10)),
          )),
          const SizedBox(height: 14),
        ],
        // Animated title (word-by-word)
        _AnimatedWords(
          text: page.title,
          style: GoogleFonts.spaceGrotesk(fontSize: 30, height: 1.08, fontWeight: FontWeight.w700, letterSpacing: -1.1, color: context.tokens.text),
        ),
        const SizedBox(height: 14),
        // Subtitle
        _Reveal(delayMs: 380, child: Text(page.subtitle, textAlign: TextAlign.center, style: context.typo.bodyLarge?.copyWith(color: context.tokens.textSecondary, height: 1.55))),
        const SizedBox(height: 22),
        // Audience chips (page 1) or feature line (pages 2-4)
        _Reveal(
          delayMs: 520,
          child: page.audience != null
              ? Wrap(alignment: WrapAlignment.center, spacing: 8, runSpacing: 8, children: [
                  for (final entry in page.audience!.asMap().entries) _audienceChip(entry.value, entry.key),
                ])
              : Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
                  decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(16), border: Border.all(color: context.tokens.border)),
                  child: Row(mainAxisSize: MainAxisSize.min, mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(Icons.check_circle_rounded, size: 16, color: AppTheme.success),
                    const SizedBox(width: 8),
                    Flexible(child: Text(page.feature ?? '', style: context.typo.bodySmall?.copyWith(color: context.tokens.textSecondary, fontWeight: FontWeight.w600))),
                  ]),
                ),
        ),
        const Spacer(),
      ]),
    );
  }

  Widget _audienceChip(String label, int index) {
    icons = [Icons.storefront_rounded, Icons.group_rounded, Icons.fitness_center_rounded, Icons.support_agent_rounded];
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 9),
      decoration: BoxDecoration(color: context.tokens.surface, borderRadius: BorderRadius.circular(30), border: Border.all(color: context.tokens.border), boxShadow: context.subtleShadow),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icons[index % icons.length], size: 15, color: AppTheme.brand),
        const SizedBox(width: 7),
        Text(label, style: context.typo.labelMedium?.copyWith(color: context.tokens.text, fontWeight: FontWeight.w600)),
      ]),
    );
  }

  Widget _glow(double size, Color color) => Container(width: size, height: size, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [color, color.withOpacity(0)])));
}

/// Fade + slide-up reveal with a real delay (used to stagger elements).
class _Reveal extends StatelessWidget {
  final Widget child;
  final int delayMs;
  final double offset;
  const _Reveal({required this.child, this.delayMs = 0, this.offset = 20});

  @override
  Widget build(BuildContext context) {
    final total = 420 + delayMs;
    final start = (delayMs / total).clamp(0.0, 0.9);
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: total),
      curve: Interval(start, 1.0, curve: Curves.easeOutCubic),
      builder: (context, v, child) => Opacity(opacity: v.clamp(0, 1), child: Transform.translate(offset: Offset(0, offset * (1 - v)), child: child)),
      child: child,
    );
  }
}

/// Word-by-word staggered reveal for titles.
class _AnimatedWords extends StatefulWidget {
  final String text;
  final TextStyle style;
  const _AnimatedWords({required this.text, required this.style});

  @override
  State<_AnimatedWords> createState() => _AnimatedWordsState();
}

class _AnimatedWordsState extends State<_AnimatedWords> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 950))..forward();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final lines = widget.text.split('\n');
    var index = 0;
    return Column(
      children: lines.map((line) {
        final words = line.split(' ');
        return Wrap(
          alignment: WrapAlignment.center,
          children: words.map((w) {
            final i = index++;
            final start = (i * 0.085).clamp(0.0, 0.72);
            final end = (start + 0.26).clamp(0.0, 1.0);
            final anim = CurvedAnimation(parent: _c, curve: Interval(start, end, curve: Curves.easeOutCubic));
            return AnimatedBuilder(
              animation: anim,
              builder: (context, child) => Opacity(
                opacity: anim.value,
                child: Transform.translate(offset: Offset(0, 16 * (1 - anim.value)), child: child),
              ),
              child: Padding(padding: const EdgeInsets.only(right: 7), child: Text(w, style: widget.style)),
            );
          }).toList(),
        );
      }).toList(),
    );
  }
}

/// Gentle up-and-down float for the illustration (keeps the screen alive).
class _Floating extends StatefulWidget {
  final Widget child;
  final double amplitude;
  const _Floating({required this.child, this.amplitude = 6});

  @override
  State<_Floating> createState() => _FloatingState();
}

class _FloatingState extends State<_Floating> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 2600))..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      builder: (context, child) => Transform.translate(offset: Offset(0, math.sin(_c.value * math.pi) * widget.amplitude), child: child),
      child: widget.child,
    );
  }
}

class _OnboardingPage {
  final String image;
  final String? tag;
  final String title;
  final String subtitle;
  final List<String>? audience;
  final String? feature;
  const _OnboardingPage({
    required this.image,
    this.tag,
    required this.title,
    required this.subtitle,
    this.audience,
    this.feature,
  });
}
