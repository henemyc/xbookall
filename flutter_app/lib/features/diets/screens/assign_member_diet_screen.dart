import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

// D5: template auto-fill and member-specific trainer customization.
class AssignMemberDietScreen extends ConsumerStatefulWidget {
  final int memberId;
  final String memberName;
  const AssignMemberDietScreen({super.key, required this.memberId, required this.memberName});
  @override
  ConsumerState<AssignMemberDietScreen> createState() => _AssignMemberDietScreenState();
}

class _AssignMemberDietScreenState extends ConsumerState<AssignMemberDietScreen> {
  List templates = [];
  Map<String, dynamic>? selected;
  bool loading = true;
  final title = TextEditingController();
  final instructions = TextEditingController();
  List<Map<String, dynamic>> meals = [];

  @override
  void initState() { super.initState(); _load(); }
  Future<void> _load() async {
    try {
      final res = await ref.read(apiClientProvider).getDietTemplates();
      if (mounted) setState(() { templates = List.from(res['templates'] ?? []); loading = false; });
    } catch (_) { if (mounted) setState(() => loading = false); }
  }

  void _select(Map<String, dynamic>? value) {
    setState(() {
      selected = value;
      title.text = value?['title']?.toString() ?? '';
      instructions.text = value?['general_instructions']?.toString() ?? '';
      meals = ((value?['meals'] as List?) ?? const []).map((e) => Map<String, dynamic>.from(e as Map)).toList();
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text('Assign Diet • ${widget.memberName}')),
    body: loading ? const SkeletonList() : SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        DropdownButtonFormField<Map<String, dynamic>>(
          value: selected,
          decoration: const InputDecoration(labelText: 'Select Diet Template'),
          items: templates.map((e) { final t = Map<String, dynamic>.from(e as Map); return DropdownMenuItem(value: t, child: Text(t['title'] ?? 'Template')); }).toList(),
          onChanged: _select,
        ),
        const SizedBox(height: 14),
        TextField(controller: title, decoration: const InputDecoration(labelText: 'Diet Title*')),
        const SizedBox(height: 12),
        TextField(controller: instructions, maxLines: 3, decoration: const InputDecoration(labelText: 'Instructions')),
        const SizedBox(height: 18),
        Text('Auto-filled Meals — customize if needed', style: context.typo.titleMedium),
        const SizedBox(height: 8),
        ...meals.asMap().entries.map((entry) => _mealCard(entry.key, entry.value)),
        OutlinedButton.icon(onPressed: () => setState(() => meals.add({'meal_name': '', 'meal_time': '', 'food_items': '', 'quantity': ''})), icon: const Icon(Icons.add_rounded), label: const Text('Add Meal')),
        const SizedBox(height: 22),
        FireButton(label: selected == null ? 'Assign Custom Diet' : 'Assign Diet', onPressed: _submit),
      ]),
    ),
  );

  Widget _mealCard(int index, Map<String, dynamic> meal) {
    final name = TextEditingController(text: meal['meal_name']?.toString() ?? '');
    final time = TextEditingController(text: meal['meal_time']?.toString() ?? '');
    final food = TextEditingController(text: meal['food_items']?.toString() ?? '');
    final qty = TextEditingController(text: meal['quantity']?.toString() ?? '');
    return SurfaceCard(padding: const EdgeInsets.all(12), child: Column(children: [
      Row(children: [Expanded(child: TextField(controller: time, decoration: const InputDecoration(labelText: 'Time (e.g. 01:50 AM)'), onChanged: (v) => meal['meal_time'] = v)), const SizedBox(width: 8), Expanded(child: TextField(controller: name, decoration: const InputDecoration(labelText: 'Meal Name*'), onChanged: (v) => meal['meal_name'] = v)), IconButton(onPressed: () => setState(() => meals.removeAt(index)), icon: const Icon(Icons.close_rounded, color: AppTheme.danger))]),
      TextField(controller: food, decoration: const InputDecoration(labelText: 'Food Items'), onChanged: (v) => meal['food_items'] = v),
      TextField(controller: qty, decoration: const InputDecoration(labelText: 'Quantity'), onChanged: (v) => meal['quantity'] = v),
    ]));
  }

  Future<void> _submit() async {
    if (title.text.trim().isEmpty || meals.isEmpty || meals.any((m) => (m['meal_name'] ?? '').toString().trim().isEmpty)) { Toast.error(context, 'Diet title and meal names are required'); return; }
    try {
      await ref.read(apiClientProvider).assignMemberDiet(widget.memberId, {'template_id': selected?['id'], 'title': title.text.trim(), 'general_instructions': instructions.text.trim(), 'meals': meals});
      if (mounted) { Toast.success(context, 'Diet assigned successfully'); Navigator.pop(context, true); }
    } catch (e) {
      if (mounted) {
        String message = 'Could not assign diet';
        try {
          final data = (e as dynamic).response?.data;
          if (data is Map && (data['error'] ?? data['message']) != null) message = (data['error'] ?? data['message']).toString();
        } catch (_) {}
        Toast.error(context, message);
      }
    }
  }
}
