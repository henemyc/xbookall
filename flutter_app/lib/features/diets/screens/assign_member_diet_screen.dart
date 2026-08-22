import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

// D5: template auto-fill and member-specific trainer customization.
//
// NOTE (bug fix): the dropdown previously used a Map as its value while the
// item list was rebuilt with fresh Map instances on every build(), which made
// Flutter throw "There should be exactly one item with [DropdownButton]'s
// value" as soon as the screen rebuilt. It now uses a stable int (template id)
// as the selected value. Meal editors also keep persistent controllers instead
// of re-creating them on every build.
class AssignMemberDietScreen extends ConsumerStatefulWidget {
  final int memberId;
  final String memberName;

  /// When provided, the screen opens in "edit" mode for an existing
  /// member diet instead of assigning a brand-new one.
  final Map<String, dynamic>? existingDiet;

  const AssignMemberDietScreen({super.key, required this.memberId, required this.memberName, this.existingDiet});
  @override
  ConsumerState<AssignMemberDietScreen> createState() => _AssignMemberDietScreenState();
}

/// Holds the controllers + data of a single editable meal so they persist
/// across rebuilds and can be disposed cleanly.
class _MealField {
  final Map<String, dynamic> data;
  final TextEditingController name = TextEditingController();
  final TextEditingController time = TextEditingController();
  final TextEditingController food = TextEditingController();
  final TextEditingController qty = TextEditingController();

  _MealField([Map<String, dynamic>? initial]) : data = {...?initial} {
    name.text = (initial?['meal_name'] ?? '').toString();
    time.text = (initial?['meal_time'] ?? '').toString();
    food.text = (initial?['food_items'] ?? '').toString();
    qty.text = (initial?['quantity'] ?? '').toString();
  }

  Map<String, dynamic> toMap() => {
        'meal_name': name.text.trim(),
        'meal_time': time.text.trim(),
        'food_items': food.text.trim(),
        'quantity': qty.text.trim(),
      };

  void dispose() {
    name.dispose();
    time.dispose();
    food.dispose();
    qty.dispose();
  }
}

class _AssignMemberDietScreenState extends ConsumerState<AssignMemberDietScreen> {
  List<Map<String, dynamic>> templates = [];
  int? selectedTemplateId;
  bool loading = true;
  String? loadError;

  final title = TextEditingController();
  final instructions = TextEditingController();
  final List<_MealField> meals = [];

  bool get isEditing => widget.existingDiet != null;

  @override
  void initState() {
    super.initState();
    final d = widget.existingDiet;
    if (d != null) {
      title.text = (d['title'] ?? '').toString();
      instructions.text = (d['general_instructions'] ?? '').toString();
      // Meals are loaded directly from the existing plan (no template
      // pre-selection — avoids a dropdown mismatch if the template changed).
      meals.addAll(
        ((d['meals'] as List?) ?? const [])
            .whereType<Map>()
            .map((e) => _MealField(Map<String, dynamic>.from(e))),
      );
    }
    _load();
  }

