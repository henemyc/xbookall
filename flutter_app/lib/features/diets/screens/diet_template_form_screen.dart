import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

// UX: full-page Diet Template create/edit form with time pickers and a
// clean sectioned layout (matches the Assign Diet screen).
class DietTemplateFormScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic>? template;
  const DietTemplateFormScreen({super.key, this.template});
  @override
  ConsumerState<DietTemplateFormScreen> createState() => _DietTemplateFormScreenState();
}

/// Holds the controllers + data of a single editable meal so they persist
/// across rebuilds and can be disposed cleanly.
class _MealField {
  final Map<String, dynamic> data;
  final TextEditingController name = TextEditingController();
  final TextEditingController time = TextEditingController();
  final TextEditingController food = TextEditingController();
  final TextEditingController qty = TextEditingController();
  final TextEditingController calories = TextEditingController();
  final TextEditingController notes = TextEditingController();

  _MealField([Map<String, dynamic>? initial]) : data = {...?initial} {
    name.text = (initial?['meal_name'] ?? '').toString();
    time.text = (initial?['meal_time'] ?? '').toString();
    food.text = (initial?['food_items'] ?? '').toString();
    qty.text = (initial?['quantity'] ?? '').toString();
    calories.text = (initial?['calories'] ?? '').toString();
    notes.text = (initial?['notes'] ?? '').toString();
  }

  Map<String, dynamic> toMap() => {
        'meal_name': name.text.trim(),
        'meal_time': time.text.trim(),
        'food_items': food.text.trim(),
        'quantity': qty.text.trim(),
        'calories': calories.text.isEmpty ? null : int.tryParse(calories.text.trim()),
        'notes': notes.text.trim(),
      };

  void dispose() {
    name.dispose();
    time.dispose();
    food.dispose();
    qty.dispose();
    calories.dispose();
    notes.dispose();
  }
}

class _DietTemplateFormScreenState extends ConsumerState<DietTemplateFormScreen> {
  late final TextEditingController title, goal, type, calories, protein, water, instructions;
  final List<_MealField> meals = [];
  bool saving = false;
  bool get editing => widget.template != null;

  @override
  void initState() {
    super.initState();
    final t = widget.template ?? const {};
    title = TextEditingController(text: t['title'] ?? '');
    goal = TextEditingController(text: t['goal'] ?? '');
    type = TextEditingController(text: t['diet_type'] ?? '');
    calories = TextEditingController(text: t['daily_calories']?.toString() ?? '');
    protein = TextEditingController(text: t['protein_target']?.toString() ?? '');
    water = TextEditingController(text: t['water_target']?.toString() ?? '');
    instructions = TextEditingController(text: t['general_instructions'] ?? '');
    meals.addAll(
      ((t['meals'] as List?) ?? const [])
          .whereType<Map>()
          .map((e) => _MealField(Map<String, dynamic>.from(e))),
    );
    if (meals.isEmpty) meals.add(_MealField());
  }

  @override
  void dispose() {
    title.dispose();
    goal.dispose();
    type.dispose();
    calories.dispose();
    protein.dispose();
    water.dispose();
    instructions.dispose();
    for (final m in meals) {
      m.dispose();
    }
    super.dispose();
  }

  void _addMeal() => setState(() => meals.add(_MealField()));

