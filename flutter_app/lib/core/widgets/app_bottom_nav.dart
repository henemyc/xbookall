import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/theme/app_theme.dart';
import 'package:gymxbook/core/providers/nav_provider.dart';

/// Spec for a single bottom-nav destination.
class NavDest {
  final IconData icon;
  final IconData active;
  final String label;
  final int index;
  const NavDest(this.icon, this.active, this.label, this.index);
}

/// The five primary admin destinations (must match MainShell order).
const kAdminNav = [
  NavDest(Icons.space_dashboard_outlined, Icons.space_dashboard_rounded, 'Home', 0),
  NavDest(Icons.people_outline_rounded, Icons.people_rounded, 'Members', 1),
  NavDest(Icons.qr_code_scanner_rounded, Icons.qr_code_scanner_rounded, 'Check In', 2),
  NavDest(Icons.bar_chart_rounded, Icons.bar_chart_rounded, 'Reports', 3),
  NavDest(Icons.swap_horiz_rounded, Icons.swap_horiz_rounded, 'History', 4),
];

const kMemberNav = [
  NavDest(Icons.space_dashboard_outlined, Icons.space_dashboard_rounded, 'Home', 0),
  NavDest(Icons.fact_check_outlined, Icons.fact_check_rounded, 'Visits', 1),
  NavDest(Icons.qr_code_scanner_rounded, Icons.qr_code_scanner_rounded, 'Scan', 2),
  NavDest(Icons.fitness_center_outlined, Icons.fitness_center_rounded, 'Workout', 3),
  NavDest(Icons.settings_outlined, Icons.settings_rounded, 'Settings', 4),
];

/// Reusable "Indicator" bottom nav. Use on pushed sub-pages (Add Member,
/// New Invoice, QR, Notices, Notifications…) so the primary nav is always
/// available. Tapping pops back to the shell root and switches the tab.
///
/// [selected] highlights the current tab if the page conceptually belongs to
/// one (e.g. Members section → index 1). Pass null for none.
class AppBottomNav extends ConsumerWidget {
  final List<NavDest> dests;
  final int? selected;
  const AppBottomNav({super.key, this.dests = kAdminNav, this.selected});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = Theme.of(context);
    final surface = t.colorScheme.surface;
    final border = t.dividerColor;
    final tertiary = t.textTheme.bodySmall?.color?.withOpacity(0.55) ?? Colors.grey;

    return Container(
      decoration: BoxDecoration(
        color: surface,
        border: Border(top: BorderSide(color: border)),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(t.brightness == Brightness.dark ? 0.4 : 0.06), blurRadius: 20, offset: const Offset(0, -4), spreadRadius: -8)],
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 62,
          child: Row(
            children: dests.map((d) {
              final sel = selected == d.index;
              return Expanded(
                child: GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTap: () {
                    HapticFeedback.selectionClick();
                    // Return to the shell root and switch to the tapped tab.
                    ref.read(navIndexProvider.notifier).state = d.index;
                    Navigator.of(context).popUntil((r) => r.isFirst);
                  },
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 260),
                        height: 3,
                        width: sel ? 26 : 0,
                        decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(4)),
                      ),
                      const SizedBox(height: 7),
                      Icon(sel ? d.active : d.icon, size: 23, color: sel ? AppTheme.brand : tertiary),
                      const SizedBox(height: 4),
                      Text(d.label, overflow: TextOverflow.ellipsis, maxLines: 1, style: GoogleFonts.poppins(fontSize: 10.5, fontWeight: sel ? FontWeight.w700 : FontWeight.w500, color: sel ? AppTheme.brand : tertiary)),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        ),
      ),
    );
  }
}