  @override
  void dispose() {
    title.dispose();
    instructions.dispose();
    for (final m in meals) {
      m.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      loading = true;
      loadError = null;
    });
    try {
      final res = await ref.read(apiClientProvider).getDietTemplates();
      if (!mounted) return;
      setState(() {
        templates = ((res['templates'] as List?) ?? const [])
            .whereType<Map>()
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
        loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        loading = false;
        loadError = 'Could not load diet templates.';
      });
    }
  }

  Map<String, dynamic>? _findTemplate(int id) {
    for (final t in templates) {
      if (int.tryParse(t['id'].toString()) == id) return t;
    }
    return null;
  }

  void _selectTemplate(int? id) {
    final tpl = id == null ? null : _findTemplate(id);
    for (final m in meals) {
      m.dispose();
    }
    setState(() {
      selectedTemplateId = id;
      meals.clear();
      if (tpl != null) {
        title.text = (tpl['title'] ?? '').toString();
        instructions.text = (tpl['general_instructions'] ?? '').toString();
        meals.addAll(
          ((tpl['meals'] as List?) ?? const [])
              .whereType<Map>()
              .map((e) => _MealField(Map<String, dynamic>.from(e))),
        );
      } else {
        title.clear();
        instructions.clear();
      }
    });
  }

  void _addMeal() => setState(() => meals.add(_MealField()));

  void _removeMeal(int index) {
    final removed = meals.removeAt(index);
    removed.dispose();
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(isEditing ? 'Edit Diet • ${widget.memberName}' : 'Assign Diet • ${widget.memberName}')),
      body: loading
          ? const SkeletonList()
          : loadError != null
              ? ErrorRetry(message: loadError!, onRetry: _load)
              : SingleChildScrollView(
                  keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                  padding: EdgeInsets.fromLTRB(16, 12, 16, 32 + MediaQuery.of(context).viewInsets.bottom + MediaQuery.of(context).padding.bottom),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    _introCard(),
                    const SizedBox(height: 20),
                    _sectionTitle('Diet Template', icon: Icons.collections_bookmark_rounded),
                    const SizedBox(height: 10),
                    _templateCard(),
                    const SizedBox(height: 20),
                    _sectionTitle('Diet Details', icon: Icons.edit_note_rounded),
                    const SizedBox(height: 10),
                    _detailsCard(),
                    const SizedBox(height: 20),
                    _sectionTitle(
                      'Meals',
                      icon: Icons.restaurant_menu_rounded,
                      trailing: StatusBadge('${meals.length}', color: AppTheme.brand),
                    ),
                    const SizedBox(height: 10),
                    if (meals.isEmpty) _emptyMeals() else ...meals.asMap().entries.map((e) => Padding(padding: const EdgeInsets.only(bottom: 12), child: _mealCard(e.key, e.value))),
                    const SizedBox(height: 2),
                    OutlinedButton.icon(
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(50),
                        side: BorderSide(color: AppTheme.brand.withOpacity(.5)),
                      ),
                      onPressed: _addMeal,
                      icon: Icon(Icons.add_rounded, color: AppTheme.brand),
                      label: Text('Add Meal', style: TextStyle(color: AppTheme.brand, fontWeight: FontWeight.w700)),
                    ),
                    const SizedBox(height: 24),
                    FireButton(
                      label: isEditing ? 'Save Changes' : (selectedTemplateId == null ? 'Assign Custom Diet' : 'Assign Diet'),
                      icon: isEditing ? Icons.save_rounded : Icons.check_rounded,
                      onPressed: _submit,
                    ),
                  ]),
                ),
    );
  }

  // ── Sections ─────────────────────────────────────────────────────
  Widget _introCard() {
    return SurfaceCard(
      gradient: AppTheme.fireGradient,
      radius: 22,
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
      border: Border.all(color: Colors.white.withOpacity(.12)),
      child: Row(children: [
        Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(color: Colors.white.withOpacity(.18), borderRadius: BorderRadius.circular(14)),
          child: const Icon(Icons.restaurant_menu_rounded, color: Colors.white, size: 24),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(isEditing ? 'Edit diet plan' : 'Assign a diet plan', style: context.typo.headlineSmall?.copyWith(color: Colors.white)),
            const SizedBox(height: 2),
            Text(
              isEditing
                  ? 'Update ${widget.memberName}\'s plan — changes apply to the active diet.'
                  : 'For ${widget.memberName} — pick a template to auto-fill, or build a custom plan.',
              style: context.typo.bodySmall?.copyWith(color: Colors.white.withOpacity(.92)),
            ),
          ]),
        ),
      ]),
    );
  }

  Widget _sectionTitle(String text, {IconData? icon, Widget? trailing}) {
    return Row(children: [
      if (icon != null) ...[Icon(icon, size: 18, color: context.tokens.textSecondary), const SizedBox(width: 8)],
      Text(text, style: context.typo.titleMedium),
      const Spacer(),
      if (trailing != null) trailing,
    ]);
  }

  Widget _templateCard() {
    return SurfaceCard(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (templates.isEmpty)
          _hintText('No diet templates yet. Create some from the Diet Templates screen, or build a custom diet below.')
        else ...[
          DropdownButtonFormField<int>(
            value: selectedTemplateId,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Select Template',
              prefixIcon: Icon(Icons.collections_bookmark_rounded),
            ),
            hint: const Text('Choose a template to auto-fill'),
            items: templates.map<DropdownMenuItem<int>>((e) {
              final id = int.tryParse(e['id'].toString());
              return DropdownMenuItem<int>(
                value: id,
                child: Text('${e['title'] ?? 'Template'}${(e['goal'] ?? '').toString().isNotEmpty ? '  ·  ${e['goal']}' : ''}'),
              );
            }).where((it) => it.value != null).toList(),
            onChanged: _selectTemplate,
          ),
          if (selectedTemplateId != null) ...[
            const SizedBox(height: 4),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton.icon(
                onPressed: () => _selectTemplate(null),
                icon: const Icon(Icons.close_rounded, size: 16),
                label: const Text('Clear & build custom'),
              ),
            ),
          ],
        ],
      ]),
    );
  }

  Widget _detailsCard() {
    return SurfaceCard(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        TextField(
          controller: title,
          textCapitalization: TextCapitalization.sentences,
          decoration: const InputDecoration(
            labelText: 'Diet Title*',
            hintText: 'e.g. Lean Muscle Plan',
            prefixIcon: Icon(Icons.title_rounded),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: instructions,
          maxLines: 3,
          textCapitalization: TextCapitalization.sentences,
          decoration: const InputDecoration(
            labelText: 'General Instructions',
            hintText: 'Hydration, sleep, supplement notes…',
            alignLabelWithHint: true,
            prefixIcon: Icon(Icons.notes_rounded),
          ),
        ),
      ]),
    );
  }

  Widget _mealCard(int index, _MealField meal) {
    return SurfaceCard(
      padding: const EdgeInsets.fromLTRB(16, 12, 8, 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            width: 28,
            height: 28,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AppTheme.brand.withOpacity(.12), borderRadius: BorderRadius.circular(9)),
            child: Text('${index + 1}', style: context.typo.labelLarge?.copyWith(color: AppTheme.brand, fontSize: 13)),
          ),
          const SizedBox(width: 10),
          Expanded(child: Text('Meal ${index + 1}', style: context.typo.titleMedium)),
          IconButton(
            onPressed: () => _removeMeal(index),
            icon: const Icon(Icons.delete_outline_rounded, color: AppTheme.danger),
            tooltip: 'Remove meal',
          ),
        ]),
        const SizedBox(height: 8),
        TextField(
          controller: meal.name,
          textCapitalization: TextCapitalization.sentences,
          decoration: const InputDecoration(labelText: 'Meal Name*', hintText: 'e.g. Breakfast', prefixIcon: Icon(Icons.restaurant_rounded)),
        ),
        const SizedBox(height: 12),
        _timeField(meal),
        const SizedBox(height: 12),
        TextField(
          controller: meal.food,
          maxLines: 2,
          textCapitalization: TextCapitalization.sentences,
          decoration: const InputDecoration(
            labelText: 'Food Items',
            hintText: 'Oats, eggs, chicken…',
            alignLabelWithHint: true,
            prefixIcon: Icon(Icons.fastfood_rounded),
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: meal.qty,
          decoration: const InputDecoration(labelText: 'Quantity', hintText: 'e.g. 200g / 2 whole eggs', prefixIcon: Icon(Icons.scale_rounded)),
        ),
      ]),
    );
  }

  // ── Time picker field (tap-to-select instead of typing) ─────────
  Widget _timeField(_MealField meal) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () async {
        final picked = await showTimePicker(
          context: context,
          initialTime: _parseTimeOfDay(meal.time.text) ?? const TimeOfDay(hour: 8, minute: 0),
          helpText: 'Select meal time',
        );
        if (picked != null && mounted) {
          setState(() => meal.time.text = _formatTimeOfDay(picked));
        }
      },
      child: InputDecorator(
        decoration: const InputDecoration(
          labelText: 'Time',
          prefixIcon: Icon(Icons.schedule_rounded),
          suffixIcon: Icon(Icons.access_time_rounded, size: 20),
        ),
        child: Text(
          meal.time.text.isEmpty ? 'Tap to select time' : meal.time.text,
          style: meal.time.text.isEmpty
              ? context.typo.bodyLarge?.copyWith(color: context.tokens.textTertiary)
              : context.typo.bodyLarge,
        ),
      ),
    );
  }

  /// Parses "7:30 AM", "07:30", "19:30" etc. Returns null if unparseable.
  TimeOfDay? _parseTimeOfDay(String s) {
    final m = RegExp(r'(\d{1,2})\s*[:.]\s*(\d{2})').firstMatch(s.trim());
    if (m == null) return null;
    var hour = int.tryParse(m.group(1)!) ?? 0;
    final minute = int.tryParse(m.group(2)!) ?? 0;
    final isPm = s.toUpperCase().contains('PM');
    final isAm = s.toUpperCase().contains('AM');
    if (isPm && hour < 12) hour += 12;
    if (isAm && hour == 12) hour = 0;
    if (hour < 0 || hour > 23 || minute < 0 || minute > 59) return null;
    return TimeOfDay(hour: hour, minute: minute);
  }

  String _formatTimeOfDay(TimeOfDay t) {
    final h = t.hourOfPeriod == 0 ? 12 : t.hourOfPeriod;
    final mm = t.minute.toString().padLeft(2, '0');
    final ap = t.period == DayPeriod.am ? 'AM' : 'PM';
    return '$h:$mm $ap';
  }

  Widget _emptyMeals() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(vertical: 24),
      decoration: BoxDecoration(
        color: context.tokens.surfaceAlt,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: context.tokens.border),
      ),
      child: Column(children: [
        Icon(Icons.no_meals_rounded, color: context.tokens.textTertiary, size: 30),
        const SizedBox(height: 8),
        Text('No meals yet', style: context.typo.titleSmall),
        const SizedBox(height: 3),
        Text('Tap “Add Meal” to build this diet.', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
      ]),
    );
  }

  Widget _hintText(String text) {
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(Icons.info_outline_rounded, size: 17, color: context.tokens.textTertiary),
      const SizedBox(width: 8),
      Expanded(child: Text(text, style: context.typo.bodySmall?.copyWith(color: context.tokens.textSecondary))),
    ]);
  }

  // ── Submit ───────────────────────────────────────────────────────
  Future<void> _submit() async {
    final titleText = title.text.trim();
    final namedMeals = meals.where((m) => m.name.text.trim().isNotEmpty).toList();

    if (titleText.isEmpty) {
      Toast.error(context, 'Please enter a diet title.');
      return;
    }
    if (meals.isEmpty || namedMeals.isEmpty) {
      Toast.error(context, 'Add at least one meal with a name.');
      return;
    }
    if (namedMeals.length != meals.length) {
      Toast.error(context, 'Every meal needs a name — name or remove empty meals.');
      return;
    }

    try {
      final api = ref.read(apiClientProvider);
      if (isEditing) {
        await api.updateMemberDiet(widget.existingDiet!['id'], {
          'title': titleText,
          'general_instructions': instructions.text.trim(),
          'meals': meals.map((m) => m.toMap()).toList(),
        });
        if (!mounted) return;
        Toast.success(context, 'Diet updated successfully');
        Navigator.pop(context, true);
      } else {
        await api.assignMemberDiet(widget.memberId, {
          'template_id': selectedTemplateId,
          'title': titleText,
          'general_instructions': instructions.text.trim(),
          'meals': meals.map((m) => m.toMap()).toList(),
        });
        if (!mounted) return;
        Toast.success(context, 'Diet assigned successfully');
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (!mounted) return;
      Toast.error(context, _apiError(e, isEditing));
    }
  }

  String _apiError(Object e, [bool editing = false]) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) {
        final msg = data['error'] ?? data['message'];
        if (msg != null && msg.toString().trim().isNotEmpty) return msg.toString();
        if (data['errors'] is Map) {
          for (final v in (data['errors'] as Map).values) {
            if (v is List && v.isNotEmpty) return v.first.toString();
            if (v != null && v.toString().trim().isNotEmpty) return v.toString();
          }
        }
      }
    } catch (_) {}
    return editing ? 'Could not update diet. Please try again.' : 'Could not assign diet. Please try again.';
  }
}