  void _removeMeal(int index) {
    if (meals.length == 1) return;
    final removed = meals.removeAt(index);
    removed.dispose();
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(editing ? 'Edit Diet Template' : 'Create Diet Template')),
      body: SafeArea(
        child: Column(children: [
          Expanded(
            child: SingleChildScrollView(
              keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
              padding: EdgeInsets.fromLTRB(16, 12, 16, 24 + MediaQuery.of(context).viewInsets.bottom),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                _introCard(),
                const SizedBox(height: 20),
                _sectionTitle('Template Details', icon: Icons.edit_note_rounded),
                const SizedBox(height: 10),
                _detailsCard(),
                const SizedBox(height: 20),
                _sectionTitle('Meals', icon: Icons.restaurant_menu_rounded, trailing: StatusBadge('${meals.length}', color: AppTheme.brand)),
                const SizedBox(height: 10),
                ...meals.asMap().entries.map((e) => Padding(padding: const EdgeInsets.only(bottom: 12), child: _mealCard(e.key, e.value))),
                const SizedBox(height: 2),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(50), side: BorderSide(color: AppTheme.brand.withOpacity(.5))),
                  onPressed: _addMeal,
                  icon: Icon(Icons.add_rounded, color: AppTheme.brand),
                  label: Text('Add Meal', style: TextStyle(color: AppTheme.brand, fontWeight: FontWeight.w700)),
                ),
              ]),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: FireButton(label: editing ? 'Save Changes' : 'Create Template', icon: Icons.check_rounded, loading: saving, onPressed: saving ? null : _save),
          ),
        ]),
      ),
    );
  }

  Widget _introCard() {
    return SurfaceCard(
      gradient: AppTheme.fireGradient,
      radius: 22,
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
      border: Border.all(color: Colors.white.withOpacity(.12)),
      child: Row(children: [
        Container(
          width: 46, height: 46,
          decoration: BoxDecoration(color: Colors.white.withOpacity(.18), borderRadius: BorderRadius.circular(14)),
          child: const Icon(Icons.restaurant_menu_rounded, color: Colors.white, size: 24),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(editing ? 'Edit template' : 'Build a reusable meal plan', style: context.typo.headlineSmall?.copyWith(color: Colors.white)),
            const SizedBox(height: 2),
            Text('Trainers can assign and customize a copy for each member.', style: context.typo.bodySmall?.copyWith(color: Colors.white.withOpacity(.92))),
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

  Widget _detailsCard() {
    return SurfaceCard(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        TextField(
          controller: title,
          textCapitalization: TextCapitalization.sentences,
          decoration: const InputDecoration(labelText: 'Template Title*', hintText: 'e.g. Lean Muscle Plan', prefixIcon: Icon(Icons.title_rounded)),
        ),
        const SizedBox(height: 14),
        Row(children: [
          Expanded(child: TextField(controller: goal, decoration: const InputDecoration(labelText: 'Goal', prefixIcon: Icon(Icons.flag_rounded)))),
          const SizedBox(width: 10),
          Expanded(child: TextField(controller: type, decoration: const InputDecoration(labelText: 'Diet Type', prefixIcon: Icon(Icons.category_rounded)))),
        ]),
        const SizedBox(height: 14),
        Row(children: [
          Expanded(child: TextField(controller: calories, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Calories', prefixIcon: Icon(Icons.local_fire_department_rounded)))),
          const SizedBox(width: 8),
          Expanded(child: TextField(controller: protein, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Protein g', prefixIcon: Icon(Icons.egg_alt_rounded)))),
          const SizedBox(width: 8),
          Expanded(child: TextField(controller: water, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Water ml', prefixIcon: Icon(Icons.water_drop_rounded)))),
        ]),
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
            width: 28, height: 28,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AppTheme.brand.withOpacity(.12), borderRadius: BorderRadius.circular(9)),
            child: Text('${index + 1}', style: context.typo.labelLarge?.copyWith(color: AppTheme.brand, fontSize: 13)),
          ),
          const SizedBox(width: 10),
          Expanded(child: Text('Meal ${index + 1}', style: context.typo.titleMedium)),
          IconButton(
            onPressed: meals.length == 1 ? null : () => _removeMeal(index),
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
        Row(children: [
          Expanded(child: TextField(controller: meal.qty, decoration: const InputDecoration(labelText: 'Quantity', hintText: 'e.g. 200g', prefixIcon: Icon(Icons.scale_rounded)))),
          const SizedBox(width: 8),
          Expanded(child: TextField(controller: meal.calories, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Calories', prefixIcon: Icon(Icons.local_fire_department_rounded)))),
        ]),
        const SizedBox(height: 12),
        TextField(controller: meal.notes, decoration: const InputDecoration(labelText: 'Meal Notes', prefixIcon: Icon(Icons.sticky_note_2_rounded))),
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

  Future<void> _save() async {
    final namedMeals = meals.where((m) => m.name.text.trim().isNotEmpty).toList();
    if (title.text.trim().isEmpty) {
      Toast.error(context, 'Please enter a template title.');
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

    setState(() => saving = true);
    final d = {
      'title': title.text.trim(),
      'goal': goal.text.trim(),
      'diet_type': type.text.trim(),
      'daily_calories': int.tryParse(calories.text.trim()),
      'protein_target': int.tryParse(protein.text.trim()),
      'water_target': int.tryParse(water.text.trim()),
      'general_instructions': instructions.text.trim(),
      'meals': meals.map((m) => m.toMap()).toList(),
    };
    try {
      final r = editing
          ? await ref.read(apiClientProvider).updateDietTemplate(widget.template!['id'], d)
          : await ref.read(apiClientProvider).createDietTemplate(d);
      if (mounted) {
        Toast.success(context, editing ? 'Template updated' : 'Template created');
        Navigator.pop(context, r['template']);
      }
    } catch (_) {
      if (mounted) Toast.error(context, 'Could not save diet template');
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }
}
