import 'dart:math' as math;
import 'dart:ui';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';

/// TEMPORARY design-lab page.
/// Shows heroes, buttons, icon packs (Material / Cupertino / Lucide-style),
/// card styles (Material / Glass), fonts (incl. iOS-like), effects & animations.
class UiShowcaseScreen extends StatelessWidget {
  const UiShowcaseScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('UI Showcase')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 48),
        children: [
          _intro(context),
          const SizedBox(height: 22),
          const SectionHeader('1 · Action Styles'),
          const SizedBox(height: 10),
          ..._actionStyles(context),
          const SizedBox(height: 22),
          const SectionHeader('2 · Buttons'),
          const SizedBox(height: 10),
          _buttons(context),
          const SizedBox(height: 22),
          const SectionHeader('3 · Icon Packs'),
          const SizedBox(height: 10),
          _iconPacks(context),
          const SizedBox(height: 22),
          const SectionHeader('4 · Cards & Surfaces'),
          const SizedBox(height: 10),
          ..._cards(context),
          const SizedBox(height: 22),
          const SectionHeader('5 · Typography & Fonts'),
          const SizedBox(height: 10),
          _fonts(context),
          const SizedBox(height: 22),
          const SectionHeader('6 · Effects & Animations'),
          const SizedBox(height: 10),
          ..._effects(context),
          const SizedBox(height: 22),
          const SectionHeader('7 · iOS Components'),
          const SizedBox(height: 10),
          ..._iosComponents(context),
          const SizedBox(height: 22),
          const SectionHeader('8 · iOS Lists & Settings'),
          const SizedBox(height: 10),
          ..._iosLists(context),
          const SizedBox(height: 22),
          const SectionHeader('9 · iOS Icon Library'),
          const SizedBox(height: 10),
          _iosIconLibrary(context),
          const SizedBox(height: 22),
          const SectionHeader('10 · iOS Typography'),
          const SizedBox(height: 10),
          _iosTypography(context),
          const SizedBox(height: 22),
          const SectionHeader('11 · iOS Motion & Effects'),
          const SizedBox(height: 10),
          ..._iosMotion(context),
          const SizedBox(height: 22),
          const SectionHeader('12 · iOS Alerts, Sheets & Pickers'),
          const SizedBox(height: 10),
          ..._iosAlertsSheets(context),
          const SizedBox(height: 22),
          const SectionHeader('13 · Member Heroes — Dark ×5'),
          const SizedBox(height: 10),
          ..._memberHeroes(context),
          const SizedBox(height: 22),
          const SectionHeader('14 · Festival Greeting Cards ×5 (live)'),
          const SizedBox(height: 10),
          ..._festivalCards(context),
        ],
      ),
    );
  }

  // ── page intro ────────────────────────────────────────────────────
  Widget _intro(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)),
      child: Row(children: [
        Container(
          width: 48, height: 48,
          decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(16)),
          child: const Icon(Icons.palette_rounded, color: Colors.white, size: 24),
        ),
        const SizedBox(width: 13),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Design Lab', style: context.typo.titleLarge?.copyWith(color: Colors.white)),
          const SizedBox(height: 2),
          Text('Material + iOS (Cupertino) — heroes, buttons, icon packs, fonts, cards, effects & animations for the next design.', style: context.typo.bodySmall?.copyWith(color: Colors.white.withOpacity(.72))),
        ])),
      ]),
    );
  }

  // ── helpers ───────────────────────────────────────────────────────
  Widget _label(BuildContext context, String text) {
    return Padding(
      padding: const EdgeInsets.only(top: 14, bottom: 8, left: 2),
      child: Text(text, style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary, letterSpacing: 1.2)),
    );
  }

  Widget _demoCard(BuildContext context, {required String title, required String note, required Widget child}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: SurfaceCard(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: context.typo.titleSmall),
          const SizedBox(height: 2),
          Text(note, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 10.5)),
          const SizedBox(height: 12),
          child,
        ]),
      ),
    );
  }

  // ════════════════════════════════════════════════════════════════
  //  1 · ACTION STYLES (Renew · Edit · Workout · Diet · More)
  // ════════════════════════════════════════════════════════════════
  List<Widget> _actionStyles(BuildContext context) {
    return [
      _demoCard(context, title: 'A · Material colored orbs', note: 'Current app style — colored circles + Material icons', child: _styleMaterial(context)),
      _demoCard(context, title: 'B · Lucide stroke orbs', note: 'Thin 1.7px Lucide stroke icons, single neutral color', child: _styleLucideOrbs(context)),
      _demoCard(context, title: 'C · Cupertino (iOS) row', note: 'iOS toolbar — Cupertino icons on grey, blue tint', child: _styleCupertino(context)),
      _demoCard(context, title: 'D · Filled tonal tiles', note: 'Material 3 tonal tiles — 2×2 grid + wide More, Lucide icons', child: _styleTonalTiles(context)),
      _demoCard(context, title: 'E · Outline pills', note: 'Pill chips with Lucide stroke icons', child: _styleOutlinePills(context)),
      _demoCard(context, title: 'F · Gradient + ghost bar', note: 'One gradient hero action + ghost actions (pill bar)', child: _stylePillBar(context)),
    ];
  }

  Widget _orb(BuildContext context, Widget icon, String label, {Color? color, Color? bg, bool border = true}) {
    final t = context.tokens;
    return Column(mainAxisSize: MainAxisSize.min, children: [
      Container(
        width: 46, height: 46,
        decoration: BoxDecoration(
          color: bg ?? (color != null ? color.withOpacity(.12) : t.surfaceAlt),
          shape: BoxShape.circle,
          border: border && color != null && bg == null ? Border.all(color: color.withOpacity(.28)) : null,
        ),
        child: Center(child: icon),
      ),
      const SizedBox(height: 5),
      Text(label, style: context.typo.labelSmall?.copyWith(color: t.textSecondary)),
    ]);
  }

  Widget _styleMaterial(BuildContext context) {
    final items = <(IconData, String, Color)>[
      (Icons.autorenew_rounded, 'Renew', AppTheme.brand),
      (Icons.edit_rounded, 'Edit', AppTheme.info),
      (Icons.fitness_center_rounded, 'Workout', AppTheme.warning),
      (Icons.restaurant_menu_rounded, 'Diet', AppTheme.success),
      (Icons.more_horiz_rounded, 'More', context.tokens.textSecondary),
    ];
    return Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: items.map((e) => _orb(context, Icon(e.$1, color: e.$3, size: 22), e.$2, color: e.$3)).toList());
  }

  Widget _styleLucideOrbs(BuildContext context) {
    final grey = context.tokens.textSecondary;
    final items = <(_StrokeIconKind, String)>[
      (_StrokeIconKind.refreshCw, 'Renew'),
      (_StrokeIconKind.pencil, 'Edit'),
      (_StrokeIconKind.dumbbell, 'Workout'),
      (_StrokeIconKind.apple, 'Diet'),
      (_StrokeIconKind.moreH, 'More'),
    ];
    return Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: items.map((e) => _orb(context, _StrokeIcon(e.$1, color: grey, size: 21, strokeWidth: 1.7), e.$2, color: grey)).toList());
  }

  Widget _styleCupertino(BuildContext context) {
    final items = <(IconData, String)>[
      (CupertinoIcons.arrow_clockwise, 'Renew'),
      (CupertinoIcons.pencil, 'Edit'),
      (CupertinoIcons.flame_fill, 'Workout'),
      (CupertinoIcons.heart_fill, 'Diet'),
      (CupertinoIcons.ellipsis, 'More'),
    ];
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
      decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(16)),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: items.map((e) => _orb(context, Icon(e.$1, color: CupertinoColors.activeBlue, size: 22), e.$2, bg: CupertinoColors.systemGrey5, border: false)).toList()),
    );
  }

  Widget _styleTonalTiles(BuildContext context) {
    final t = context.tokens;
    final grey = t.textSecondary;
    Widget tile(_StrokeIconKind kind, String title, String sub) => Expanded(child: Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: t.surfaceAlt, borderRadius: BorderRadius.circular(16), border: Border.all(color: t.border)),
      child: Row(children: [
        Container(width: 36, height: 36, decoration: BoxDecoration(color: t.surface, borderRadius: BorderRadius.circular(11), border: Border.all(color: t.border)), child: Center(child: _StrokeIcon(kind, color: grey, size: 17, strokeWidth: 1.7))),
        const SizedBox(width: 9),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: context.typo.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
          Text(sub, style: context.typo.bodySmall?.copyWith(color: t.textTertiary, fontSize: 9.5), maxLines: 1, overflow: TextOverflow.ellipsis),
        ])),
      ]),
    ));
    return Column(children: [
      Row(children: [tile(_StrokeIconKind.refreshCw, 'Renew', 'Extend plan'), const SizedBox(width: 8), tile(_StrokeIconKind.pencil, 'Edit', 'Profile')]),
      const SizedBox(height: 8),
      Row(children: [tile(_StrokeIconKind.dumbbell, 'Workout', 'Assign'), const SizedBox(width: 8), tile(_StrokeIconKind.apple, 'Diet', 'Nutrition')]),
      const SizedBox(height: 8),
      tile(_StrokeIconKind.moreH, 'More', 'Freeze · locker · delete'),
    ]);
  }

  Widget _styleOutlinePills(BuildContext context) {
    final t = context.tokens;
    final items = <(_StrokeIconKind, String)>[
      (_StrokeIconKind.refreshCw, 'Renew'),
      (_StrokeIconKind.pencil, 'Edit'),
      (_StrokeIconKind.dumbbell, 'Workout'),
      (_StrokeIconKind.apple, 'Diet'),
      (_StrokeIconKind.moreH, 'More'),
    ];
    return Wrap(spacing: 8, runSpacing: 8, children: items.map((e) => Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
      decoration: BoxDecoration(border: Border.all(color: t.borderStrong), borderRadius: BorderRadius.circular(999)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        _StrokeIcon(e.$1, color: t.textSecondary, size: 15, strokeWidth: 1.7),
        const SizedBox(width: 7),
        Text(e.$2, style: context.typo.labelMedium?.copyWith(color: t.text, fontWeight: FontWeight.w600)),
      ]),
    )).toList());
  }

  Widget _stylePillBar(BuildContext context) {
    final t = context.tokens;
    Widget seg(Widget icon, String label, {bool hero = false}) => Expanded(child: Column(mainAxisSize: MainAxisSize.min, children: [
      Container(
        width: 42, height: 42,
        decoration: BoxDecoration(
          gradient: hero ? AppTheme.fireGradient : null,
          color: hero ? null : t.surfaceAlt,
          borderRadius: BorderRadius.circular(14),
          boxShadow: hero ? [BoxShadow(color: AppTheme.brand.withOpacity(.45), blurRadius: 14, offset: const Offset(0, 6))] : null,
        ),
        child: Center(child: icon),
      ),
      const SizedBox(height: 6),
      Text(label, style: context.typo.labelSmall?.copyWith(color: hero ? AppTheme.brandDeep : t.textSecondary, fontWeight: FontWeight.w700)),
    ]));
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
      decoration: BoxDecoration(color: t.surface, borderRadius: BorderRadius.circular(20), border: Border.all(color: t.border)),
      child: Row(children: [
        seg(const Icon(Icons.autorenew_rounded, color: Colors.white, size: 20), 'Renew', hero: true),
        seg(Icon(Icons.edit_rounded, color: t.textSecondary, size: 20), 'Edit'),
        seg(Icon(Icons.fitness_center_rounded, color: t.textSecondary, size: 20), 'Workout'),
        seg(Icon(Icons.restaurant_menu_rounded, color: t.textSecondary, size: 20), 'Diet'),
        seg(Icon(Icons.more_horiz_rounded, color: t.textSecondary, size: 20), 'More'),
      ]),
    );
  }

  Widget _pill(IconData icon, String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(color: color.withOpacity(.14), borderRadius: BorderRadius.circular(20), border: Border.all(color: color.withOpacity(.28))),
      child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 12, color: color), const SizedBox(width: 4), Text(label, style: GoogleFonts.poppins(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600))]),
    );
  }

  Widget _roundBtn(IconData icon, Color color) {
    return Container(width: 44, height: 44, decoration: BoxDecoration(color: color.withOpacity(.18), shape: BoxShape.circle, border: Border.all(color: color.withOpacity(.45))), child: Icon(icon, color: color, size: 20));
  }

  Widget _stat(IconData icon, String value, String label) {
    return Expanded(child: Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: Colors.white.withOpacity(.08), borderRadius: BorderRadius.circular(14), border: Border.all(color: Colors.white.withOpacity(.1))),
      child: Row(children: [
        Icon(icon, size: 17, color: AppTheme.brandAmber),
        const SizedBox(width: 8),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(value, style: GoogleFonts.spaceGrotesk(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
          Text(label, style: GoogleFonts.poppins(color: Colors.white.withOpacity(.6), fontSize: 9.5)),
        ])),
      ]),
    ));
  }

  // ════════════════════════════════════════════════════════════════
  //  2 · BUTTONS
  // ════════════════════════════════════════════════════════════════
  Widget _buttons(BuildContext context) {
    return SurfaceCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _label(context, 'Primary (Fire)'),
        FireButton(label: 'Sign In', icon: Icons.arrow_forward_rounded, onPressed: () {}),
        _label(context, 'Loading state'),
        FireButton(label: 'Processing…', loading: true, onPressed: () {}),
        _label(context, 'Disabled'),
        FireButton(label: 'Disabled', onPressed: null),
        _label(context, 'Secondary / Outlined'),
        OutlinedButton.icon(onPressed: () {}, icon: const Icon(Icons.add_rounded, size: 18), label: const Text('Add Member')),
        _label(context, 'Text / ghost'),
        TextButton(onPressed: () {}, child: const Text('View all')),
        _label(context, 'Icon button'),
        Row(children: [
          IconButton.filledTonal(onPressed: () {}, icon: const Icon(Icons.favorite_rounded)),
          const SizedBox(width: 8),
          IconButton.filled(onPressed: () {}, icon: const Icon(Icons.share_rounded)),
          const SizedBox(width: 8),
          IconButton.outlined(onPressed: () {}, icon: const Icon(Icons.more_horiz_rounded)),
        ]),
        _label(context, 'Pill / chip buttons'),
        Wrap(spacing: 8, runSpacing: 8, children: [
          ActionChip(avatar: const Icon(Icons.autorenew_rounded, size: 16), label: const Text('Renew'), onPressed: () {}),
          ActionChip(avatar: const Icon(Icons.edit_rounded, size: 16), label: const Text('Edit'), onPressed: () {}),
          ActionChip(avatar: const Icon(Icons.restaurant_menu_rounded, size: 16), label: const Text('Diet'), onPressed: () {}),
        ]),
        _label(context, 'Pressable tile (scale micro-interaction)'),
        Pressable(radius: 16, onTap: () {}, child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(16), border: Border.all(color: context.tokens.border)),
          child: Row(children: [Icon(Icons.touch_app_rounded, color: context.tokens.textSecondary), const SizedBox(width: 10), Text('Tap me — I scale down', style: context.typo.titleSmall)]),
        )),
      ]),
    );
  }

  // ════════════════════════════════════════════════════════════════
  //  3 · ICON PACKS
  // ════════════════════════════════════════════════════════════════
  Widget _iconPacks(BuildContext context) {
    final t = context.tokens;
    return Column(children: [
      _demoCard(context, title: 'Material (filled · rounded · outlined · sharp)', note: 'Flutter built-in Material Symbols — 4 visual weights', child: Wrap(spacing: 18, runSpacing: 18, children: [
        _iconCell(context, Icons.favorite, 'filled'),
        _iconCell(context, Icons.favorite_rounded, 'rounded'),
        _iconCell(context, Icons.favorite_outline_rounded, 'outlined'),
        _iconCell(context, Icons.favorite_sharp, 'sharp'),
        _iconCell(context, Icons.bolt, 'bolt'),
        _iconCell(context, Icons.fitness_center_rounded, 'gym'),
      ])),
      _demoCard(context, title: 'Cupertino (iOS / SF-Symbol style)', note: 'Apple-style glyphs used across iOS apps', child: Wrap(spacing: 18, runSpacing: 18, children: [
        _iconCell(context, CupertinoIcons.heart_fill, 'heart.fill'),
        _iconCell(context, CupertinoIcons.bolt_fill, 'bolt.fill'),
        _iconCell(context, CupertinoIcons.bell_fill, 'bell.fill'),
        _iconCell(context, CupertinoIcons.star_fill, 'star.fill'),
        _iconCell(context, CupertinoIcons.flame_fill, 'flame.fill'),
        _iconCell(context, CupertinoIcons.chart_bar_alt_fill, 'chart'),
      ])),
      _demoCard(context, title: 'Lucide-style (stroke icons)', note: '1.9px stroke, round caps — drawn with CustomPaint (no package needed)', child: Row(mainAxisAlignment: MainAxisAlignment.spaceAround, children: [
        _StrokeIcon(_StrokeIconKind.heart, color: t.text, size: 30, strokeWidth: 1.9),
        _StrokeIcon(_StrokeIconKind.search, color: t.text, size: 30, strokeWidth: 1.9),
        _StrokeIcon(_StrokeIconKind.star, color: t.text, size: 30, strokeWidth: 1.9),
        _StrokeIcon(_StrokeIconKind.dumbbell, color: t.text, size: 30, strokeWidth: 1.9),
        _StrokeIcon(_StrokeIconKind.flame, color: t.text, size: 30, strokeWidth: 1.9),
      ])),
    ]);
  }

  Widget _iconCell(BuildContext context, IconData icon, String name) {
    final t = context.tokens;
    return Column(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 46, height: 46, decoration: BoxDecoration(color: t.surfaceAlt, borderRadius: BorderRadius.circular(14), border: Border.all(color: t.border)), child: Icon(icon, size: 24, color: t.text)),
      const SizedBox(height: 5),
      Text(name, style: TextStyle(fontSize: 9.5, color: t.textTertiary)),
    ]);
  }

  // ════════════════════════════════════════════════════════════════
  //  4 · CARDS & SURFACES
  // ════════════════════════════════════════════════════════════════
  List<Widget> _cards(BuildContext context) {
    return [
      _demoCard(context, title: 'Material SurfaceCard', note: 'tokens.surface + hairline border + soft shadow', child: Column(children: [
        SurfaceCard(padding: const EdgeInsets.all(14), child: Row(children: [
          IconBadge(Icons.receipt_long_rounded, color: AppTheme.brand, size: 40, iconSize: 19),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('INV #1042', style: context.typo.titleSmall),
            Text('12 Aug 2026 · ₹1,499', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          ])),
          const StatusBadge('Paid', color: AppTheme.success),
        ])),
      ])),
      _demoCard(context, title: 'Glass card over gradient', note: 'Frosted translucent surface', child: ClipRRect(
        borderRadius: BorderRadius.circular(22),
        child: SizedBox(
          height: 120,
          child: Stack(children: [
            Positioned.fill(child: Container(decoration: const BoxDecoration(gradient: LinearGradient(colors: [Color(0xFFFF8A3D), Color(0xFF8B5CF6)])))),
            Center(child: GlassCard(radius: 20, blur: 16, tint: Colors.white.withOpacity(.16), border: Border.all(color: Colors.white.withOpacity(.35)), padding: const EdgeInsets.symmetric(horizontal: 26, vertical: 18), child: Text('Glass', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800)))),
          ]),
        ),
      )),
      _demoCard(context, title: 'Gradient card', note: 'fireGradient + white content', child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(20)),
        child: Row(children: [
          const Icon(Icons.workspace_premium_rounded, color: Colors.white, size: 30),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Premium Plan', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800)),
            Text('Unlock all features', style: GoogleFonts.poppins(color: Colors.white.withOpacity(.9), fontSize: 11.5)),
          ])),
        ]),
      )),
      _demoCard(context, title: 'Stat tile', note: 'Compact KPI with icon chip', child: Row(children: [
        _statTile(context, Icons.group_rounded, '342', 'Members', AppTheme.brand),
        const SizedBox(width: 10),
        _statTile(context, Icons.trending_up_rounded, '₹1.2L', 'Revenue', AppTheme.success),
        const SizedBox(width: 10),
        _statTile(context, Icons.event_available_rounded, '126', 'Check-ins', AppTheme.info),
      ])),
      _demoCard(context, title: 'List rows with divider', note: 'Standard settings-style rows', child: Column(children: [
        _row(context, Icons.person_rounded, 'Personal Profile', 'Name, email and phone'),
        Divider(height: 1, color: context.tokens.border),
        _row(context, Icons.lock_rounded, 'Change Password', 'Update login password'),
        Divider(height: 1, color: context.tokens.border),
        _row(context, Icons.notifications_rounded, 'Notifications', 'Push notification preferences'),
      ])),
    ];
  }

  Widget _statTile(BuildContext context, IconData icon, String value, String label, Color color) {
    final t = context.tokens;
    return Expanded(child: Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: t.surface, borderRadius: BorderRadius.circular(18), border: Border.all(color: t.border), boxShadow: context.subtleShadow),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(width: 34, height: 34, decoration: BoxDecoration(color: color.withOpacity(.13), borderRadius: BorderRadius.circular(11)), child: Icon(icon, color: color, size: 17)),
        const SizedBox(height: 9),
        Text(value, style: GoogleFonts.spaceGrotesk(fontSize: 17, fontWeight: FontWeight.w800, color: t.text)),
        Text(label, style: context.typo.labelSmall?.copyWith(color: t.textTertiary)),
      ]),
    ));
  }

  Widget _row(BuildContext context, IconData icon, String title, String subtitle) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(children: [
        Icon(icon, color: context.tokens.textSecondary, size: 20),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(title, style: context.typo.titleSmall),
          Text(subtitle, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11)),
        ])),
        Icon(Icons.chevron_right_rounded, color: context.tokens.textTertiary, size: 18),
      ]),
    );
  }

  // ════════════════════════════════════════════════════════════════
  //  5 · FONTS
  // ════════════════════════════════════════════════════════════════
  Widget _fonts(BuildContext context) {
    final t = context.tokens;
    final entries = <(String, String, TextStyle)>[
      ('Space Grotesk', 'GymXBook display font (headings & numbers)', GoogleFonts.spaceGrotesk(fontSize: 17, fontWeight: FontWeight.w700, color: t.text)),
      ('Poppins', 'GymXBook body font (UI & labels)', GoogleFonts.poppins(fontSize: 15, color: t.text)),
      ('Inter', 'The closest Google font to iOS SF Pro', GoogleFonts.inter(fontSize: 15, color: t.text)),
      ('Figtree', 'SF-like geometric, modern iOS feel', GoogleFonts.figtree(fontSize: 15, color: t.text)),
      ('Manrope', 'Clean geometric sans (very popular)', GoogleFonts.manrope(fontSize: 15, color: t.text)),
      ('DM Sans', 'Friendly, open counters', GoogleFonts.dmSans(fontSize: 15, color: t.text)),
      ('Outfit', 'Rounded-geometric, premium feel', GoogleFonts.outfit(fontSize: 15, color: t.text)),
      ('Sora', 'Techy, elegant', GoogleFonts.sora(fontSize: 15, color: t.text)),
      ('Plus Jakarta Sans', 'Modern UI font', GoogleFonts.plusJakartaSans(fontSize: 15, color: t.text)),
      ('Urbanist', 'Geometric with quirky details', GoogleFonts.urbanist(fontSize: 15, color: t.text)),
      ('Montserrat', 'Wide geometric', GoogleFonts.montserrat(fontSize: 15, color: t.text)),
      ('Nunito', 'Rounded and soft', GoogleFonts.nunito(fontSize: 15, color: t.text)),
      ('Quicksand', 'Rounded, playful', GoogleFonts.quicksand(fontSize: 15, color: t.text)),
      ('Lato', 'Neutral workhorse', GoogleFonts.lato(fontSize: 15, color: t.text)),
      ('Bricolage Grotesque', 'Trendy display for big numbers', GoogleFonts.bricolageGrotesque(fontSize: 17, fontWeight: FontWeight.w700, color: t.text)),
    ];
    return SurfaceCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _label(context, 'Note on iOS fonts'),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(12)),
          child: Text('SF Pro (iOS system font) is not redistributable on Google Fonts. Closest Google alternatives: Inter, Figtree, Manrope. On iOS the system default already renders as SF Pro.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textSecondary, fontSize: 11)),
        ),
        const SizedBox(height: 6),
        for (final e in entries) ...[
          Divider(height: 1, color: context.tokens.border),
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text('Aa Bb 0123456789', style: e.$3)),
                Text(e.$1, style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary)),
              ]),
              const SizedBox(height: 4),
              Text('The quick brown fox jumps over the lazy dog.', style: e.$3.copyWith(fontSize: 13, color: context.tokens.textSecondary)),
              const SizedBox(height: 2),
              Text(e.$2, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 10)),
            ]),
          ),
        ],
      ]),
    );
  }

  // ════════════════════════════════════════════════════════════════
  //  6 · EFFECTS & ANIMATIONS
  // ════════════════════════════════════════════════════════════════
  List<Widget> _effects(BuildContext context) {
    return [
      _demoCard(context, title: 'Gradient border', note: 'Gradient-filled container + inner surface padding', child: Container(
        padding: const EdgeInsets.all(2),
        decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(18)),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: context.tokens.surface, borderRadius: BorderRadius.circular(16)),
          child: Text('Gradient ring border', style: context.typo.titleSmall),
        ),
      )),
      _demoCard(context, title: 'Glow shadow', note: 'Colored box-shadow for neon feel', child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: AppTheme.brand, borderRadius: BorderRadius.circular(18), boxShadow: [BoxShadow(color: AppTheme.brand.withOpacity(.5), blurRadius: 26, offset: const Offset(0, 8))]),
        child: const Text('Glowing', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
      )),
      _demoCard(context, title: 'Shimmer skeleton', note: 'ShimmerBox — loops automatically', child: Column(children: const [
        ShimmerBox(height: 14),
        SizedBox(height: 8),
        ShimmerBox(height: 14, width: 220),
        SizedBox(height: 8),
        ShimmerBox(height: 60, radius: 14),
      ])),
      _demoCard(context, title: 'Count-up number', note: 'CountUp animates value on build', child: Row(children: [
        CountUp(248900, prefix: '₹', style: GoogleFonts.spaceGrotesk(fontSize: 30, fontWeight: FontWeight.w800, color: context.tokens.text)),
        const SizedBox(width: 12),
        const StatusBadge('+12%', color: AppTheme.success, icon: Icons.trending_up_rounded),
      ])),
      _demoCard(context, title: 'Animated progress', note: 'TweenAnimationBuilder 0 → 72% on build', child: TweenAnimationBuilder<double>(
        tween: Tween(begin: 0, end: .72),
        duration: const Duration(milliseconds: 1400),
        curve: Curves.easeOutCubic,
        builder: (context, v, _) {
          return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(value: v, minHeight: 10, backgroundColor: context.tokens.surfaceAlt, color: AppTheme.brand),
            ),
            const SizedBox(height: 6),
            Text('${(v * 100).round()}%', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w800, color: context.tokens.text)),
          ]);
        },
      )),
      _demoCard(context, title: 'Fade-in-up entrance', note: 'FadeInUp with staggered delays (already running on this page)', child: FadeInUp(delayMs: 300, child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: AppTheme.success.withOpacity(.12), borderRadius: BorderRadius.circular(14)),
        child: const Row(children: [Icon(Icons.animation_rounded, color: AppTheme.success), SizedBox(width: 10), Text('Staggered entrance')]),
      ))),
      _demoCard(context, title: 'Badges & chips', note: 'StatusBadge + soft color chips', child: Wrap(spacing: 8, runSpacing: 8, children: const [
        StatusBadge('ACTIVE', color: AppTheme.success),
        StatusBadge('EXPIRING', color: AppTheme.warning),
        StatusBadge('OVERDUE', color: AppTheme.danger),
        StatusBadge('INFO', color: AppTheme.info),
        StatusBadge('PAID', color: AppTheme.success, icon: Icons.check_rounded),
      ])),
    ];
  }

  // ════════════════════════════════════════════════════════════════
  //  7 · iOS COMPONENTS (Cupertino)
  // ════════════════════════════════════════════════════════════════
  List<Widget> _iosComponents(BuildContext context) {
    return [
      _demoCard(context, title: 'Cupertino buttons', note: 'iOS filled · grey · bordered buttons (SF blue)', child: Column(children: [
        CupertinoButton.filled(onPressed: () {}, child: const Text('Filled Button')),
        const SizedBox(height: 8),
        CupertinoButton(color: CupertinoColors.systemGrey5, onPressed: () {}, child: const Text('Grey Button', style: TextStyle(color: CupertinoColors.black))),
        const SizedBox(height: 8),
        CupertinoButton(
          onPressed: () {},
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
            decoration: BoxDecoration(border: Border.all(color: CupertinoColors.activeBlue, width: 1.4), borderRadius: BorderRadius.circular(8)),
            child: const Text('Bordered Button', style: TextStyle(color: CupertinoColors.activeBlue)),
          ),
        ),
      ])),
      _demoCard(context, title: 'Cupertino switch', note: 'iOS toggle · spring-animated', child: const _IosSwitchRow()),
      _demoCard(context, title: 'Cupertino slider', note: 'iOS slider with live value', child: const _IosSliderDemo()),
      _demoCard(context, title: 'Cupertino segmented control', note: 'iOS segmented tabs', child: const _IosSegmentedDemo()),
      _demoCard(context, title: 'Cupertino activity indicator', note: 'Spinning iOS spinner in sizes + colors', child: Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: const [
        CupertinoActivityIndicator(radius: 8),
        CupertinoActivityIndicator(radius: 12, color: CupertinoColors.systemGreen),
        CupertinoActivityIndicator(radius: 16, color: CupertinoColors.systemOrange),
      ])),
      _demoCard(context, title: 'Cupertino text field', note: 'iOS input with prefix icon', child: CupertinoTextField(
        placeholder: 'Full name',
        prefix: const Padding(padding: EdgeInsets.only(left: 10), child: Icon(CupertinoIcons.person, size: 18, color: CupertinoColors.systemGrey)),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(12)),
      )),
      _demoCard(context, title: 'Cupertino search field', note: 'iOS search bar', child: CupertinoSearchTextField(
        placeholder: 'Search members',
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      )),
      _demoCard(context, title: 'iOS stepper (custom)', note: 'CupertinoButtons as +/− controls', child: const _IosStepperDemo()),
      _demoCard(context, title: 'iOS tab bar', note: 'CupertinoTabBar with SF symbols', child: Container(
        decoration: BoxDecoration(color: context.tokens.surface, borderRadius: BorderRadius.circular(16), border: Border.all(color: context.tokens.border)),
        child: CupertinoTabBar(
          currentIndex: 0,
          items: const [
            BottomNavigationBarItem(icon: Icon(CupertinoIcons.house_fill), label: 'Home'),
            BottomNavigationBarItem(icon: Icon(CupertinoIcons.chart_bar_alt_fill), label: 'Stats'),
            BottomNavigationBarItem(icon: Icon(CupertinoIcons.person_2_fill), label: 'Members'),
            BottomNavigationBarItem(icon: Icon(CupertinoIcons.gear_alt_fill), label: 'Settings'),
          ],
        ),
      )),
    ];
  }

  // ════════════════════════════════════════════════════════════════
  //  8 · iOS LISTS & SETTINGS
  // ════════════════════════════════════════════════════════════════
  List<Widget> _iosLists(BuildContext context) {
    return [
      _demoCard(context, title: 'CupertinoListSection.insetGrouped', note: 'Native iOS settings grouped card (header/footer, chevrons, switch)', child: CupertinoListSection.insetGrouped(
        header: const Text('ACCOUNT'),
        footer: const Text('Changes sync automatically across devices.'),
        children: [
          CupertinoListTile(
            leading: const Icon(CupertinoIcons.person_crop_circle, color: CupertinoColors.systemBlue),
            title: const Text('Profile'),
            trailing: const Icon(CupertinoIcons.chevron_right, size: 18, color: CupertinoColors.systemGrey3),
            onTap: () {},
          ),
          CupertinoListTile(
            leading: const Icon(CupertinoIcons.lock, color: CupertinoColors.systemBlue),
            title: const Text('Password'),
            trailing: const Icon(CupertinoIcons.chevron_right, size: 18, color: CupertinoColors.systemGrey3),
            onTap: () {},
          ),
          CupertinoListTile(
            leading: const Icon(CupertinoIcons.bell, color: CupertinoColors.systemRed),
            title: const Text('Notifications'),
            trailing: CupertinoSwitch(value: true, activeColor: CupertinoColors.activeGreen, onChanged: (_) {}),
          ),
        ],
      )),
      _demoCard(context, title: 'iOS settings rows', note: 'Toggle + slider row patterns', child: Column(children: const [
        _IosSwitchRow(),
        Divider(height: 1),
        _IosSwitchRow(initial: true, label: 'Low data mode'),
        Divider(height: 1),
        _IosSliderDemo(),
      ])),
      _demoCard(context, title: 'iOS list tile w/ subtitle', note: 'Two-line settings row', child: CupertinoListTile(
        leading: const Icon(CupertinoIcons.creditcard_fill, color: CupertinoColors.systemGreen),
        title: const Text('Payment methods'),
        subtitle: const Text('2 cards on file'),
        trailing: const Icon(CupertinoIcons.chevron_right, size: 18, color: CupertinoColors.systemGrey3),
        onTap: () {},
      )),
    ];
  }

  // ════════════════════════════════════════════════════════════════
  //  9 · iOS ICON LIBRARY
  // ════════════════════════════════════════════════════════════════
  Widget _iosIconLibrary(BuildContext context) {
    final icons = <(IconData, String)>[
      (CupertinoIcons.house_fill, 'house.fill'),
      (CupertinoIcons.bell_fill, 'bell.fill'),
      (CupertinoIcons.heart_fill, 'heart.fill'),
      (CupertinoIcons.star_fill, 'star.fill'),
      (CupertinoIcons.bolt_fill, 'bolt.fill'),
      (CupertinoIcons.flame_fill, 'flame.fill'),
      (CupertinoIcons.moon_fill, 'moon.fill'),
      (CupertinoIcons.sun_max_fill, 'sun.max.fill'),
      (CupertinoIcons.gear_alt_fill, 'gear.fill'),
      (CupertinoIcons.wifi, 'wifi'),
      (CupertinoIcons.bluetooth, 'bluetooth'),
      (CupertinoIcons.airplane, 'airplane'),
      (CupertinoIcons.battery_full, 'battery.full'),
      (CupertinoIcons.calendar, 'calendar'),
      (CupertinoIcons.camera_fill, 'camera.fill'),
      (CupertinoIcons.clock_fill, 'clock.fill'),
      (CupertinoIcons.folder_fill, 'folder.fill'),
      (CupertinoIcons.lock_fill, 'lock.fill'),
      (CupertinoIcons.mail, 'mail'),
      (CupertinoIcons.mic_fill, 'mic.fill'),
      (CupertinoIcons.music_note_2, 'music'),
      (CupertinoIcons.person_2_fill, 'person.2.fill'),
      (CupertinoIcons.phone_fill, 'phone.fill'),
      (CupertinoIcons.search, 'search'),
      (CupertinoIcons.settings, 'settings'),
      (CupertinoIcons.trash_fill, 'trash.fill'),
      (CupertinoIcons.chart_bar_alt_fill, 'chart.fill'),
      (CupertinoIcons.checkmark_alt_circle_fill, 'checkmark.circle.fill'),
      (CupertinoIcons.add_circled_solid, 'plus.circle.fill'),
      (CupertinoIcons.share, 'share'),
      (CupertinoIcons.qrcode, 'qrcode'),
      (CupertinoIcons.creditcard_fill, 'creditcard.fill'),
      (CupertinoIcons.wand_stars, 'wand.and.stars'),
      (CupertinoIcons.flag_fill, 'flag.fill'),
      (CupertinoIcons.location_fill, 'location.fill'),
      (CupertinoIcons.doc_text_fill, 'doc.text.fill'),
    ];
    return SurfaceCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Cupertino (iOS SF Symbols) icon set', style: context.typo.titleSmall),
        const SizedBox(height: 4),
        Text('SF-Symbol style glyphs shipped with Flutter — 36 samples', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 10.5)),
        const SizedBox(height: 12),
        Wrap(spacing: 14, runSpacing: 14, children: icons.map((e) => _iconCell(context, e.$1, e.$2)).toList()),
      ]),
    );
  }

  // ════════════════════════════════════════════════════════════════
  //  10 · iOS TYPOGRAPHY
  // ════════════════════════════════════════════════════════════════
  Widget _iosTypography(BuildContext context) {
    final t = context.tokens;
    final scale = <(String, String, TextStyle)>[
      ('Large Title', '34 · Regular', GoogleFonts.inter(fontSize: 34, color: t.text)),
      ('Title 1', '28 · Regular', GoogleFonts.inter(fontSize: 28, color: t.text)),
      ('Title 2', '22 · Regular', GoogleFonts.inter(fontSize: 22, color: t.text)),
      ('Title 3', '20 · Regular', GoogleFonts.inter(fontSize: 20, color: t.text)),
      ('Headline', '17 · SemiBold', GoogleFonts.inter(fontSize: 17, fontWeight: FontWeight.w600, color: t.text)),
      ('Body', '17 · Regular', GoogleFonts.inter(fontSize: 17, color: t.text)),
      ('Callout', '16 · Regular', GoogleFonts.inter(fontSize: 16, color: t.text)),
      ('Subheadline', '15 · Regular', GoogleFonts.inter(fontSize: 15, color: t.text)),
      ('Footnote', '13 · Regular', GoogleFonts.inter(fontSize: 13, color: t.textSecondary)),
      ('Caption 1', '12 · Regular', GoogleFonts.inter(fontSize: 12, color: t.textSecondary)),
      ('Caption 2', '11 · Regular', GoogleFonts.inter(fontSize: 11, color: t.textTertiary)),
    ];
    return SurfaceCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('iOS (SF) type scale', style: context.typo.titleSmall),
        const SizedBox(height: 2),
        Text('Sample in Inter — the closest Google alternative to SF Pro', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 10.5)),
        const SizedBox(height: 6),
        for (final e in scale) ...[
          Divider(height: 1, color: context.tokens.border),
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 10),
            child: Row(crossAxisAlignment: CrossAxisAlignment.baseline, textBaseline: TextBaseline.alphabetic, children: [
              Expanded(child: Text('Sample Text', style: e.$3, maxLines: 1, overflow: TextOverflow.ellipsis)),
              Text(e.$1, style: context.typo.labelSmall?.copyWith(color: context.tokens.textTertiary)),
              const SizedBox(width: 8),
              Text(e.$2, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 9.5)),
            ]),
          ),
        ],
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: context.tokens.surfaceAlt, borderRadius: BorderRadius.circular(12)),
          child: Text('SF Pro / SF Pro Rounded / New York are Apple-only and cannot be bundled. iOS devices render the system font automatically; on Android use Inter (closest), Figtree or Manrope.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textSecondary, fontSize: 11)),
        ),
      ]),
    );
  }

  // ════════════════════════════════════════════════════════════════
  //  11 · iOS MOTION & EFFECTS
  // ════════════════════════════════════════════════════════════════
  List<Widget> _iosMotion(BuildContext context) {
    return [
      _demoCard(context, title: 'iOS wheel picker', note: 'ListWheelScrollView — native scrolling wheel, no package', child: const _IosWheelPickerDemo()),
      _demoCard(context, title: 'iOS page transition', note: 'CupertinoPageRoute — horizontal slide + swipe-back', child: CupertinoButton.filled(
        onPressed: () => Navigator.of(context).push(CupertinoPageRoute(builder: (_) => const _IosSamplePage())),
        child: const Text('Push iOS page →'),
      )),
      _demoCard(context, title: 'Bouncing scroll physics', note: 'iOS rubber-band overscroll', child: SizedBox(
        height: 60,
        child: ListView.builder(
          physics: const BouncingScrollPhysics(),
          scrollDirection: Axis.horizontal,
          itemCount: 10,
          itemBuilder: (_, i) => Container(
            width: 90,
            margin: const EdgeInsets.only(right: 8),
            alignment: Alignment.center,
            decoration: BoxDecoration(color: i.isEven ? AppTheme.brand.withOpacity(.85) : CupertinoColors.systemGrey4, borderRadius: BorderRadius.circular(14)),
            child: Text('Card $i', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
          ),
        ),
      )),
      _demoCard(context, title: 'iOS switch toggle', note: 'Spring-animated state change', child: const _IosSwitchRow(initial: true)),
      _demoCard(context, title: 'Cupertino spinner states', note: 'Loading indicators', child: Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: const [
        CupertinoActivityIndicator(radius: 8, color: CupertinoColors.systemGrey),
        CupertinoActivityIndicator(radius: 12),
        CupertinoActivityIndicator(radius: 18, color: CupertinoColors.systemOrange),
      ])),
    ];
  }

  // ════════════════════════════════════════════════════════════════
  //  12 · iOS ALERTS, SHEETS & PICKERS
  // ════════════════════════════════════════════════════════════════
  List<Widget> _iosAlertsSheets(BuildContext context) {
    return [
      _demoCard(context, title: 'Cupertino alert dialog', note: 'iOS two-button alert', child: CupertinoButton.filled(onPressed: () => _showIosAlert(context), child: const Text('Show alert'))),
      _demoCard(context, title: 'Cupertino action sheet', note: 'iOS bottom sheet with destructive + cancel', child: CupertinoButton.filled(onPressed: () => _showIosActionSheet(context), child: const Text('Show action sheet'))),
      _demoCard(context, title: 'Cupertino date picker', note: 'Inline date wheel', child: SizedBox(height: 180, child: CupertinoDatePicker(mode: CupertinoDatePickerMode.date, initialDateTime: DateTime(2026, 8, 17), onDateTimeChanged: (_) {}))),
      _demoCard(context, title: 'Cupertino time picker', note: 'Inline time wheel (12h)', child: SizedBox(height: 180, child: CupertinoDatePicker(mode: CupertinoDatePickerMode.time, initialDateTime: DateTime(2026, 8, 17, 7, 30), use24hFormat: false, onDateTimeChanged: (_) {}))),
      _demoCard(context, title: 'Cupertino picker (list)', note: 'Generic scroll list picker', child: SizedBox(height: 140, child: CupertinoPicker(
        itemExtent: 32,
        onSelectedItemChanged: (_) {},
        children: List.generate(12, (i) => Center(child: Text('Option ${i + 1}'))),
      ))),
    ];
  }

  void _showIosAlert(BuildContext context) {
    showCupertinoDialog(
      context: context,
      builder: (ctx) => CupertinoAlertDialog(
        title: const Text('Delete Member?'),
        content: const Text('This will permanently remove the member and all their data.'),
        actions: [
          CupertinoDialogAction(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          CupertinoDialogAction(isDestructiveAction: true, onPressed: () => Navigator.pop(ctx), child: const Text('Delete')),
        ],
      ),
    );
  }

  void _showIosActionSheet(BuildContext context) {
    showCupertinoModalPopup(
      context: context,
      builder: (ctx) => CupertinoActionSheet(
        title: const Text('Choose action'),
        actions: [
          CupertinoActionSheetAction(onPressed: () => Navigator.pop(ctx), child: const Text('Renew membership')),
          CupertinoActionSheetAction(onPressed: () => Navigator.pop(ctx), child: const Text('Freeze membership')),
          CupertinoActionSheetAction(isDestructiveAction: true, onPressed: () => Navigator.pop(ctx), child: const Text('Delete member')),
        ],
        cancelButton: CupertinoActionSheetAction(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
      ),
    );
  }

  // ════════════════════════════════════════════════════════════════
  //  13 · MEMBER HEROES — DARK ×5 (same data, different layouts)
  // ════════════════════════════════════════════════════════════════
  List<Widget> _memberHeroes(BuildContext context) {
    return [
      _demoCard(context, title: 'Member A · Split + stats', note: 'Avatar left · KPI strip · contact pills', child: _memberCardA(context)),
      _demoCard(context, title: 'Member B · Aurora + big name', note: 'Radial glows · 40px display name · round contact', child: _memberCardB(context)),
      _demoCard(context, title: 'Member C · Membership progress', note: 'Plan progress bar · days remaining', child: _memberCardC(context)),
      _demoCard(context, title: 'Member D · Progress-ring avatar', note: 'Circular ring around avatar · compact', child: _memberCardD(context)),
      _demoCard(context, title: 'Member E · Minimal rows', note: 'Hairline detail rows · flat dark', child: _memberCardE(context)),
    ];
  }

  Widget _contactPill(IconData icon, String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(color: color.withOpacity(.16), borderRadius: BorderRadius.circular(12), border: Border.all(color: color.withOpacity(.35))),
      child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(icon, size: 16, color: color),
        const SizedBox(width: 6),
        Text(label, style: GoogleFonts.poppins(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12)),
      ]),
    );
  }

  Widget _mRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7),
      child: Row(children: [
        SizedBox(width: 72, child: Text(label.toUpperCase(), style: GoogleFonts.poppins(color: Colors.white38, fontSize: 10, fontWeight: FontWeight.w600, letterSpacing: 1.1))),
        Expanded(child: Text(value, style: GoogleFonts.poppins(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600))),
      ]),
    );
  }

  Widget _memberCardA(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF030303), Color(0xFF15151A), Color(0xFF0B1624)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(children: [
        Row(children: [
          Container(
            padding: const EdgeInsets.all(3),
            decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(18), boxShadow: [BoxShadow(color: AppTheme.brand.withOpacity(.45), blurRadius: 18)]),
            child: Container(width: 52, height: 52, decoration: BoxDecoration(color: const Color(0xFF17171C), borderRadius: BorderRadius.circular(15)), child: const Icon(Icons.person_rounded, color: Colors.white, size: 28)),
          ),
          const SizedBox(width: 13),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Rahul Sharma', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 3),
            Text('+91 98123 45678', style: GoogleFonts.poppins(color: Colors.white70, fontSize: 12)),
            const SizedBox(height: 9),
            Wrap(spacing: 6, runSpacing: 6, children: [
              _pill(Icons.verified_rounded, 'Active', AppTheme.success),
              _pill(Icons.card_membership_rounded, 'Gold Plan', AppTheme.brandAmber),
            ]),
          ])),
        ]),
        const SizedBox(height: 16),
        Row(children: [
          _stat(Icons.fact_check_rounded, '18', 'Attendance'),
          const SizedBox(width: 10),
          _stat(Icons.receipt_long_rounded, '3', 'Invoices'),
          const SizedBox(width: 10),
          _stat(Icons.swap_horiz_rounded, '4', 'Payments'),
        ]),
        const SizedBox(height: 16),
        Row(children: [
          Expanded(child: _contactPill(Icons.call_rounded, 'Call', AppTheme.success)),
          const SizedBox(width: 10),
          Expanded(child: _contactPill(Icons.chat_rounded, 'WhatsApp', const Color(0xFF25D366))),
          const SizedBox(width: 10),
          _pill(Icons.event_rounded, '24 Aug 2026', Colors.white70),
        ]),
      ]),
    );
  }

  Widget _memberCardB(BuildContext context) {
    return Container(
      height: 200,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF241A15), Color(0xFF1A1210), Color(0xFF120C0A)]),
        borderRadius: BorderRadius.circular(26),
      ),
      child: Stack(children: [
        Positioned(top: -60, right: -40, child: Container(width: 180, height: 180, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [AppTheme.brand.withOpacity(.5), Colors.transparent])))),
        Positioned(bottom: -70, left: -50, child: Container(width: 190, height: 190, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [AppTheme.brandDeep.withOpacity(.4), Colors.transparent])))),
        Padding(
          padding: const EdgeInsets.all(20),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Container(width: 40, height: 40, decoration: BoxDecoration(color: Colors.white.withOpacity(.08), borderRadius: BorderRadius.circular(12), border: Border.all(color: Colors.white.withOpacity(.1))), child: const Icon(Icons.person_rounded, color: Colors.white, size: 22)),
              const SizedBox(width: 10),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Rahul Sharma', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w800)),
                Text('+91 98123 45678', style: GoogleFonts.poppins(color: Colors.white54, fontSize: 11)),
              ])),
              _pill(Icons.verified_rounded, 'Active', AppTheme.success),
            ]),
            const Spacer(),
            Text('Rahul', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w800, height: 1)),
            const SizedBox(height: 6),
            Wrap(spacing: 6, runSpacing: 6, children: [
              _pill(Icons.card_membership_rounded, 'Gold Plan', AppTheme.brandAmber),
              _pill(Icons.event_rounded, '24 Aug 2026', Colors.white70),
            ]),
            const Spacer(),
            Row(children: [
              _roundBtn(Icons.call_rounded, AppTheme.success),
              const SizedBox(width: 12),
              _roundBtn(Icons.chat_rounded, const Color(0xFF25D366)),
              const Spacer(),
              Text('Membership active', style: GoogleFonts.poppins(color: Colors.white38, fontSize: 10.5)),
            ]),
          ]),
        ),
      ]),
    );
  }

  Widget _memberCardC(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF101318), Color(0xFF0A0D12)], begin: Alignment.topCenter, end: Alignment.bottomCenter),
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            padding: const EdgeInsets.all(3),
            decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: AppTheme.brandAmber.withOpacity(.9), width: 2)),
            child: Container(width: 46, height: 46, decoration: const BoxDecoration(color: Color(0xFF23212B), shape: BoxShape.circle), child: const Icon(Icons.person_rounded, color: Colors.white, size: 24)),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Rahul Sharma', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800)),
            Text('+91 98123 45678', style: GoogleFonts.poppins(color: Colors.white60, fontSize: 11.5)),
          ])),
          _pill(Icons.verified_rounded, 'Active', AppTheme.success),
        ]),
        const SizedBox(height: 16),
        Row(children: [
          Text('Gold Plan', style: GoogleFonts.poppins(color: AppTheme.brandAmber, fontSize: 12.5, fontWeight: FontWeight.w700)),
          const Spacer(),
          Text('Expires 24 Aug 2026', style: GoogleFonts.poppins(color: Colors.white60, fontSize: 11)),
        ]),
        const SizedBox(height: 8),
        ClipRRect(borderRadius: BorderRadius.circular(6), child: LinearProgressIndicator(value: 0.72, minHeight: 8, backgroundColor: Colors.white.withOpacity(.12), color: AppTheme.brand)),
        const SizedBox(height: 6),
        Text('18 days remaining · 72% of plan used', style: GoogleFonts.poppins(color: Colors.white54, fontSize: 10.5)),
        const SizedBox(height: 14),
        Row(children: [
          Expanded(child: _contactPill(Icons.call_rounded, 'Call', AppTheme.success)),
          const SizedBox(width: 10),
          Expanded(child: _contactPill(Icons.chat_rounded, 'WhatsApp', const Color(0xFF25D366))),
        ]),
      ]),
    );
  }

  Widget _memberCardD(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF17141F), Color(0xFF0C0A12)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(children: [
        Row(children: [
          _RingProgress(
            value: 0.72,
            child: Container(width: 60, height: 60, decoration: const BoxDecoration(color: Color(0xFF23212B), shape: BoxShape.circle), child: const Icon(Icons.person_rounded, color: Colors.white, size: 30)),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Rahul Sharma', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 3),
            Text('+91 98123 45678', style: GoogleFonts.poppins(color: Colors.white60, fontSize: 12)),
            const SizedBox(height: 8),
            Wrap(spacing: 6, runSpacing: 6, children: [
              _pill(Icons.verified_rounded, 'Active', AppTheme.success),
              _pill(Icons.card_membership_rounded, 'Gold Plan', AppTheme.brandAmber),
            ]),
          ])),
        ]),
        const SizedBox(height: 16),
        Row(children: [
          Expanded(child: _contactPill(Icons.call_rounded, 'Call', AppTheme.success)),
          const SizedBox(width: 10),
          Expanded(child: _contactPill(Icons.chat_rounded, 'WhatsApp', const Color(0xFF25D366))),
        ]),
        const SizedBox(height: 10),
        Text('Expires 24 Aug 2026 · 18 days left', style: GoogleFonts.poppins(color: Colors.white54, fontSize: 11)),
      ]),
    );
  }

  Widget _memberCardE(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF14161C), Color(0xFF0B0D11)], begin: Alignment.topCenter, end: Alignment.bottomCenter),
        borderRadius: BorderRadius.circular(26),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(width: 44, height: 44, decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(14)), child: const Icon(Icons.person_rounded, color: Colors.white, size: 24)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Rahul Sharma', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800)),
            Text('+91 98123 45678', style: GoogleFonts.poppins(color: Colors.white60, fontSize: 11)),
          ])),
          _pill(Icons.verified_rounded, 'Active', AppTheme.success),
        ]),
        const SizedBox(height: 8),
        _mRow('Plan', 'Gold Plan'),
        _mRow('Expires', '24 Aug 2026'),
        const SizedBox(height: 6),
        Row(children: [
          Expanded(child: _contactPill(Icons.call_rounded, 'Call', AppTheme.success)),
          const SizedBox(width: 10),
          Expanded(child: _contactPill(Icons.chat_rounded, 'WhatsApp', const Color(0xFF25D366))),
        ]),
      ]),
    );
  }

  // ════════════════════════════════════════════════════════════════
  //  14 · FESTIVAL GREETING CARDS (live animations)
  //  Future: greeting + theme driven from Super Admin (occasion/festival).
  // ════════════════════════════════════════════════════════════════
  List<Widget> _festivalCards(BuildContext context) {
    return [
      _demoCard(context, title: 'Independence Day', note: 'Tricolor stripe · spinning Ashoka chakra · greeting', child: const _FestivalCard(_FestivalKind.independence)),
      _demoCard(context, title: 'Diwali', note: 'Rising golden sparks · flickering diyas · greeting', child: const _FestivalCard(_FestivalKind.diwali)),
      _demoCard(context, title: 'Dussehra', note: 'Ember particles · glowing sun · greeting', child: const _FestivalCard(_FestivalKind.dussehra)),
      _demoCard(context, title: 'Holi', note: 'Colour splashes · falling confetti · greeting', child: const _FestivalCard(_FestivalKind.holi)),
      _demoCard(context, title: 'New Year', note: 'Fireworks bursts · welcome 2027 · greeting', child: const _FestivalCard(_FestivalKind.newYear)),
    ];
  }
}

