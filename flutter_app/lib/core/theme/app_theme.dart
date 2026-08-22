import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// ────────────────────────────────────────────────────────────────
///  GymXBook Design System — v3 "Fire" (2026)
///  Warm amber/orange energetic fitness palette.
///  Full light + dark token sets. Same brand logic, new premium skin.
/// ────────────────────────────────────────────────────────────────
class AppTheme {
  AppTheme._();

  // ── Brand accent — "Fire" gradient stops ─────────────────────────
  static const Color brand = Color(0xFFFF6B2C);     // primary orange
  static const Color brandDeep = Color(0xFFF43F1C); // deep ember red-orange
  static const Color brandAmber = Color(0xFFFFB020); // amber
  static const Color brandGlow = Color(0xFFFF8A3D); // glow

  static const LinearGradient fireGradient = LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFFFF8A3D), Color(0xFFFF6B2C), Color(0xFFF43F1C)],
      );

  static const LinearGradient amberGradient = LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFFFFC848), Color(0xFFFF8A3D)],
      );

  static const LinearGradient darkHeroGradient = LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF241A15), Color(0xFF1A1210), Color(0xFF120C0A)],
      );

  // ── Semantic status colors (shared) ──────────────────────────────
  static const Color success = Color(0xFF16C784);
  static const Color danger = Color(0xFFFF4D4F);
  static const Color warning = Color(0xFFFFA726);
  static const Color info = Color(0xFF3B9EFF);

  // Category accents for icon tiles / charts
  static const List<Color> categoryColors = [
    Color(0xFFFF6B2C), // orange
    Color(0xFF3B9EFF), // blue
    Color(0xFF16C784), // green
    Color(0xFFFFB020), // amber
    Color(0xFF8B5CF6), // violet
    Color(0xFFEC4899), // pink
    Color(0xFF06B6D4), // cyan
    Color(0xFFEF4444), // red
  ];

  // ════════════════════════════════════════════════════════════════
  //  TOKEN SETS
  // ════════════════════════════════════════════════════════════════
  static const _light = _Tokens(
    isDark: false,
    bg: Color(0xFFF6F7FB),
    bgElevated: Color(0xFFFFFFFF),
    surface: Color(0xFFFFFFFF),
    surfaceAlt: Color(0xFFF1F3F9),
    surfaceGlass: Color(0xE6FFFFFF),
    border: Color(0xFFE8EBF2),
    borderStrong: Color(0xFFD9DEE9),
    text: Color(0xFF111318),
    textSecondary: Color(0xFF5A6270),
    textTertiary: Color(0xFF98A0AE),
    shadow: Color(0x14101828),
    scrimGradient: LinearGradient(
      begin: Alignment.topCenter,
      end: Alignment.bottomCenter,
      colors: [Color(0xFFF6F7FB), Color(0xFFEFF1F7)],
    ),
  );

  static const _dark = _Tokens(
    isDark: true,
    bg: Color(0xFF0E0B0A),
    bgElevated: Color(0xFF17110F),
    surface: Color(0xFF1B1512),
    surfaceAlt: Color(0xFF241B17),
    surfaceGlass: Color(0xCC1B1512),
    border: Color(0xFF2E2420),
    borderStrong: Color(0xFF3C2F28),
    text: Color(0xFFF7F3F0),
    textSecondary: Color(0xFFB6ABA4),
    textTertiary: Color(0xFF7C6F68),
    shadow: Color(0x66000000),
    scrimGradient: LinearGradient(
      begin: Alignment.topCenter,
      end: Alignment.bottomCenter,
      colors: [Color(0xFF15100E), Color(0xFF0E0B0A)],
    ),
  );

  static _Tokens tokensFor(Brightness b) => b == Brightness.dark ? _dark : _light;

  // ════════════════════════════════════════════════════════════════
  //  THEME DATA
  // ════════════════════════════════════════════════════════════════
  static ThemeData light() => _build(_light);
  static ThemeData dark() => _build(_dark);

  static ThemeData _build(_Tokens t) {
    final base = t.isDark ? ThemeData.dark() : ThemeData.light();
    final textTheme = _textTheme(base.textTheme, t);

    return base.copyWith(
      useMaterial3: true,
      scaffoldBackgroundColor: t.bg,
      canvasColor: t.bg,
      splashColor: brand.withOpacity(0.08),
      highlightColor: brand.withOpacity(0.05),
      colorScheme: (t.isDark ? const ColorScheme.dark() : const ColorScheme.light()).copyWith(
        primary: brand,
        onPrimary: Colors.white,
        secondary: brandAmber,
        surface: t.surface,
        onSurface: t.text,
        error: danger,
        brightness: t.isDark ? Brightness.dark : Brightness.light,
      ),
      textTheme: textTheme,
      primaryColor: brand,
      dividerColor: t.border,
      appBarTheme: AppBarTheme(
        backgroundColor: t.bg,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: textTheme.titleLarge,
        iconTheme: IconThemeData(color: t.text),
      ),
      cardTheme: CardThemeData(
        color: t.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: t.border),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: t.surfaceAlt,
        hintStyle: GoogleFonts.poppins(color: t.textTertiary, fontSize: 14.5, fontWeight: FontWeight.w400),
        prefixIconColor: t.textTertiary,
        suffixIconColor: t.textTertiary,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide(color: t.border)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide(color: t.border)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide(color: brand, width: 1.6)),
        errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: danger)),
        focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: danger, width: 1.6)),
        counterStyle: GoogleFonts.poppins(color: t.textTertiary, fontSize: 11),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: brand,
          foregroundColor: Colors.white,
          elevation: 0,
          shadowColor: Colors.transparent,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
          textStyle: GoogleFonts.poppins(fontWeight: FontWeight.w700, fontSize: 15.5, letterSpacing: 0.2),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: brand,
          textStyle: GoogleFonts.poppins(fontWeight: FontWeight.w600, fontSize: 14),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: t.text,
          side: BorderSide(color: t.borderStrong),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          textStyle: GoogleFonts.poppins(fontWeight: FontWeight.w600, fontSize: 14.5),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: t.surfaceAlt,
        selectedColor: brand,
        side: BorderSide(color: t.border),
        labelStyle: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w600, color: t.textSecondary),
        secondaryLabelStyle: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.white),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: brand,
        foregroundColor: Colors.white,
        elevation: 6,
        highlightElevation: 2,
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: t.isDark ? const Color(0xFF2A211C) : const Color(0xFF1B1512),
        contentTextStyle: GoogleFonts.poppins(color: Colors.white, fontWeight: FontWeight.w500, fontSize: 13.5),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        insetPadding: const EdgeInsets.all(16),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: t.surface,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(28))),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: t.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        titleTextStyle: textTheme.titleLarge,
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: brand),
      dividerTheme: DividerThemeData(color: t.border, thickness: 1, space: 1),
      iconTheme: IconThemeData(color: t.textSecondary),
      // Snappier page transitions across the whole app (pushed routes like
      // member detail, check-in, reports, history, etc.).
      pageTransitionsTheme: const PageTransitionsTheme(
        builders: <TargetPlatform, PageTransitionsBuilder>{
          TargetPlatform.android: _FastSlidePageTransitionsBuilder(),
          TargetPlatform.iOS: _FastSlidePageTransitionsBuilder(),
          TargetPlatform.macOS: _FastSlidePageTransitionsBuilder(),
          TargetPlatform.windows: _FastSlidePageTransitionsBuilder(),
          TargetPlatform.linux: _FastSlidePageTransitionsBuilder(),
          TargetPlatform.fuchsia: _FastSlidePageTransitionsBuilder(),
        },
      ),
      extensions: [t],
    );
  }

  // ── Typography scale ─────────────────────────────────────────────
  static TextTheme _textTheme(TextTheme base, _Tokens t) {
    final display = GoogleFonts.spaceGrotesk; // characterful display for numbers/headings
    final body = GoogleFonts.poppins;
    return base.copyWith(
      displayLarge: display(fontWeight: FontWeight.w700, fontSize: 40, height: 1.05, letterSpacing: -1, color: t.text),
      displayMedium: display(fontWeight: FontWeight.w700, fontSize: 32, height: 1.08, letterSpacing: -0.8, color: t.text),
      headlineLarge: display(fontWeight: FontWeight.w700, fontSize: 28, height: 1.12, letterSpacing: -0.6, color: t.text),
      headlineMedium: display(fontWeight: FontWeight.w700, fontSize: 23, height: 1.15, letterSpacing: -0.4, color: t.text),
      headlineSmall: display(fontWeight: FontWeight.w600, fontSize: 19, height: 1.2, letterSpacing: -0.2, color: t.text),
      titleLarge: body(fontWeight: FontWeight.w700, fontSize: 18, height: 1.25, letterSpacing: -0.2, color: t.text),
      titleMedium: body(fontWeight: FontWeight.w600, fontSize: 15.5, height: 1.3, color: t.text),
      titleSmall: body(fontWeight: FontWeight.w600, fontSize: 13.5, height: 1.3, color: t.text),
      bodyLarge: body(fontWeight: FontWeight.w400, fontSize: 15.5, height: 1.5, color: t.text),
      bodyMedium: body(fontWeight: FontWeight.w400, fontSize: 14, height: 1.5, color: t.textSecondary),
      bodySmall: body(fontWeight: FontWeight.w400, fontSize: 12.5, height: 1.45, color: t.textSecondary),
      labelLarge: body(fontWeight: FontWeight.w700, fontSize: 14.5, letterSpacing: 0.2, color: t.text),
      labelMedium: body(fontWeight: FontWeight.w600, fontSize: 12.5, letterSpacing: 0.3, color: t.textSecondary),
      labelSmall: body(fontWeight: FontWeight.w700, fontSize: 10.5, letterSpacing: 0.8, color: t.textTertiary),
    );
  }

  // ── Legacy aliases (kept so not-yet-migrated screens keep compiling
  //    against the new palette; new code should use tokens/ColorScheme) ─
  static const Color primary = brand;
  static const Color primaryLight = Color(0x1AFF6B2C);
  static const Color background = Color(0xFFF6F7FB);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color surface2 = Color(0xFFF1F3F9);
  static const Color text = Color(0xFF111318);
  static const Color text2 = Color(0xFF5A6270);
  static const Color text3 = Color(0xFF98A0AE);
  static const Color border = Color(0xFFE8EBF2);
  static const Color border2 = Color(0xFFEFF1F7);
  static const Color red = danger;
  static const Color redBg = Color(0x1AFF4D4F);
  static const Color green = success;
  static const Color greenBg = Color(0x1A16C784);
  static const Color orange = warning;
  static const Color orangeBg = Color(0x1AFFA726);
  static const Color blueBg = Color(0x1A3B9EFF);
  static const Color expiringYellow = Color(0xFFD97706);
  static const Color expiringYellowBg = Color(0x1FD97706);

  /// Legacy map-based helper used by not-yet-migrated screens.
  static Map<String, Color> getExpiryColors(int daysLeft) {
    if (daysLeft < 0 && daysLeft >= -3) return {'text': danger, 'bg': redBg};
    if (daysLeft >= 0 && daysLeft <= 7) return {'text': expiringYellow, 'bg': expiringYellowBg};
    return {'text': text2, 'bg': surface2};
  }

  // ── Expiry badge colors (same brand logic as PWA) ────────────────
  static ({Color fg, Color bg}) expiryColors(int daysLeft, Brightness b) {
    final dark = b == Brightness.dark;
    if (daysLeft < 0 && daysLeft >= -3) {
      return (fg: danger, bg: danger.withOpacity(dark ? 0.16 : 0.10));
    } else if (daysLeft < 0) {
      return (fg: danger, bg: danger.withOpacity(dark ? 0.14 : 0.09));
    } else if (daysLeft >= 0 && daysLeft <= 7) {
      return (fg: const Color(0xFFD97706), bg: const Color(0xFFD97706).withOpacity(dark ? 0.18 : 0.12));
    } else {
      return (fg: success, bg: success.withOpacity(dark ? 0.16 : 0.10));
    }
  }
}

