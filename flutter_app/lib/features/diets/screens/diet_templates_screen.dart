import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/providers/permission_provider.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/features/diets/screens/diet_template_form_screen.dart';
import 'package:gymxbook/features/diets/screens/diet_template_detail_screen.dart';
import 'package:gymxbook/core/widgets/ui.dart';

// D4: Gym Owner/authorized user reusable diet template editor.
class DietTemplatesScreen extends ConsumerStatefulWidget {
  const DietTemplatesScreen({super.key});

  @override
  ConsumerState<DietTemplatesScreen> createState() => _DietTemplatesScreenState();
}

class _DietTemplatesScreenState extends ConsumerState<DietTemplatesScreen> {
  List templates = [];
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
      final data = await ref.read(apiClientProvider).getDietTemplates();
      if (mounted) setState(() { templates = data['templates'] ?? []; loading = false; });
    } catch (e) {
      if (mounted) setState(() { error = e.toString(); loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final permissions = ref.watch(permissionProvider);
    final canCreate = permissions.can('diets.create');
    final canEdit = permissions.can('diets.edit');
    final canDelete = permissions.can('diets.delete');

    return Scaffold(
      appBar: AppBar(title: const Text('Diet Templates')),
      body: loading
          ? const SkeletonList()
          : error != null
              ? ErrorRetry(message: 'Could not load diet templates.', onRetry: _load)
              : templates.isEmpty
                  ? EmptyState(
                      icon: Icons.restaurant_menu_rounded,
                      title: 'No diet templates',
                      subtitle: canCreate ? 'Create reusable meal plans for members' : 'No templates available for your role',
                      actionLabel: canCreate ? 'Create Template' : null,
                      onAction: canCreate ? () => _openEditor() : null,
                    )
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        padding: EdgeInsets.fromLTRB(16, 12, 16, context.navSpace + 20),
                        itemCount: templates.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (_, index) {
                          final template = Map<String, dynamic>.from(templates[index]);
                          final meals = (template['meals'] as List? ?? const []).length;
                          return SurfaceCard(
                            padding: const EdgeInsets.all(16),
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Row(children: [
                                IconBadge(Icons.restaurant_menu_rounded, color: AppTheme.success, size: 42),
                                const SizedBox(width: 12),
                                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                  Text(template['title'] ?? '', style: context.typo.titleMedium),
                                  Text('${template['goal'] ?? 'General'} • $meals meals', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary)),
                                ])),
                                if (canEdit || canDelete)
                                  PopupMenuButton<String>(
                                    onSelected: (value) {
                                      if (value == 'edit' && canEdit) _openEditor(template: template);
                                      if (value == 'delete' && canDelete) _delete(template);
                                    },
                                    itemBuilder: (_) => [
                                      if (canEdit) const PopupMenuItem(value: 'edit', child: Text('Edit')),
                                      if (canDelete) const PopupMenuItem(value: 'delete', child: Text('Delete', style: TextStyle(color: AppTheme.danger))),
                                    ],
                                  ),
                              ]),
                              if ((template['general_instructions'] ?? '').toString().isNotEmpty) ...[
                                const SizedBox(height: 10),
                                Text(template['general_instructions'], maxLines: 2, overflow: TextOverflow.ellipsis, style: context.typo.bodySmall),
                              ],
                            ]),
                          );
                        },
                      ),
                    ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(
              onPressed: () async { final created = await Navigator.push(context, MaterialPageRoute(builder: (_) => DietTemplateFormScreen())); if (created != null) _load(); },
              icon: const Icon(Icons.add_rounded),
              label: const Text('Create Template'),
              backgroundColor: AppTheme.brand,
            )
          : null,
    );
  }

  Future<void> _delete(Map<String, dynamic> template) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Diet Template?'),
        content: Text('Delete "${template['title']}"? Existing member diets will not be changed.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), child: const Text('Delete')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(apiClientProvider).deleteDietTemplate(template['id']);
      await _load();
      if (mounted) Toast.success(context, 'Diet template deleted');
    } catch (e) {
      if (mounted) Toast.error(context, 'Could not delete diet template');
    }
  }

  void _openEditor({Map<String, dynamic>? template}) {
    final isEditing = template != null;
    final title = TextEditingController(text: template?['title'] ?? '');
    final goal = TextEditingController(text: template?['goal'] ?? '');
    final type = TextEditingController(text: template?['diet_type'] ?? '');
    final calories = TextEditingController(text: template?['daily_calories']?.toString() ?? '');
    final protein = TextEditingController(text: template?['protein_target']?.toString() ?? '');
    final water = TextEditingController(text: template?['water_target']?.toString() ?? '');
    final instructions = TextEditingController(text: template?['general_instructions'] ?? '');
    final meals = ((template?['meals'] as List?) ?? const []).map((e) => _MealDraft.fromMap(Map<String, dynamic>.from(e))).toList();
    if (meals.isEmpty) meals.add(_MealDraft());

    showAppSheet(context, child: StatefulBuilder(builder: (ctx, setSheet) {
      return SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [IconBadge(Icons.restaurant_menu_rounded, color: AppTheme.success), const SizedBox(width: 12), Text(isEditing ? 'Edit Diet Template' : 'Create Diet Template', style: context.typo.titleLarge)]),
            const SizedBox(height: 18),
            TextField(controller: title, decoration: const InputDecoration(labelText: 'Template Title*')),
            const SizedBox(height: 10),
            Row(children: [Expanded(child: TextField(controller: goal, decoration: const InputDecoration(labelText: 'Goal'))), const SizedBox(width: 10), Expanded(child: TextField(controller: type, decoration: const InputDecoration(labelText: 'Diet Type')))]),
            const SizedBox(height: 10),
            Row(children: [Expanded(child: TextField(controller: calories, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Calories'))), const SizedBox(width: 10), Expanded(child: TextField(controller: protein, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Protein g'))), const SizedBox(width: 10), Expanded(child: TextField(controller: water, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Water ml')))]),
            const SizedBox(height: 12),
            TextField(controller: instructions, maxLines: 3, decoration: const InputDecoration(labelText: 'General Instructions')),
            const SizedBox(height: 20),
            Text('Meals', style: context.typo.titleMedium),
            ...List.generate(meals.length, (index) => _mealEditor(meals[index], () => setSheet(() => meals.removeAt(index)))),
            OutlinedButton.icon(onPressed: () => setSheet(() => meals.add(_MealDraft())), icon: const Icon(Icons.add_rounded), label: const Text('Add Meal')),
            const SizedBox(height: 20),
            FireButton(label: isEditing ? 'Save Template' : 'Create Template', onPressed: () async {
              if (title.text.trim().isEmpty || meals.any((m) => m.name.text.trim().isEmpty)) { Toast.error(ctx, 'Template title and all meal names are required'); return; }
              final payload = {
                'title': title.text.trim(), 'goal': goal.text.trim(), 'diet_type': type.text.trim(),
                'daily_calories': int.tryParse(calories.text.trim()), 'protein_target': int.tryParse(protein.text.trim()),
                'water_target': int.tryParse(water.text.trim()), 'general_instructions': instructions.text.trim(),
                'meals': meals.map((m) => m.toMap()).toList(),
              };
              try {
                if (isEditing) await ref.read(apiClientProvider).updateDietTemplate(template['id'], payload);
                else await ref.read(apiClientProvider).createDietTemplate(payload);
                if (ctx.mounted) Navigator.pop(ctx);
                await _load();
                if (mounted) Toast.success(context, isEditing ? 'Diet template updated' : 'Diet template created');
              } catch (e) { if (mounted) Toast.error(context, 'Could not save diet template'); }
            }),
          ]),
        ),
      );
    }));
  }

  Widget _mealEditor(_MealDraft meal, VoidCallback remove) => Padding(
    padding: const EdgeInsets.only(top: 12),
    child: SurfaceCard(
      padding: const EdgeInsets.all(12),
      child: Column(children: [
        Row(children: [Expanded(child: TextField(controller: meal.time, decoration: const InputDecoration(labelText: 'Time'))), const SizedBox(width: 10), Expanded(child: TextField(controller: meal.name, decoration: const InputDecoration(labelText: 'Meal Name*'))), IconButton(onPressed: remove, icon: const Icon(Icons.close_rounded, color: AppTheme.danger))]),
        const SizedBox(height: 10),
        TextField(controller: meal.food, maxLines: 2, decoration: const InputDecoration(labelText: 'Food Items')),
        const SizedBox(height: 10),
        Row(children: [Expanded(child: TextField(controller: meal.quantity, decoration: const InputDecoration(labelText: 'Quantity'))), const SizedBox(width: 10), Expanded(child: TextField(controller: meal.calories, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Calories')))]),
        const SizedBox(height: 10),
        TextField(controller: meal.notes, decoration: const InputDecoration(labelText: 'Meal Notes')),
      ]),
    ),
  );
}

class _MealDraft {
  final time = TextEditingController();
  final name = TextEditingController();
  final food = TextEditingController();
  final quantity = TextEditingController();
  final calories = TextEditingController();
  final notes = TextEditingController();
  _MealDraft();
  _MealDraft.fromMap(Map<String, dynamic> data) {
    time.text = data['meal_time']?.toString() ?? '';
    name.text = data['meal_name']?.toString() ?? '';
    food.text = data['food_items']?.toString() ?? '';
    quantity.text = data['quantity']?.toString() ?? '';
    calories.text = data['calories']?.toString() ?? '';
    notes.text = data['notes']?.toString() ?? '';
  }
  Map<String, dynamic> toMap() => {'meal_time': time.text.trim(), 'meal_name': name.text.trim(), 'food_items': food.text.trim(), 'quantity': quantity.text.trim(), 'calories': int.tryParse(calories.text.trim()), 'notes': notes.text.trim()};
}