// ════════════════════════════════════════════════════════════════════
//  Lucide-style stroke icon (CustomPaint, no external package)
// ════════════════════════════════════════════════════════════════════
enum _StrokeIconKind { heart, search, star, dumbbell, flame, refreshCw, pencil, apple, moreH }

class _StrokeIcon extends StatelessWidget {
  final _StrokeIconKind kind;
  final Color color;
  final double size;
  final double strokeWidth;
  const _StrokeIcon(this.kind, {required this.color, this.size = 24, this.strokeWidth = 1.9});

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      size: Size.square(size),
      painter: _StrokeIconPainter(_path(kind, size), color, strokeWidth, fill: kind == _StrokeIconKind.moreH),
    );
  }

  Path _path(_StrokeIconKind k, double s) {
    final p = Path();
    switch (k) {
      case _StrokeIconKind.heart:
        p.moveTo(s * .5, s * .88);
        p.cubicTo(s * .5, s * .88, s * .1, s * .62, s * .1, s * .36);
        p.cubicTo(s * .1, s * .2, s * .24, s * .12, s * .37, s * .12);
        p.cubicTo(s * .43, s * .12, s * .5, s * .16, s * .5, s * .21);
        p.cubicTo(s * .5, s * .16, s * .57, s * .12, s * .63, s * .12);
        p.cubicTo(s * .76, s * .12, s * .9, s * .2, s * .9, s * .36);
        p.cubicTo(s * .9, s * .62, s * .5, s * .88, s * .5, s * .88);
        p.close();
        break;
      case _StrokeIconKind.search:
        p.addOval(Rect.fromCircle(center: Offset(s * .42, s * .42), radius: s * .26));
        p.moveTo(s * .62, s * .62);
        p.lineTo(s * .86, s * .86);
        break;
      case _StrokeIconKind.star:
        final cx = s / 2, cy = s / 2, outer = s * .47, inner = s * .19;
        for (var i = 0; i < 10; i++) {
          final r = i.isEven ? outer : inner;
          final a = -math.pi / 2 + i * math.pi / 5;
          final x = cx + r * math.cos(a), y = cy + r * math.sin(a);
          i == 0 ? p.moveTo(x, y) : p.lineTo(x, y);
        }
        p.close();
        break;
      case _StrokeIconKind.dumbbell:
        p.addRRect(RRect.fromRectAndRadius(Rect.fromLTWH(s * .04, s * .34, s * .16, s * .32), const Radius.circular(4)));
        p.addRRect(RRect.fromRectAndRadius(Rect.fromLTWH(s * .8, s * .34, s * .16, s * .32), const Radius.circular(4)));
        p.moveTo(s * .2, s * .42);
        p.lineTo(s * .42, s * .42);
        p.moveTo(s * .58, s * .42);
        p.lineTo(s * .8, s * .42);
        p.moveTo(s * .2, s * .58);
        p.lineTo(s * .42, s * .58);
        p.moveTo(s * .58, s * .58);
        p.lineTo(s * .8, s * .58);
        p.moveTo(s * .42, s * .34);
        p.lineTo(s * .42, s * .66);
        p.moveTo(s * .58, s * .34);
        p.lineTo(s * .58, s * .66);
        break;
      case _StrokeIconKind.flame:
        p.moveTo(s * .5, s * .06);
        p.cubicTo(s * .34, s * .26, s * .2, s * .38, s * .2, s * .56);
        p.cubicTo(s * .2, s * .74, s * .34, s * .9, s * .5, s * .9);
        p.cubicTo(s * .66, s * .9, s * .8, s * .74, s * .8, s * .56);
        p.cubicTo(s * .8, s * .44, s * .7, s * .36, s * .56, s * .32);
        p.cubicTo(s * .58, s * .4, s * .54, s * .44, s * .48, s * .44);
        p.cubicTo(s * .4, s * .44, s * .36, s * .38, s * .38, s * .3);
        p.cubicTo(s * .42, s * .2, s * .46, s * .12, s * .5, s * .06);
        p.close();
        break;
      case _StrokeIconKind.refreshCw:
        final c = Offset(s * .5, s * .5);
        final r = s * .34;
        p.addArc(Rect.fromCircle(center: c, radius: r), -math.pi / 2, math.pi * 1.8);
        final tip = Offset(c.dx, c.dy - r);
        p.moveTo(tip.dx - s * .11, tip.dy + s * .03);
        p.lineTo(tip.dx, tip.dy);
        p.lineTo(tip.dx + s * .10, tip.dy + s * .07);
        break;
      case _StrokeIconKind.pencil:
        final k = s / 24;
        p.moveTo(16.4 * k, 3.0 * k);
        p.cubicTo(19.6 * k, 1.9 * k, 21.6 * k, 3.5 * k, 21.0 * k, 6.6 * k);
        p.lineTo(7.5 * k, 20.5 * k);
        p.lineTo(2.0 * k, 22.0 * k);
        p.lineTo(3.5 * k, 16.5 * k);
        p.close();
        p.moveTo(15.0 * k, 5.0 * k);
        p.lineTo(19.0 * k, 9.0 * k);
        break;
      case _StrokeIconKind.apple:
        p.addOval(Rect.fromCircle(center: Offset(s * .5, s * .64), radius: s * .32));
        p.moveTo(s * .5, s * .33);
        p.lineTo(s * .5, s * .18);
        p.moveTo(s * .5, s * .24);
        p.quadraticBezierTo(s * .57, s * .15, s * .63, s * .21);
        p.quadraticBezierTo(s * .57, s * .27, s * .5, s * .29);
        break;
      case _StrokeIconKind.moreH:
        final rd = s * .055;
        p.addOval(Rect.fromCircle(center: Offset(s * .25, s * .5), radius: rd));
        p.addOval(Rect.fromCircle(center: Offset(s * .5, s * .5), radius: rd));
        p.addOval(Rect.fromCircle(center: Offset(s * .75, s * .5), radius: rd));
        break;
    }
    return p;
  }
}

