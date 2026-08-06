import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';

class MemberBmiScreen extends StatefulWidget {
  const MemberBmiScreen({super.key});

  @override
  State<MemberBmiScreen> createState() => _MemberBmiScreenState();
}

class _MemberBmiScreenState extends State<MemberBmiScreen> {
  double heightCm = 170;
  double weightKg = 70;
  int age = 25;
  String gender = 'male';

  double get bmi {
    final meter = heightCm / 100;
    if (meter <= 0) return 0;
    return weightKg / (meter * meter);
  }

  String get category {
    final v = bmi;
    if (v < 18.5) return 'Underweight';
    if (v < 25) return 'Normal';
    if (v < 30) return 'Overweight';
    return 'Obese';
  }

  Color get categoryColor {
    final v = bmi;
    if (v < 18.5) return AppTheme.info;
    if (v < 25) return AppTheme.success;
    if (v < 30) return AppTheme.warning;
    return AppTheme.danger;
  }

  String get advice {
    switch (category) {
      case 'Underweight':
        return 'Focus on strength training and a calorie-surplus diet with enough protein.';
      case 'Normal':
        return 'Great range. Maintain consistency with balanced nutrition and regular workouts.';
      case 'Overweight':
        return 'Add daily activity, strength training, and a small calorie deficit for fat loss.';
      default:
        return 'Start with low-impact cardio, guided training, and consult a health professional.';
    }
  }

