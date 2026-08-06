import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:gymxbook/core/theme/app_theme.dart';

/// Frosted-glass container. Falls back gracefully on low-end devices.
class GlassCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final double radius;
  final double blur;
  final VoidCallback? onTap;
  final Color? tint;
  final Border? border;

  const GlassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.radius = 24,
    this.blur = 18,
    this.onTap,
    this.tint,
    this.border,
  });

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    final content = ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: Container(
          padding: padding,
          decoration: BoxDecoration(
            color: tint ?? t.surfaceGlass,
            borderRadius: BorderRadius.circular(radius),
            border: border ?? Border.all(color: Colors.white.withOpacity(t.isDark ? 0.06 : 0.6)),
          ),
          child: child,
        ),
      ),
    );
    if (onTap == null) return content;
    return _Pressable(onTap: onTap!, child: content);
  }
}

/// Standard surface card with soft shadow + hairline border.
class SurfaceCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final double radius;
  final VoidCallback? onTap;
  final Color? color;
  final Gradient? gradient;
  final bool shadow;
  final Border? border;

  const SurfaceCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.radius = 22,
    this.onTap,
    this.color,
    this.gradient,
    this.shadow = true,
    this.border,
  });

  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    final content = Container(
      padding: padding,
      decoration: BoxDecoration(
        color: gradient == null ? (color ?? t.surface) : null,
        gradient: gradient,
        borderRadius: BorderRadius.circular(radius),
        border: border ?? Border.all(color: t.border),
        boxShadow: shadow ? context.subtleShadow : null,
      ),
      child: child,
    );
    if (onTap == null) return content;
    return _Pressable(onTap: onTap!, radius: radius, child: content);
  }
}

/// Adds a satisfying scale-press micro-interaction to any child.
/// Includes debounce to prevent accidental double-taps.
class _Pressable extends StatefulWidget {
  final Widget child;
  final VoidCallback onTap;
  final double radius;
  const _Pressable({required this.child, required this.onTap, this.radius = 22});
  @override
  State<_Pressable> createState() => _PressableState();
}

class _PressableState extends State<_Pressable> {
  double _scale = 1;
  bool _debouncing = false;

  void _handleTap() {
    if (_debouncing) return;
    _debouncing = true;
    widget.onTap();
    Future.delayed(const Duration(milliseconds: 800), () {
      if (mounted) _debouncing = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _scale = 0.97),
      onTapUp: (_) => setState(() => _scale = 1),
      onTapCancel: () => setState(() => _scale = 1),
      onTap: _handleTap,
      child: AnimatedScale(
        scale: _scale,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: widget.child,
      ),
    );
  }
}

/// Public pressable wrapper reused by other components.
class Pressable extends StatelessWidget {
  final Widget child;
  final VoidCallback onTap;
  final double radius;
  const Pressable({super.key, required this.child, required this.onTap, this.radius = 22});
  @override
  Widget build(BuildContext context) => _Pressable(onTap: onTap, radius: radius, child: child);
}