class _StrokeIconPainter extends CustomPainter {
  final Path path;
  final Color color;
  final double strokeWidth;
  final bool fill;
  _StrokeIconPainter(this.path, this.color, this.strokeWidth, {this.fill = false});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..style = fill ? PaintingStyle.fill : PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(covariant _StrokeIconPainter oldDelegate) =>
      oldDelegate.color != color || oldDelegate.strokeWidth != strokeWidth || oldDelegate.path != path || oldDelegate.fill != fill;
}

// ════════════════════════════════════════════════════════════════════
//  Interactive iOS demo widgets
// ════════════════════════════════════════════════════════════════════
class _IosSwitchRow extends StatefulWidget {
  final bool initial;
  final String label;
  const _IosSwitchRow({this.initial = false, this.label = 'Enable feature'});
  @override
  State<_IosSwitchRow> createState() => _IosSwitchRowState();
}

class _IosSwitchRowState extends State<_IosSwitchRow> {
  late bool value = widget.initial;
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(children: [
        Expanded(child: Text(widget.label, style: context.typo.titleSmall)),
        CupertinoSwitch(value: value, activeColor: CupertinoColors.activeGreen, onChanged: (v) => setState(() => value = v)),
      ]),
    );
  }
}

class _IosSliderDemo extends StatefulWidget {
  const _IosSliderDemo();
  @override
  State<_IosSliderDemo> createState() => _IosSliderDemoState();
}

