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
  static const _seenKey = 'has_seen_auth_onboarding_v1';
  final _controller = PageController();
  bool _checking = true;
  bool _showLanding = false;
  int _page = 0;

  final _pages = const [
    _OnboardingPage(Icons.dashboard_rounded, 'Run your gym\nwith clarity.', 'Members, attendance, trainers and daily operations in one focused workspace.'),
    _OnboardingPage(Icons.groups_rounded, 'Know your\nmembers better.', 'Manage memberships, renewals, attendance and member details with ease.'),
    _OnboardingPage(Icons.account_balance_wallet_rounded, 'Keep every\nrupee visible.', 'Create invoices, record payments and understand your gym’s monthly performance.'),
    _OnboardingPage(Icons.auto_awesome_rounded, 'Everything your gym\nneeds, together.', 'Simple to use for your team. Powerful enough to grow with your business.'),
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
    if (seen) {
      setState(() {
        _checking = false;
        _showLanding = true;
      });
      return;
    }
    setState(() => _checking = false);
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
    if (_checking) return const Scaffold(body: Center(child: CircularProgressIndicator(color: AppTheme.brand)));
    if (_showLanding) return const AuthLandingScreen();

    final item = _pages[_page];
    return Scaffold(
      backgroundColor: context.tokens.bg,
      body: SafeArea(
        child: Column(children: [
          Align(alignment: Alignment.centerRight, child: TextButton(onPressed: _finish, child: const Text('Skip'))),
          Expanded(
            child: PageView.builder(
              controller: _controller,
              itemCount: _pages.length,
              onPageChanged: (value) => setState(() => _page = value),
              itemBuilder: (_, index) {
                final page = _pages[index];
                return Padding(
                  padding: const EdgeInsets.fromLTRB(28, 10, 28, 20),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Container(
                      width: 154,
                      height: 154,
                      decoration: BoxDecoration(color: AppTheme.brand.withOpacity(.10), borderRadius: BorderRadius.circular(50)),
                      child: Icon(page.icon, size: 70, color: AppTheme.brand),
                    ),
                    const SizedBox(height: 42),
                    Text(page.title, textAlign: TextAlign.center, style: GoogleFonts.spaceGrotesk(fontSize: 31, height: 1.05, fontWeight: FontWeight.w700, letterSpacing: -1.2, color: context.tokens.text)),
                    const SizedBox(height: 16),
                    Text(page.subtitle, textAlign: TextAlign.center, style: context.typo.bodyLarge?.copyWith(color: context.tokens.textSecondary, height: 1.55)),
                  ]),
                );
              },
            ),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(
              _pages.length,
              (index) => AnimatedContainer(
                duration: const Duration(milliseconds: 220),
                margin: const EdgeInsets.symmetric(horizontal: 4),
                width: index == _page ? 24 : 7,
                height: 7,
                decoration: BoxDecoration(
                  color: index == _page ? AppTheme.brand : context.tokens.borderStrong,
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            ),
          ),
          const SizedBox(height: 24),
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 0, 24, 28),
            child: FireButton(
              label: _page == _pages.length - 1 ? 'Get Started' : 'Continue',
              icon: _page == _pages.length - 1 ? Icons.arrow_forward_rounded : null,
              onPressed: () {
                if (_page == _pages.length - 1) {
                  _finish();
                } else {
                  _controller.nextPage(duration: const Duration(milliseconds: 320), curve: Curves.easeOutCubic);
                }
              },
            ),
          ),
        ]),
      ),
    );
  }
}

class _OnboardingPage {
  final IconData icon;
  final String title;
  final String subtitle;
  const _OnboardingPage(this.icon, this.title, this.subtitle);
}
