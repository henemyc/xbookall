import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class AdminQRScreen extends ConsumerStatefulWidget {
  final bool standalone;
  const AdminQRScreen({super.key, this.standalone = false});
  @override
  ConsumerState<AdminQRScreen> createState() => _AdminQRScreenState();
}

class _AdminQRScreenState extends ConsumerState<AdminQRScreen> {
  String? qrSecret;
  String gymName = 'GYM NAME';
  bool loading = true;
  String? error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { loading = true; error = null; });
    try {
      final api = ref.read(apiClientProvider);
      final res = await api.getSettings();
      final data = (res is Map) ? Map<String, dynamic>.from(res) : <String, dynamic>{};
      final settings = (data['settings'] is Map) ? Map<String, dynamic>.from(data['settings']) : data;
      setState(() {
        qrSecret = settings['attendance_qr_secret'] ?? 'gymxbook-secret';
        gymName = (settings['company_name'] ?? settings['gym_name'] ?? 'GYM NAME').toString().toUpperCase();
        loading = false;
      });
      try {
        final me = await api.me();
        final gName = me['gym_info']?['name'];
        // For staff login never fallback to me.user.name, because that is the
        // staff person's name, not the gym name.
        if (gName != null && gName.toString().trim().isNotEmpty) {
          setState(() => gymName = gName.toString().toUpperCase());
        }
      } catch (_) {}
    } catch (e) {
      setState(() { error = e.toString(); loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: widget.standalone ? AppBar(title: const Text('Gym QR Code')) : null,
      bottomNavigationBar: widget.standalone ? const AppBottomNav() : null,
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? ErrorRetry(message: 'Could not load QR code.', onRetry: _load)
              : SingleChildScrollView(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 40),
                  child: Column(children: [
                    FadeInUp(child: _qrCard()),
                    const SizedBox(height: 22),
                    FadeInUp(delayMs: 80, child: SurfaceCard(
                      color: AppTheme.info.withOpacity(0.08),
                      border: Border.all(color: AppTheme.info.withOpacity(0.2)),
                      child: Row(children: [
                        const Icon(Icons.info_outline_rounded, color: AppTheme.info, size: 20),
                        const SizedBox(width: 12),
                        Expanded(child: Text('This QR is unique to your gym. Members scan it to mark attendance. It auto-checks out after 4 hours if forgotten.', style: context.typo.bodySmall?.copyWith(color: AppTheme.info, height: 1.45))),
                      ]),
                    )),
                  ]),
                ),
    );
  }

  Widget _qrCard() {
    return Container(
      decoration: BoxDecoration(
        gradient: AppTheme.darkHeroGradient,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.3), blurRadius: 30, offset: const Offset(0, 12), spreadRadius: -6)],
      ),
      child: Stack(children: [
        Positioned(top: -40, left: -30, child: Container(width: 150, height: 150, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [AppTheme.brand.withOpacity(0.35), Colors.transparent])))),
        Positioned(bottom: -30, right: -20, child: Container(width: 180, height: 180, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [AppTheme.info.withOpacity(0.22), Colors.transparent])))),
        Padding(
          padding: const EdgeInsets.all(26),
          child: Column(children: [
            Container(width: 48, height: 48, decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(14)), child: const Icon(Icons.local_fire_department_rounded, color: Colors.white, size: 26)),
            const SizedBox(height: 14),
            Text(gymName, textAlign: TextAlign.center, style: GoogleFonts.spaceGrotesk(fontSize: 21, fontWeight: FontWeight.w700, color: Colors.white, letterSpacing: 0.5)),
            const SizedBox(height: 5),
            Text('SCAN TO MARK ATTENDANCE', style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: FontWeight.w700, letterSpacing: 2, color: AppTheme.brandAmber)),
            const SizedBox(height: 24),
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(22)),
              child: qrSecret == null
                  ? const SizedBox(width: 220, height: 220, child: Center(child: CircularProgressIndicator()))
                  : QrImageView(
                      data: qrSecret!,
                      version: QrVersions.auto,
                      size: 220,
                      backgroundColor: Colors.white,
                      eyeStyle: const QrEyeStyle(eyeShape: QrEyeShape.circle, color: Color(0xFF17110F)),
                      dataModuleStyle: const QrDataModuleStyle(dataModuleShape: QrDataModuleShape.circle, color: Color(0xFF17110F)),
                    ),
            ),
            const SizedBox(height: 22),
            Text('Powered by GymXBook', style: GoogleFonts.poppins(fontSize: 11, color: Colors.white.withOpacity(0.65), fontWeight: FontWeight.w600)),
            const SizedBox(height: 3),
            Text('Show this at gym entrance', style: GoogleFonts.poppins(fontSize: 10, color: Colors.white.withOpacity(0.45))),
          ]),
        ),
      ]),
    );
  }
}