class _IosSliderDemoState extends State<_IosSliderDemo> {
  double value = 0.4;
  @override
  Widget build(BuildContext context) {
    return Row(children: [
      const Icon(CupertinoIcons.speaker, size: 20, color: CupertinoColors.systemGrey),
      Expanded(child: CupertinoSlider(value: value, onChanged: (v) => setState(() => value = v))),
      Text('${(value * 100).round()}%', style: context.typo.labelMedium),
    ]);
  }
}

class _IosSegmentedDemo extends StatefulWidget {
  const _IosSegmentedDemo();
  @override
  State<_IosSegmentedDemo> createState() => _IosSegmentedDemoState();
}

class _IosSegmentedDemoState extends State<_IosSegmentedDemo> {
  int value = 0;
  @override
  Widget build(BuildContext context) {
    return CupertinoSegmentedControl<int>(
      selectedColor: CupertinoColors.activeBlue,
      groupValue: value,
      children: const {
        0: Padding(padding: EdgeInsets.symmetric(horizontal: 8), child: Text('Active')),
        1: Padding(padding: EdgeInsets.symmetric(horizontal: 8), child: Text('Expiring')),
        2: Padding(padding: EdgeInsets.symmetric(horizontal: 8), child: Text('Expired')),
      },
      onValueChanged: (v) => setState(() => value = v),
    );
  }
}

