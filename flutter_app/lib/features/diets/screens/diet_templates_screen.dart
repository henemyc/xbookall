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

  Future<void> _openEditor({Map<String, dynamic>? template}) async {
    final created = await Navigator.push(context, MaterialPageRoute(builder: (_) => DietTemplateFormScreen(template: template)));
    if (created != null && mounted) _load();
  }
}