  double get progress {
    final clamped = bmi.clamp(12.0, 40.0);
    return (clamped - 12.0) / 28.0;
  }

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Scaffold(
      body: ListView(
        padding: EdgeInsets.fromLTRB(16, 10, 16, context.navSpace + 18),
        children: [
          _hero(t),
          const SizedBox(height: 16),
          _genderCard(),
          const SizedBox(height: 14),
          _sliderCard(
            title: 'Height',
            value: '${heightCm.toStringAsFixed(0)} cm',
            icon: Icons.height_rounded,
            color: AppTheme.info,
            min: 120,
            max: 220,
            valueRaw: heightCm,
            onChanged: (v) => setState(() => heightCm = v),
          ),
          const SizedBox(height: 14),
          _sliderCard(
            title: 'Weight',
            value: '${weightKg.toStringAsFixed(0)} kg',
            icon: Icons.monitor_weight_rounded,
            color: AppTheme.success,
            min: 30,
            max: 180,
            valueRaw: weightKg,
            onChanged: (v) => setState(() => weightKg = v),
          ),
          const SizedBox(height: 14),
          _ageCard(),
          const SizedBox(height: 18),
          _adviceCard(),
        ],
      ),
    );
  }

  Widget _hero(dynamic t) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: progress),
      duration: const Duration(milliseconds: 750),
      curve: Curves.easeOutCubic,
      builder: (_, animatedProgress, __) {
        return Container(
          padding: const EdgeInsets.all(22),
          decoration: BoxDecoration(
            gradient: AppTheme.darkHeroGradient,
            borderRadius: BorderRadius.circular(28),
            boxShadow: [BoxShadow(color: AppTheme.brand.withOpacity(0.22), blurRadius: 28, offset: const Offset(0, 12))],
          ),
          child: Column(children: [
            Row(children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(16)),
                child: const Icon(Icons.calculate_rounded, color: Colors.white, size: 26),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('BMI Calculator', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
                Text('Body Mass Index', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.62), fontSize: 12)),
              ])),
            ]),
            const SizedBox(height: 24),
            SizedBox(
              width: 190,
              height: 190,
              child: CustomPaint(
                painter: _BmiGaugePainter(progress: animatedProgress, color: categoryColor),
                child: Center(
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    TweenAnimationBuilder<double>(
                      tween: Tween(begin: 0, end: bmi),
                      duration: const Duration(milliseconds: 650),
                      curve: Curves.easeOutCubic,
                      builder: (_, value, __) => Text(
                        value.toStringAsFixed(1),
                        style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 42, fontWeight: FontWeight.w900, letterSpacing: -1),
                      ),
                    ),
                    const SizedBox(height: 2),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                      decoration: BoxDecoration(color: categoryColor.withOpacity(0.18), borderRadius: BorderRadius.circular(30), border: Border.all(color: categoryColor.withOpacity(0.45))),
                      child: Text(category, style: GoogleFonts.poppins(color: categoryColor, fontSize: 12, fontWeight: FontWeight.w800)),
                    ),
                  ]),
                ),
              ),
            ),
          ]),
        );
      },
    );
  }

  Widget _genderCard() {
    return FadeInUp(
      child: SurfaceCard(
        padding: const EdgeInsets.all(10),
        child: Row(children: [
          _genderOption('male', 'Male', Icons.male_rounded),
          const SizedBox(width: 10),
          _genderOption('female', 'Female', Icons.female_rounded),
        ]),
      ),
    );
  }

  Widget _genderOption(String value, String label, IconData icon) {
    final selected = gender == value;
    return Expanded(
      child: Pressable(
        radius: 18,
        onTap: () {
          HapticFeedback.selectionClick();
          setState(() => gender = value);
        },
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 240),
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            gradient: selected ? AppTheme.fireGradient : null,
            color: selected ? null : context.tokens.surfaceAlt,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: selected ? Colors.transparent : context.tokens.border),
          ),
          child: Column(children: [
            Icon(icon, color: selected ? Colors.white : context.tokens.textSecondary, size: 28),
            const SizedBox(height: 6),
            Text(label, style: context.typo.titleSmall?.copyWith(color: selected ? Colors.white : context.tokens.text, fontWeight: FontWeight.w800)),
          ]),
        ),
      ),
    );
  }

  Widget _sliderCard({
    required String title,
    required String value,
    required IconData icon,
    required Color color,
    required double min,
    required double max,
    required double valueRaw,
    required ValueChanged<double> onChanged,
  }) {
    return FadeInUp(
      child: SurfaceCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            IconBadge(icon, color: color, size: 42),
            const SizedBox(width: 12),
            Expanded(child: Text(title, style: context.typo.titleMedium)),
            Text(value, style: GoogleFonts.spaceGrotesk(fontSize: 22, fontWeight: FontWeight.w800, color: color)),
          ]),
          const SizedBox(height: 12),
          SliderTheme(
            data: SliderTheme.of(context).copyWith(
              activeTrackColor: color,
              inactiveTrackColor: color.withOpacity(0.14),
              thumbColor: color,
              overlayColor: color.withOpacity(0.12),
              trackHeight: 6,
            ),
            child: Slider(
              min: min,
              max: max,
              divisions: (max - min).round(),
              value: valueRaw,
              onChanged: (v) {
                HapticFeedback.selectionClick();
                onChanged(v);
              },
            ),
          ),
        ]),
      ),
    );
  }

  Widget _ageCard() {
    return FadeInUp(
      child: SurfaceCard(
        child: Row(children: [
          IconBadge(Icons.cake_rounded, color: AppTheme.warning, size: 42),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Age', style: context.typo.titleMedium),
            Text('For your profile estimate', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          ])),
          _stepButton(Icons.remove_rounded, () => setState(() => age = math.max(10, age - 1))),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Text('$age', style: GoogleFonts.spaceGrotesk(fontSize: 24, fontWeight: FontWeight.w900, color: AppTheme.warning)),
          ),
          _stepButton(Icons.add_rounded, () => setState(() => age = math.min(90, age + 1))),
        ]),
      ),
    );
  }

  Widget _stepButton(IconData icon, VoidCallback onTap) {
    return Material(
      color: context.tokens.surfaceAlt,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () {
          HapticFeedback.selectionClick();
          onTap();
        },
        child: Padding(padding: const EdgeInsets.all(9), child: Icon(icon, size: 20)),
      ),
    );
  }

  Widget _adviceCard() {
    return FadeInUp(
      child: SurfaceCard(
        color: categoryColor.withOpacity(0.08),
        border: Border.all(color: categoryColor.withOpacity(0.22)),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          IconBadge(Icons.tips_and_updates_rounded, color: categoryColor, size: 42),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Recommendation', style: context.typo.titleMedium?.copyWith(color: categoryColor)),
            const SizedBox(height: 5),
            Text(advice, style: context.typo.bodySmall?.copyWith(height: 1.45)),
          ])),
        ]),
      ),
    );
  }
}

class _BmiGaugePainter extends CustomPainter {
  final double progress;
  final Color color;

  _BmiGaugePainter({required this.progress, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final center = Offset(size.width / 2, size.height / 2);
    final radius = math.min(size.width, size.height) / 2 - 10;
    final rect = Rect.fromCircle(center: center, radius: radius);
    const startAngle = math.pi * 0.75;
    const sweepAngle = math.pi * 1.5;

    final bgPaint = Paint()
      ..color = Colors.white.withOpacity(0.10)
      ..strokeWidth = 14
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final fgPaint = Paint()
      ..shader = SweepGradient(
        startAngle: startAngle,
        endAngle: startAngle + sweepAngle,
        colors: [color.withOpacity(0.35), color],
      ).createShader(rect)
      ..strokeWidth = 14
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    canvas.drawArc(rect, startAngle, sweepAngle, false, bgPaint);
    canvas.drawArc(rect, startAngle, sweepAngle * progress, false, fgPaint);
  }

  @override
  bool shouldRepaint(covariant _BmiGaugePainter oldDelegate) {
    return oldDelegate.progress != progress || oldDelegate.color != color;
  }
}