class _IosStepperDemo extends StatefulWidget {
  const _IosStepperDemo();
  @override
  State<_IosStepperDemo> createState() => _IosStepperDemoState();
}

class _IosStepperDemoState extends State<_IosStepperDemo> {
  int value = 1;
  @override
  Widget build(BuildContext context) {
    return Row(mainAxisAlignment: MainAxisAlignment.center, children: [
      CupertinoButton(
        padding: EdgeInsets.zero,
        onPressed: () => setState(() { if (value > 0) value--; }),
        child: Container(width: 32, height: 32, alignment: Alignment.center, decoration: const BoxDecoration(color: CupertinoColors.systemGrey5, shape: BoxShape.circle), child: const Icon(CupertinoIcons.minus, size: 18, color: CupertinoColors.black)),
      ),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: Text('$value', style: GoogleFonts.spaceGrotesk(fontSize: 24, fontWeight: FontWeight.w700, color: context.tokens.text)),
      ),
      CupertinoButton(
        padding: EdgeInsets.zero,
        onPressed: () => setState(() { if (value < 99) value++; }),
        child: Container(width: 32, height: 32, alignment: Alignment.center, decoration: const BoxDecoration(color: CupertinoColors.systemGrey5, shape: BoxShape.circle), child: const Icon(CupertinoIcons.plus, size: 18, color: CupertinoColors.black)),
      ),
    ]);
  }
}

