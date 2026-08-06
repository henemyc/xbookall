import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/theme/app_theme.dart';

/// Layout helpers for the docked bottom navigation bar.
///
/// The nav is now a normal (docked) `bottomNavigationBar`, so the Scaffold
/// already reserves its height and the system-nav inset. Screens only need a
/// little extra breathing room at the end of their scroll content, and FABs
/// use the standard end-float location (which already sits above a docked bar).
class BottomNav {
  static const double barHeight = 64;
  static const double sideGap = 16;
  static const double bottomGap = 12;

  /// Small trailing space for scroll content above the docked bar.
  static double space(BuildContext context) => 24;
}

/// Convenience: comfortable bottom padding for scroll content.
extension BottomNavX on BuildContext {
  double get navSpace => BottomNav.space(this);
}

/// FAB location for screens inside [MainShell]. With a docked bar the default
/// end-float already clears it, so this is just the standard location.
class AboveNavFabLocation extends FloatingActionButtonLocation {
  const AboveNavFabLocation();
  @override
  Offset getOffset(ScaffoldPrelayoutGeometry g) =>
      FloatingActionButtonLocation.endFloat.getOffset(g);
}

/// Themed snackbars with icon + accent color.
/// Uses OverlayEntry so toasts always appear ON TOP of bottom sheets / dialogs.
class Toast {
  static void success(BuildContext context, String msg) => _show(context, msg, AppTheme.success, Icons.check_circle_rounded);
  static void error(BuildContext context, String msg) => _show(context, msg, AppTheme.danger, Icons.error_rounded);
  static void info(BuildContext context, String msg) => _show(context, msg, AppTheme.info, Icons.info_rounded);

  static void _show(BuildContext context, String msg, Color color, IconData icon) {
    // Try ScaffoldMessenger first (works when no sheet is open)
    try {
      final messenger = ScaffoldMessenger.maybeOf(context);
      if (messenger != null) {
        messenger
          ..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(
            content: Row(children: [
              Icon(icon, color: color, size: 20),
              const SizedBox(width: 12),
              Expanded(child: Text(msg, style: GoogleFonts.poppins(color: Colors.white, fontSize: 13.5, fontWeight: FontWeight.w500))),
            ]),
            duration: const Duration(seconds: 3),
          ));
        return;
      }
    } catch (_) {}

    // Fallback: show as an overlay (works on top of bottom sheets)
    _showOverlay(context, msg, color, icon);
  }

  static OverlayEntry? _currentOverlay;

  static void _showOverlay(BuildContext context, String msg, Color color, IconData icon) {
    _currentOverlay?.remove();
    _currentOverlay = null;

    late OverlayEntry entry;
    entry = OverlayEntry(
      builder: (ctx) => Positioned(
        top: MediaQuery.of(ctx).padding.top + 16,
        left: 16,
        right: 16,
        child: Material(
          color: Colors.transparent,
          child: TweenAnimationBuilder<double>(
            tween: Tween(begin: 0, end: 1),
            duration: const Duration(milliseconds: 250),
            builder: (_, v, child) => Opacity(opacity: v, child: Transform.translate(offset: Offset(0, -20 * (1 - v)), child: child)),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                color: Theme.of(context).brightness == Brightness.dark ? const Color(0xFF2A211C) : const Color(0xFF1B1512),
                borderRadius: BorderRadius.circular(14),
                boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.2), blurRadius: 20, offset: const Offset(0, 8))],
              ),
              child: Row(children: [
                Icon(icon, color: color, size: 20),
                const SizedBox(width: 12),
                Expanded(child: Text(msg, style: GoogleFonts.poppins(color: Colors.white, fontSize: 13.5, fontWeight: FontWeight.w500))),
              ]),
            ),
          ),
        ),
      ),
    );

    Overlay.of(context).insert(entry);
    _currentOverlay = entry;

    Future.delayed(const Duration(seconds: 3), () {
      entry.remove();
      if (_currentOverlay == entry) _currentOverlay = null;
    });
  }
}

/// A rounded drag-handle for bottom sheets.
class SheetHandle extends StatelessWidget {
  const SheetHandle({super.key});
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        margin: const EdgeInsets.only(top: 12, bottom: 4),
        width: 44, height: 5,
        decoration: BoxDecoration(color: Theme.of(context).dividerColor, borderRadius: BorderRadius.circular(10)),
      ),
    );
  }
}

/// Standard rounded modal bottom sheet wrapper.
Future<T?> showAppSheet<T>(BuildContext context, {required Widget child, bool scrollable = true}) {
  return showModalBottomSheet<T>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (ctx) => Container(
      decoration: BoxDecoration(
        color: Theme.of(ctx).colorScheme.surface,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(28)),
        border: Border(top: BorderSide(color: Theme.of(ctx).dividerColor)),
      ),
      child: Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom),
        child: SafeArea(
          top: false,
          child: Column(mainAxisSize: MainAxisSize.min, children: [const SheetHandle(), Flexible(child: child)]),
        ),
      ),
    ),
  );
}
