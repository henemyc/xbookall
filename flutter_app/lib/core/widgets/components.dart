import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shimmer/shimmer.dart';
import 'package:gymxbook/core/theme/app_theme.dart';
import 'package:gymxbook/core/widgets/glass.dart';

// ══════════════════════════════════════════════════════════════════
//  SECTION HEADER
// ══════════════════════════════════════════════════════════════════
class SectionHeader extends StatelessWidget {
  final String title;
  final String? action;
  final VoidCallback? onAction;
  final EdgeInsetsGeometry padding;
  const SectionHeader(this.title, {super.key, this.action, this.onAction, this.padding = EdgeInsets.zero});
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: padding,
      child: Row(
        children: [
          Container(width: 4, height: 18, decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(4))),
          const SizedBox(width: 10),
          Expanded(child: Text(title, style: context.typo.titleMedium?.copyWith(fontWeight: FontWeight.w700))),
          if (action != null)
            GestureDetector(
              onTap: onAction,
              child: Row(children: [
                Text(action!, style: context.typo.labelMedium?.copyWith(color: AppTheme.brand, fontWeight: FontWeight.w700)),
                const Icon(Icons.chevron_right_rounded, size: 16, color: AppTheme.brand),
              ]),
            ),
        ],
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  STAT TILE (dashboard metrics)
// ══════════════════════════════════════════════════════════════════
class StatTile extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final String? trend;
  final bool trendUp;
  final VoidCallback? onTap;
  const StatTile({super.key, required this.label, required this.value, required this.icon, required this.color, this.trend, this.trendUp = true, this.onTap});
  @override
  Widget build(BuildContext context) {
    return SurfaceCard(
      onTap: onTap,
      padding: const EdgeInsets.all(11),
      radius: 18,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Container(
                width: 34, height: 34,
                decoration: BoxDecoration(
                  color: color.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(11),
                ),
                child: Icon(icon, color: color, size: 18),
              ),
              const Spacer(),
              if (trend != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                  decoration: BoxDecoration(color: (trendUp ? AppTheme.success : AppTheme.danger).withOpacity(0.12), borderRadius: BorderRadius.circular(20)),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(trendUp ? Icons.trending_up_rounded : Icons.trending_down_rounded, size: 12, color: trendUp ? AppTheme.success : AppTheme.danger),
                    const SizedBox(width: 2),
                    Text(trend!, style: GoogleFonts.poppins(fontSize: 10, fontWeight: FontWeight.w700, color: trendUp ? AppTheme.success : AppTheme.danger)),
                  ]),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: GoogleFonts.spaceGrotesk(fontSize: 22, fontWeight: FontWeight.w800, height: 1, color: context.tokens.text)),
            const SizedBox(height: 2),
            Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontWeight: FontWeight.w600, fontSize: 11)),
          ]),
        ],
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  GRADIENT PILL BUTTON
// ══════════════════════════════════════════════════════════════════
class FireButton extends StatefulWidget {
  final String label;
  final IconData? icon;
  final VoidCallback? onPressed;
  final bool loading;
  final bool expand;
  final Gradient gradient;
  const FireButton({super.key, required this.label, this.icon, this.onPressed, this.loading = false, this.expand = true, this.gradient = AppTheme.fireGradient});
  @override
  State<FireButton> createState() => _FireButtonState();
}

class _FireButtonState extends State<FireButton> {
  bool _submitting = false;

  bool get _isLoading => widget.loading || _submitting;