class _IosWheelPickerDemo extends StatefulWidget {
  const _IosWheelPickerDemo();
  @override
  State<_IosWheelPickerDemo> createState() => _IosWheelPickerDemoState();
}

class _IosWheelPickerDemoState extends State<_IosWheelPickerDemo> {
  int selected = 3;
  @override
  Widget build(BuildContext context) {
    final t = context.tokens;
    return Column(children: [
      SizedBox(
        height: 120,
        child: ListWheelScrollView.useDelegate(
          itemExtent: 36,
          physics: const FixedExtentScrollPhysics(),
          overAndUnderCenterOpacity: 0.35,
          perspective: 0.003,
          diameterRatio: 1.8,
          onSelectedItemChanged: (i) => setState(() => selected = i),
          childDelegate: ListWheelChildBuilderDelegate(
            builder: (context, i) => Center(
              child: Text('${i + 1}',
                  style: GoogleFonts.inter(fontSize: 18, fontWeight: i == selected ? FontWeight.w600 : FontWeight.w400, color: t.text)),
            ),
            childCount: 24,
          ),
        ),
      ),
      const SizedBox(height: 6),
      Text('Selected: ${selected + 1}', style: context.typo.bodySmall?.copyWith(color: t.textTertiary)),
    ]);
  }
}