/// Design tokens exposed as a ThemeExtension so any widget can read
/// context.tokens for palette values not covered by ColorScheme.
class _Tokens extends ThemeExtension<_Tokens> {
  final bool isDark;
  final Color bg;
  final Color bgElevated;
  final Color surface;
  final Color surfaceAlt;
  final Color surfaceGlass;
  final Color border;
  final Color borderStrong;
  final Color text;
  final Color textSecondary;
  final Color textTertiary;
  final Color shadow;
  final LinearGradient scrimGradient;

  const _Tokens({
    required this.isDark,
    required this.bg,
    required this.bgElevated,
    required this.surface,
    required this.surfaceAlt,
    required this.surfaceGlass,
    required this.border,
    required this.borderStrong,
    required this.text,
    required this.textSecondary,
    required this.textTertiary,
    required this.shadow,
    required this.scrimGradient,
  });

  @override
  ThemeExtension<_Tokens> copyWith() => this;

  @override
  ThemeExtension<_Tokens> lerp(ThemeExtension<_Tokens>? other, double t) {
    if (other is! _Tokens) return this;
    return t < 0.5 ? this : other;
  }
}

/// Convenience accessor: `context.tokens.surface`, `context.isDark`, etc.
extension AppThemeX on BuildContext {
  _Tokens get tokens => Theme.of(this).extension<_Tokens>()!;
  bool get isDark => Theme.of(this).brightness == Brightness.dark;
  TextTheme get typo => Theme.of(this).textTheme;