  void _handleTap() {
    if (_isLoading || widget.onPressed == null) return;
    // Use Function.apply to get the dynamic return value
    final dynamic result = Function.apply(widget.onPressed!, []);
    // If the callback returned a Future, auto-show loading until done
    if (result is Future) {
      setState(() => _submitting = true);
      result.whenComplete(() {
        if (mounted) setState(() => _submitting = false);
      }).catchError((_) {
        if (mounted) setState(() => _submitting = false);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final disabled = widget.onPressed == null || _isLoading;
    final child = Container(
      height: 54,
      width: widget.expand ? double.infinity : null,
      padding: widget.expand ? null : const EdgeInsets.symmetric(horizontal: 26),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        gradient: disabled ? null : widget.gradient,
        color: disabled ? context.tokens.surfaceAlt : null,
        borderRadius: BorderRadius.circular(16),
        boxShadow: disabled ? null : [BoxShadow(color: AppTheme.brand.withOpacity(0.35), blurRadius: 20, offset: const Offset(0, 8), spreadRadius: -4)],
      ),
      child: _isLoading
          ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.4, color: Colors.white))
          : Row(mainAxisSize: MainAxisSize.min, mainAxisAlignment: MainAxisAlignment.center, children: [
              if (widget.icon != null) ...[Icon(widget.icon, size: 20, color: disabled ? context.tokens.textTertiary : Colors.white), const SizedBox(width: 9)],
              Text(widget.label, style: GoogleFonts.poppins(fontSize: 15.5, fontWeight: FontWeight.w700, letterSpacing: 0.2, color: disabled ? context.tokens.textTertiary : Colors.white)),
            ]),
    );
    return Pressable(onTap: _handleTap, radius: 16, child: child);
  }
}

// ══════════════════════════════════════════════════════════════════
//  BADGE / CHIP
// ══════════════════════════════════════════════════════════════════
class StatusBadge extends StatelessWidget {
  final String text;
  final Color color;
  final IconData? icon;
  final bool soft;
  const StatusBadge(this.text, {super.key, required this.color, this.icon, this.soft = true});
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: icon != null ? 9 : 10, vertical: 5),
      decoration: BoxDecoration(
        color: soft ? color.withOpacity(0.13) : color,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        if (icon != null) ...[Icon(icon, size: 12, color: soft ? color : Colors.white), const SizedBox(width: 4)],
        Text(text, style: GoogleFonts.poppins(fontSize: 11.5, fontWeight: FontWeight.w700, color: soft ? color : Colors.white)),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  AVATAR (gradient initial fallback)
// ══════════════════════════════════════════════════════════════════
class GxAvatar extends StatelessWidget {
  final String name;
  final double size;
  final int? seed;
  const GxAvatar({super.key, required this.name, this.size = 46, this.seed});
  @override
  Widget build(BuildContext context) {
    final letter = name.trim().isNotEmpty ? name.trim()[0].toUpperCase() : '?';
    final colorSeed = seed ?? name.hashCode;
    final c1 = AppTheme.categoryColors[colorSeed.abs() % AppTheme.categoryColors.length];
    return Container(
      width: size, height: size,
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [c1.withOpacity(0.9), c1], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(size * 0.34),
        boxShadow: [BoxShadow(color: c1.withOpacity(0.3), blurRadius: 10, offset: const Offset(0, 4))],
      ),
      alignment: Alignment.center,
      child: Text(letter, style: GoogleFonts.spaceGrotesk(color: Colors.white, fontWeight: FontWeight.w700, fontSize: size * 0.42)),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  ICON BADGE (colored square icon)
// ══════════════════════════════════════════════════════════════════
class IconBadge extends StatelessWidget {
  final IconData icon;
  final Color color;
  final double size;
  final double iconSize;
  const IconBadge(this.icon, {super.key, required this.color, this.size = 42, this.iconSize = 20});
  @override
  Widget build(BuildContext context) {
    return Container(
      width: size, height: size,
      decoration: BoxDecoration(color: color.withOpacity(0.14), borderRadius: BorderRadius.circular(size * 0.3)),
      child: Icon(icon, color: color, size: iconSize),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  EMPTY / ERROR STATES
// ══════════════════════════════════════════════════════════════════
class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;
  const EmptyState({super.key, required this.icon, required this.title, this.subtitle, this.actionLabel, this.onAction});
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 92, height: 92,
              decoration: BoxDecoration(gradient: LinearGradient(colors: [AppTheme.brand.withOpacity(0.14), AppTheme.brandAmber.withOpacity(0.06)]), borderRadius: BorderRadius.circular(30)),
              child: Icon(icon, size: 42, color: AppTheme.brand),
            ),
            const SizedBox(height: 20),
            Text(title, textAlign: TextAlign.center, style: context.typo.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            if (subtitle != null) ...[
              const SizedBox(height: 6),
              Text(subtitle!, textAlign: TextAlign.center, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
            ],
            if (actionLabel != null) ...[
              const SizedBox(height: 20),
              FireButton(label: actionLabel!, expand: false, onPressed: onAction),
            ],
          ],
        ),
      ),
    );
  }
}

class ErrorRetry extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const ErrorRetry({super.key, required this.message, required this.onRetry});
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 84, height: 84, decoration: BoxDecoration(color: AppTheme.danger.withOpacity(0.12), borderRadius: BorderRadius.circular(28)), child: const Icon(Icons.cloud_off_rounded, size: 38, color: AppTheme.danger)),
          const SizedBox(height: 18),
          Text('Something went wrong', style: context.typo.titleMedium),
          const SizedBox(height: 6),
          Text(message, textAlign: TextAlign.center, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          const SizedBox(height: 18),
          FireButton(label: 'Try Again', icon: Icons.refresh_rounded, expand: false, onPressed: onRetry),
        ]),
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  SHIMMER SKELETON
// ══════════════════════════════════════════════════════════════════
class ShimmerBox extends StatelessWidget {
  final double? width;
  final double height;
  final double radius;
  const ShimmerBox({super.key, this.width, this.height = 16, this.radius = 8});
  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Shimmer.fromColors(
      baseColor: t.surfaceAlt,
      highlightColor: t.isDark ? const Color(0xFF2E2420) : const Color(0xFFFFFFFF),
      child: Container(width: width, height: height, decoration: BoxDecoration(color: t.surfaceAlt, borderRadius: BorderRadius.circular(radius))),
    );
  }
}