/// Sample page pushed via CupertinoPageRoute to demo the iOS slide transition.
class _IosSamplePage extends StatelessWidget {
  const _IosSamplePage();
  @override
  Widget build(BuildContext context) {
    return CupertinoPageScaffold(
      navigationBar: CupertinoNavigationBar(
        middle: const Text('iOS Page'),
        leading: CupertinoNavigationBarBackButton(onPressed: () => Navigator.pop(context)),
        trailing: const Icon(CupertinoIcons.ellipsis),
      ),
      child: SafeArea(
        child: Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(CupertinoIcons.sparkles, size: 44, color: CupertinoColors.systemOrange),
            const SizedBox(height: 12),
            Text('Pushed with CupertinoPageRoute', style: context.typo.titleMedium),
            const SizedBox(height: 4),
            Text('Swipe from the left edge to go back.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
          ]),
        ),
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════════════
//  Circular progress ring (for member card D)
// ════════════════════════════════════════════════════════════════════
class _RingProgress extends StatelessWidget {
  final double value;
  final Widget child;
  final double size;
  final Color? color;
  const _RingProgress({required this.value, required this.child, this.size = 76, this.color});

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      painter: _RingPainter(value: value, color: color ?? AppTheme.brand, track: Colors.white.withOpacity(.14), width: 5),
      child: SizedBox(width: size, height: size, child: Center(child: child)),
    );
  }
}

class _RingPainter extends CustomPainter {
  final double value;
  final Color color;
  final Color track;
  final double width;
  _RingPainter({required this.value, required this.color, required this.track, required this.width});

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final radius = (size.width - width) / 2;
    final trackPaint = Paint()..style = PaintingStyle.stroke..strokeWidth = width..color = track..strokeCap = StrokeCap.round;
    canvas.drawCircle(center, radius, trackPaint);
    final sweep = math.pi * 2 * value.clamp(0.0, 1.0);
    final arcPaint = Paint()..style = PaintingStyle.stroke..strokeWidth = width..color = color..strokeCap = StrokeCap.round;
    canvas.drawArc(Rect.fromCircle(center: center, radius: radius), -math.pi / 2, sweep, false, arcPaint);
  }

  @override
  bool shouldRepaint(covariant _RingPainter old) => old.value != value || old.color != color;
}

// ════════════════════════════════════════════════════════════════════
//  Festival greeting cards — live animations
// ════════════════════════════════════════════════════════════════════
enum _FestivalKind { independence, diwali, dussehra, holi, newYear }

class _FestivalCard extends StatefulWidget {
  final _FestivalKind kind;
  const _FestivalCard(this.kind, {super.key});
  @override
  State<_FestivalCard> createState() => _FestivalCardState();
}