  // Elevated soft shadow used across cards
  List<BoxShadow> get softShadow => [
        BoxShadow(color: tokens.shadow, blurRadius: 24, offset: const Offset(0, 8), spreadRadius: -6),
      ];
  List<BoxShadow> get subtleShadow => [
        BoxShadow(color: tokens.shadow, blurRadius: 12, offset: const Offset(0, 4), spreadRadius: -4),
      ];
}

// ════════════════════════════════════════════════════════════════
//  FAST PAGE TRANSITIONS
//  Shorter-than-default route animations for a snappier feel.
// ════════════════════════════════════════════════════════════════
const Duration kFastPageTransitionDuration = Duration(milliseconds: 150);

/// Horizontal slide + subtle fade (Android / desktop). 150ms vs 300ms default.
class _FastSlidePageTransitionsBuilder extends PageTransitionsBuilder {
  const _FastSlidePageTransitionsBuilder();

  @override
  Duration get transitionDuration => kFastPageTransitionDuration;

  @override
  Widget buildTransitions<T>(
    PageRoute<T> route,
    BuildContext context,
    Animation<double> animation,
    Animation<double> secondaryAnimation,
    Widget child,
  ) {
    final curved = CurvedAnimation(
      parent: animation,
      curve: Curves.easeOutCubic,
      reverseCurve: Curves.easeInCubic,
    );
    final slide = Tween<Offset>(begin: const Offset(0.06, 0), end: Offset.zero).animate(curved);
    return SlideTransition(position: slide, child: FadeTransition(opacity: curved, child: child));
  }
}