class SkeletonList extends StatelessWidget {
  final int count;
  const SkeletonList({super.key, this.count = 6});
  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: count,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (_, __) => SurfaceCard(
        padding: const EdgeInsets.all(14),
        child: Row(children: [
          const ShimmerBox(width: 46, height: 46, radius: 16),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: const [
            ShimmerBox(width: 140, height: 14),
            SizedBox(height: 8),
            ShimmerBox(width: 90, height: 11),
          ])),
          const ShimmerBox(width: 60, height: 24, radius: 12),
        ]),
      ),
    );
  }
}

class SkeletonGrid extends StatelessWidget {
  const SkeletonGrid({super.key});
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        Row(children: const [Expanded(child: ShimmerBox(height: 96, radius: 22)), SizedBox(width: 12), Expanded(child: ShimmerBox(height: 96, radius: 22))]),
        const SizedBox(height: 12),
        Row(children: const [Expanded(child: ShimmerBox(height: 96, radius: 22)), SizedBox(width: 12), Expanded(child: ShimmerBox(height: 96, radius: 22))]),
        const SizedBox(height: 20),
        const ShimmerBox(height: 70, radius: 20),
        const SizedBox(height: 20),
        const Align(alignment: Alignment.centerLeft, child: ShimmerBox(width: 120, height: 16)),
        const SizedBox(height: 12),
        const ShimmerBox(height: 56, radius: 16),
        const SizedBox(height: 10),
        const ShimmerBox(height: 56, radius: 16),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  ANIMATED ENTRANCE (fade + slide up)
// ══════════════════════════════════════════════════════════════════
class FadeInUp extends StatelessWidget {
  final Widget child;
  final int delayMs;
  final double offset;
  const FadeInUp({super.key, required this.child, this.delayMs = 0, this.offset = 18});
  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 460 + delayMs),
      curve: Curves.easeOutCubic,
      builder: (context, v, child) => Opacity(
        opacity: v.clamp(0, 1),
        child: Transform.translate(offset: Offset(0, offset * (1 - v)), child: child),
      ),
      child: child,
    );
  }
}

// ══════════════════════════════════════════════════════════════════
//  ANIMATED COUNTER (for stat numbers)
// ══════════════════════════════════════════════════════════════════
class CountUp extends StatelessWidget {
  final num value;
  final TextStyle? style;
  final String prefix;
  final String suffix;
  const CountUp(this.value, {super.key, this.style, this.prefix = '', this.suffix = ''});
  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: value.toDouble()),
      duration: const Duration(milliseconds: 900),
      curve: Curves.easeOutExpo,
      builder: (context, v, _) => Text('$prefix${v.round()}$suffix', style: style),
    );
  }
}