class _FestivalCardState extends State<_FestivalCard> with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl = AnimationController(vsync: this, duration: const Duration(seconds: 6))..repeat();

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final anim = CurvedAnimation(parent: _ctrl, curve: Curves.linear);
    return ClipRRect(
      borderRadius: BorderRadius.circular(26),
      child: SizedBox(
        height: 176,
        child: Stack(children: [
          Positioned.fill(child: Container(decoration: BoxDecoration(gradient: _bg))),
          ..._decor(anim),
          Positioned.fill(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(_greeting, style: GoogleFonts.poppins(color: Colors.white.withOpacity(.82), fontSize: 11.5, fontWeight: FontWeight.w500)),
                const Spacer(),
                Text(_title, style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
                const SizedBox(height: 3),
                Text(_subtitle, style: GoogleFonts.poppins(color: Colors.white.withOpacity(.66), fontSize: 11.5)),
              ]),
            ),
          ),
        ]),
      ),
    );
  }

  String get _greeting {
    switch (widget.kind) {
      case _FestivalKind.diwali:
      case _FestivalKind.newYear:
        return 'Good Evening, Varun';
      default:
        return 'Good Morning, Varun';
    }
  }

  String get _title {
    switch (widget.kind) {
      case _FestivalKind.independence: return 'Happy Independence Day';
      case _FestivalKind.diwali: return 'Happy Diwali';
      case _FestivalKind.dussehra: return 'Happy Dussehra';
      case _FestivalKind.holi: return 'Happy Holi';
      case _FestivalKind.newYear: return 'Happy New Year';
    }
  }

  String get _subtitle {
    switch (widget.kind) {
      case _FestivalKind.independence: return '15 Aug · Jai Hind';
      case _FestivalKind.diwali: return 'May your year shine bright';
      case _FestivalKind.dussehra: return 'Victory of good over evil';
      case _FestivalKind.holi: return 'Splash a little colour today';
      case _FestivalKind.newYear: return 'Welcome 2027';
    }
  }

  LinearGradient get _bg {
    switch (widget.kind) {
      case _FestivalKind.independence:
        return const LinearGradient(colors: [Color(0xFF0A1233), Color(0xFF101F4D)], begin: Alignment.topLeft, end: Alignment.bottomRight);
      case _FestivalKind.diwali:
        return const LinearGradient(colors: [Color(0xFF2A1200), Color(0xFF0F0600)], begin: Alignment.topCenter, end: Alignment.bottomCenter);
      case _FestivalKind.dussehra:
        return const LinearGradient(colors: [Color(0xFFB3410E), Color(0xFF6E1C05)], begin: Alignment.topLeft, end: Alignment.bottomRight);
      case _FestivalKind.holi:
        return const LinearGradient(colors: [Color(0xFF1A0B24), Color(0xFF0D0614)], begin: Alignment.topCenter, end: Alignment.bottomCenter);
      case _FestivalKind.newYear:
        return const LinearGradient(colors: [Color(0xFF0B1026), Color(0xFF231A52)], begin: Alignment.topCenter, end: Alignment.bottomCenter);
    }
  }

  List<Widget> _decor(Animation<double> t) {
    switch (widget.kind) {
      case _FestivalKind.independence:
        return [
          Positioned(left: 0, right: 0, top: 0, child: Container(height: 7, decoration: const BoxDecoration(gradient: LinearGradient(colors: [Color(0xFFFF9933), Color(0xFFFFFFFF), Color(0xFF138808)])))),
          Positioned(right: 18, top: 26, child: _SpinningChakra(t: t, size: 74)),
        ];
      case _FestivalKind.diwali:
        return [
          Positioned.fill(child: _ParticleField(t: t, colors: const [Color(0xFFFFC24B), Color(0xFFFF9A3D), Color(0xFFFFE29A)], mode: _ParticleMode.rise, count: 26, seed: 7)),
          Positioned(left: 20, bottom: 14, child: Row(children: [
            _Flicker(t: t, phase: 0, icon: Icons.local_fire_department_rounded, color: const Color(0xFFFFB020), size: 26),
            const SizedBox(width: 10),
            _Flicker(t: t, phase: 1.3, icon: Icons.local_fire_department_rounded, color: const Color(0xFFFF8A3D), size: 32),
            const SizedBox(width: 10),
            _Flicker(t: t, phase: 2.5, icon: Icons.local_fire_department_rounded, color: const Color(0xFFFFB020), size: 26),
          ])),
        ];
      case _FestivalKind.dussehra:
        return [
          Positioned.fill(child: _ParticleField(t: t, colors: const [Color(0xFFFFB020), Color(0xFFFF6B2C), Color(0xFFFFE082)], mode: _ParticleMode.rise, count: 22, seed: 11)),
          Positioned(right: 20, top: 22, child: _Flicker(t: t, phase: 0, icon: Icons.wb_sunny_rounded, color: const Color(0xFFFFD54F), size: 40)),
        ];
      case _FestivalKind.holi:
        return [
          Positioned(right: 24, top: 22, child: Container(width: 90, height: 90, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [const Color(0xFFFF3D81).withOpacity(.55), Colors.transparent])))),
          Positioned(right: 60, bottom: -20, child: Container(width: 110, height: 110, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [const Color(0xFFFFD23D).withOpacity(.5), Colors.transparent])))),
          Positioned(left: 60, bottom: 10, child: Container(width: 90, height: 90, decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [const Color(0xFF2ECC71).withOpacity(.45), Colors.transparent])))),
          Positioned.fill(child: _ParticleField(t: t, colors: const [Color(0xFFFF3D81), Color(0xFFFFD23D), Color(0xFF2ECC71), Color(0xFF3B9EFF)], mode: _ParticleMode.fall, count: 30, seed: 5)),
        ];
      case _FestivalKind.newYear:
        return [
          Positioned.fill(child: _ParticleField(t: t, colors: const [Color(0xFFFFE082), Color(0xFFFFF8E1), Color(0xFF80D8FF)], mode: _ParticleMode.burst, count: 30, seed: 13)),
        ];
    }
  }
}

/// Flickering flame/glow effect (looping).
class _Flicker extends StatelessWidget {
  final Animation<double> t;
  final double phase;
  final IconData icon;
  final Color color;
  final double size;
  const _Flicker({required this.t, required this.phase, required this.icon, required this.color, this.size = 28});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: t,
      builder: (_, __) {
        final v = math.sin((t.value * 2 * math.pi * 2.5) + phase);
        final scale = 1 + 0.12 * v;
        final opacity = 0.78 + 0.22 * v;
        final o = opacity < 0.4 ? 0.4 : (opacity > 1 ? 1.0 : opacity);
        return Transform.scale(scale: scale, child: Icon(icon, color: color.withOpacity(o), size: size));
      },
    );
  }
}

/// Slowly spinning Ashoka chakra.
class _SpinningChakra extends StatelessWidget {
  final Animation<double> t;
  final double size;
  const _SpinningChakra({required this.t, this.size = 72});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: t,
      builder: (_, __) => CustomPaint(size: Size.square(size), painter: _ChakraPainter(angle: t.value * 2 * math.pi)),
    );
  }
}

class _ChakraPainter extends CustomPainter {
  final double angle;
  _ChakraPainter({required this.angle});

  @override
  void paint(Canvas canvas, Size size) {
    final c = Offset(size.width / 2, size.height / 2);
    final r = size.width / 2;
    const navy = Color(0xFF1B3C8C);
    final ring = Paint()..style = PaintingStyle.stroke..strokeWidth = size.width * 0.08..color = navy;
    canvas.drawCircle(c, r * 0.9, ring);
    final spoke = Paint()..strokeWidth = size.width * 0.045..color = navy..strokeCap = StrokeCap.round;
    canvas.save();
    canvas.translate(c.dx, c.dy);
    canvas.rotate(angle);
    for (var i = 0; i < 24; i++) {
      canvas.drawLine(Offset.zero, Offset(r * 0.8, 0), spoke);
      canvas.rotate(math.pi / 12);
    }
    canvas.restore();
    canvas.drawCircle(c, r * 0.13, Paint()..color = navy);
  }

  @override
  bool shouldRepaint(covariant _ChakraPainter old) => old.angle != angle;
}

/// Particle field — rise (sparks), fall (confetti), burst (fireworks).
enum _ParticleMode { rise, fall, burst }

class _ParticleField extends StatelessWidget {
  final Animation<double> t;
  final List<Color> colors;
  final _ParticleMode mode;
  final int count;
  final double seed;
  const _ParticleField({required this.t, required this.colors, this.mode = _ParticleMode.rise, this.count = 24, this.seed = 1});

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: AnimatedBuilder(
        animation: t,
        builder: (_, __) => CustomPaint(size: Size.infinite, painter: _ParticlePainter(progress: t.value, colors: colors, mode: mode, count: count, seed: seed)),
      ),
    );
  }
}

class _ParticlePainter extends CustomPainter {
  final double progress;
  final List<Color> colors;
  final _ParticleMode mode;
  final int count;
  final double seed;
  _ParticlePainter({required this.progress, required this.colors, required this.mode, required this.count, required this.seed});

  @override
  void paint(Canvas canvas, Size size) {
    final rnd = math.Random(seed.toInt());
    final paint = Paint()..style = PaintingStyle.fill;
    for (var i = 0; i < count; i++) {
      final phase = rnd.nextDouble();
      final x0 = rnd.nextDouble();
      final speed = 0.5 + rnd.nextDouble();
      final r = 2.0 + rnd.nextDouble() * 3.0;
      final color = colors[i % colors.length];
      double x = 0, y = 0, opacity = 1;
      switch (mode) {
        case _ParticleMode.rise:
          final tt = (progress * speed + phase) % 1.0;
          y = size.height - tt * size.height;
          x = (x0 + 0.03 * math.sin((tt * 6 + phase) * 2 * math.pi)) * size.width;
          opacity = tt < 0.15 ? tt / 0.15 : (tt > 0.85 ? (1 - tt) / 0.15 : 1);
          break;
        case _ParticleMode.fall:
          final tt = (progress * speed + phase) % 1.0;
          y = tt * size.height;
          x = (x0 + 0.05 * math.sin((tt * 5 + phase) * 2 * math.pi)) * size.width;
          opacity = tt < 0.1 ? tt / 0.1 : 1;
          break;
        case _ParticleMode.burst:
          const burstCount = 4;
          final bi = i % burstCount;
          final bx = (0.22 + 0.56 * (bi / (burstCount - 1))) * size.width;
          final by = size.height * (0.28 + 0.18 * ((i * 7) % 3));
          final tt = (progress * speed + phase) % 1.0;
          final ang = phase * 2 * math.pi;
          final rad = tt * size.width * 0.22;
          x = bx + math.cos(ang) * rad;
          y = by + math.sin(ang) * rad;
          opacity = 1 - tt;
          break;
      }
      final o = opacity < 0 ? 0.0 : (opacity > 1 ? 1.0 : opacity);
      paint.color = color.withOpacity(o);
      canvas.drawCircle(Offset(x, y), r, paint);
    }
  }

  @override
  bool shouldRepaint(covariant _ParticlePainter old) => old.progress != progress;
}
